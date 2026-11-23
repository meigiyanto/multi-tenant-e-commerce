<?php

namespace App\Services\Stores;

use App\Models\Order;
use App\Models\Store;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class StoreOrderStatusService
{
    public function transition(Store $store, Order $order, User $actor, string $toStatus, ?string $note = null): Order
    {
        return DB::transaction(function () use ($store, $order, $actor, $toStatus, $note): Order {
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

            $fromStatus = $lockedOrder->status;
            $lockedOrder->update(['status' => $toStatus]);
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
