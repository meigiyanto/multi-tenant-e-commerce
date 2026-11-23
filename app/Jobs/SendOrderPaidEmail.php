<?php

namespace App\Jobs;

use App\Models\NotificationDelivery;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;
use Throwable;

class SendOrderPaidEmail implements ShouldQueue
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

        $delivery->update(['status' => 'sending', 'last_error' => null]);

        try {
            $order = $delivery->order;
            $lines = $order->items->map(
                fn ($item): string => $item->product_name.' ('.$item->quantity.' x '.$order->currency.' '.$item->unit_price.')',
            )->implode("\n");
            $body = implode("\n", [
                'Halo '.$order->customer_name.',',
                '',
                'Pembayaran untuk pesanan '.$order->order_number.' telah berhasil kami konfirmasi.',
                '',
                'Rincian pesanan:',
                $lines,
                '',
                'Total: '.$order->currency.' '.$order->total,
                'Status pesanan: '.$order->status,
                '',
                'Terima kasih telah berbelanja di '.$delivery->store->name.'.',
            ]);

            Mail::raw($body, function ($message) use ($delivery, $order): void {
                $message->to($delivery->recipient)
                    ->subject('Pembayaran berhasil - '.$order->order_number);
            });

            $delivery->update([
                'status' => 'sent',
                'sent_at' => now(),
                'failed_at' => null,
                'last_error' => null,
            ]);
        } catch (Throwable $exception) {
            $delivery->update([
                'status' => 'queued',
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
                'failed_at' => now(),
                'last_error' => $exception?->getMessage(),
            ]);
    }
}
