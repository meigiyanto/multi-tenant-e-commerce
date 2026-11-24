<?php

namespace App\Jobs;

use App\Models\NotificationDelivery;
use App\Services\Orders\OrderTrackingUrl;
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

    public function handle(OrderTrackingUrl $tracking): void
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
            $trackingUrl = $tracking->for($order);
            $eventMessage = match ($delivery->event) {
                'order_shipped' => 'Your order has been shipped.',
                'order_delivered' => 'Your order has been marked as delivered.',
                default => 'Your payment has been confirmed successfully.',
            };
            $body = implode("\n", [
                'Hello '.$order->customer_name.',',
                '',
                $eventMessage,
                'Order number: '.$order->order_number,
                'Order status: '.$order->status,
                'Total: '.$order->currency.' '.$order->total,
                $order->shipping_tracking_number ? 'Tracking number: '.$order->shipping_tracking_number : '',
                'Track your order: '.$trackingUrl,
                '',
                'Thank you for shopping at '.$delivery->store->name.'.',
            ]);

            Mail::raw($body, function ($message) use ($delivery, $order): void {
                $subject = match ($delivery->event) {
                    'order_shipped' => 'Your order has shipped - '.$order->order_number,
                    'order_delivered' => 'Your order was delivered - '.$order->order_number,
                    default => 'Payment confirmed - '.$order->order_number,
                };
                $message->to($delivery->recipient)->subject($subject);
            });

            $delivery->update([
                'status' => 'sent',
                'sent_at' => now(),
                'failed_at' => null,
                'last_error' => null,
            ]);

            $metadata = (array) $delivery->fresh()->metadata;
            $metadata = array_diff_key($metadata, ['job_failure' => true]);
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
