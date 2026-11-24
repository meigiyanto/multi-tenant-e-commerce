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

    public int $timeout = 60;

    /** @return array<int, int> */
    public function backoff(): array
    {
        return [30, 120];
    }

    public function __construct(public int $deliveryId) {}

    public function handle(): void
    {
        $delivery = NotificationDelivery::query()->with(['order.items', 'store'])->findOrFail($this->deliveryId);

        if (in_array($delivery->status, ['sent', 'delivered', 'read'], true)
            || ($delivery->status === 'failed' && data_get($delivery->metadata, 'job_failure') === null)) {
            return;
        }

        // Atomically claim the row to prevent duplicate workers from sending
        // the same email concurrently. Stale claims are recoverable.
        $claimed = NotificationDelivery::query()
            ->whereKey($delivery->id)
            ->where(function ($query): void {
                $query->where('status', 'queued')
                    ->orWhere(function ($stale): void {
                        $stale->where('status', 'sending')
                            ->where('updated_at', '<=', now()->subMinutes(10));
                    })
                    ->orWhere(function ($retryable): void {
                        $retryable->where('status', 'failed')->whereNotNull('metadata->job_failure');
                    });
            })
            ->update(['status' => 'sending', 'last_error' => null, 'updated_at' => now()]);

        if ($claimed !== 1) {
            if ($delivery->status === 'sending') {
                $this->release(600);
            }

            return;
        }

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

            $metadata = (array) $delivery->fresh()->metadata;
            unset($metadata['job_failure']);
            $delivery->update(['metadata' => $metadata]);
        } catch (Throwable $exception) {
            $metadata = (array) $delivery->metadata;
            $metadata['job_failure'] = [
                'exception' => $exception::class,
                'message' => $exception->getMessage(),
                'occurred_at' => now()->toISOString(),
            ];

            $delivery->update([
                'status' => 'queued',
                'last_error' => $exception->getMessage(),
                'metadata' => $metadata,
            ]);

            throw $exception;
        }
    }

    public function failed(?Throwable $exception): void
    {
        $delivery = NotificationDelivery::query()->find($this->deliveryId);
        if ($delivery === null) {
            return;
        }

        $metadata = (array) $delivery->metadata;
        $metadata['job_failure'] = [
            'exception' => $exception === null ? null : $exception::class,
            'message' => $exception?->getMessage(),
            'occurred_at' => now()->toISOString(),
        ];

        NotificationDelivery::query()
            ->whereKey($this->deliveryId)
            ->whereNotIn('status', ['sent', 'delivered', 'read'])
            ->update([
                'status' => 'failed',
                'failed_at' => now(),
                'last_error' => $exception?->getMessage(),
                'metadata' => $metadata,
            ]);
    }
}
