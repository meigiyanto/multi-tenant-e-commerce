<?php

namespace App\Services\Stores;

use App\Models\Order;
use App\Models\Store;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class StoreOrderStatusService
{
    public function transition(
        Store $store,
        Order $order,
        User $actor,
        string $toStatus,
        ?string $note = null,
        ?string $trackingNumber = null,
        ?string $trackingUrl = null,
    ): Order {
        return DB::transaction(function () use ($store, $order, $actor, $toStatus, $note, $trackingNumber, $trackingUrl): Order {
            $lockedOrder = Order::query()
                ->where('store_id', $store->id)
                ->whereKey($order->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($lockedOrder->payment_status !== 'paid') {
                throw ValidationException::withMessages([
                    'status' => 'Only paid orders can move into fulfillment.',
                ]);
            }

            $allowedTransitions = [
                'processing' => ['shipped'],
                'shipped' => ['delivered'],
            ];

            if (! in_array($toStatus, $allowedTransitions[$lockedOrder->status] ?? [], true)) {
                throw ValidationException::withMessages([
                    'status' => 'This order status transition is not allowed.',
                ]);
            }

            if ($toStatus === 'shipped' && trim((string) $trackingNumber) === '') {
                throw ValidationException::withMessages([
                    'tracking_number' => 'A tracking number is required before an order can be marked as shipped.',
                ]);
            }

            if ($toStatus === 'delivered' && $lockedOrder->shipped_at === null) {
                throw ValidationException::withMessages([
                    'status' => 'An order must be shipped before it can be marked as delivered.',
                ]);
            }

            $fromStatus = $lockedOrder->status;
            $updates = ['status' => $toStatus];

            if ($toStatus === 'shipped') {
                $updates['shipping_tracking_number'] = trim((string) $trackingNumber);
                $updates['shipping_tracking_url'] = $trackingUrl;
                $updates['shipped_at'] = now();
            }

            if ($toStatus === 'delivered') {
                $updates['delivered_at'] = now();
            }

            $lockedOrder->update($updates);
            $lockedOrder->statusHistories()->create([
                'store_id' => $store->id,
                'user_id' => $actor->id,
                'from_status' => $fromStatus,
                'to_status' => $toStatus,
                'note' => $note,
            ]);

            return $lockedOrder->refresh();
        });
    }
}
