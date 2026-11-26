<?php

namespace App\Services\Notifications;

use App\Models\Inventory;
use App\Models\Order;
use App\Models\Store;
use App\Models\User;
use App\Notifications\Stores\StoreEventNotification;

class StoreNotificationService
{
    public function notifyLowStock(Inventory $inventory, int $previousAvailable): void
    {
        $inventory->loadMissing(['variant.product', 'store.team']);
        $threshold = (int) $inventory->low_stock_threshold;
        $available = max(0, (int) $inventory->quantity - (int) $inventory->reserved_quantity);

        if ($threshold <= 0 || $previousAvailable <= $threshold || $available > $threshold) {
            return;
        }

        $variant = $inventory->variant;
        $product = $variant?->product;

        if ($variant === null || $product === null) {
            return;
        }

        $this->notifyStoreOwner($inventory->store, [
            'event' => 'inventory_low_stock',
            'title' => 'Low stock alert',
            'message' => $product->name.' ('.$variant->name.') has '.$available.' available units remaining.',
            'url' => '/settings/teams/'.$inventory->store->team->slug.'/stores/'.$inventory->store_id.'/catalog/products/'.$product->id.'/edit',
            'store_id' => $inventory->store_id,
            'subject_id' => $inventory->id,
            'inventory_id' => $inventory->id,
            'available_quantity' => $available,
            'low_stock_threshold' => $threshold,
        ]);
    }

    public function notifyOrderReady(Order $order): void
    {
        $order->loadMissing('store.team');
        $owner = $order->store?->team?->owner();

        if (! $owner instanceof User) {
            return;
        }

        $alreadyNotified = $owner->notifications()
            ->where('data->event', 'order_ready')
            ->where('data->subject_id', $order->id)
            ->exists();

        if ($alreadyNotified) {
            return;
        }

        $owner->notify(new StoreEventNotification([
            'event' => 'order_ready',
            'title' => 'New paid order',
            'message' => 'Order '.$order->order_number.' has been paid and is ready for processing.',
            'url' => '/settings/teams/'.$order->store->team->slug.'/stores/'.$order->store_id.'/orders/'.$order->id,
            'store_id' => $order->store_id,
            'subject_id' => $order->id,
            'order_id' => $order->id,
        ]));
    }

    /** @param array<string, mixed> $data */
    private function notifyStoreOwner(Store $store, array $data): void
    {
        $owner = $store->team?->owner();

        if ($owner instanceof User) {
            $owner->notify(new StoreEventNotification($data));
        }
    }
}
