<?php

namespace App\Jobs;

use App\Models\NotificationDelivery;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Throwable;

class SendOrderPaidWhatsApp implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;

    public function __construct(public int $deliveryId) {}

    public function handle(): void
    {
        $delivery = NotificationDelivery::query()->with(['order.items', 'store'])->findOrFail($this->deliveryId);

        if (in_array($delivery->status, ['sent', 'delivered', 'read'], true)) {
            return;
        }

        $token = (string) config('services.fonnte.token');
        if ($token === '') {
            throw new \RuntimeException('FONNTE_TOKEN belum dikonfigurasi.');
        }

        $order = $delivery->order;
        $lines = $order->items->map(
            fn ($item): string => '- '.$item->product_name.' ('.$item->quantity.' x '.$order->currency.' '.$item->unit_price.')',
        )->implode("\n");
        $message = implode("\n", [
            'Halo '.$order->customer_name.',',
            '',
            'Pembayaran pesanan '.$order->order_number.' berhasil dikonfirmasi.',
            '',
            'Rincian pesanan:',
            $lines,
            '',
            'Total: '.$order->currency.' '.$order->total,
            'Status pesanan: '.$order->status,
            '',
            'Terima kasih telah berbelanja di '.$delivery->store->name.'.',
        ]);

        $delivery->update(['status' => 'sending', 'last_error' => null]);

        try {
            $response = Http::asForm()
                ->withHeaders(['Authorization' => $token])
                ->timeout(20)
                ->post('https://api.fonnte.com/send', [
                    'target' => $delivery->recipient,
                    'message' => $message,
                    'countryCode' => '62',
                    'connectOnly' => true,
                ]);

            $result = $response->json();
            if (! $response->successful() || ! is_array($result) || ! ($result['status'] ?? false)) {
                throw new \RuntimeException('Fonnte menolak pengiriman: '.substr($response->body(), 0, 1000));
            }

            $messageIds = $result['id'] ?? [];
            $messageId = is_array($messageIds) ? (string) (array_values($messageIds)[0] ?? '') : (string) $messageIds;

            $delivery->update([
                'status' => 'sent',
                'provider' => 'fonnte',
                'provider_message_id' => $messageId !== '' ? $messageId : null,
                'sent_at' => now(),
                'failed_at' => null,
                'last_error' => null,
                'metadata' => array_merge((array) $delivery->metadata, [
                    'fonnte' => [
                        'process' => $result['process'] ?? null,
                        'detail' => $result['detail'] ?? null,
                    ],
                ]),
            ]);
        } catch (ConnectionException|Throwable $exception) {
            $delivery->update([
                'status' => 'queued',
                'provider' => 'fonnte',
                'last_error' => $exception->getMessage(),
            ]);

            throw $exception;
        }
    }

    public function failed(?Throwable $exception): void
    {
        NotificationDelivery::query()
            ->whereKey($this->deliveryId)
            ->whereNotIn('status', ['sent', 'delivered', 'read'])
            ->update([
                'status' => 'failed',
                'provider' => 'fonnte',
                'failed_at' => now(),
                'last_error' => $exception?->getMessage(),
            ]);
    }
}
