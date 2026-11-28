<?php

namespace App\Services\Notifications;

use App\Enums\TeamRole;
use App\Models\Inventory;
use App\Models\Order;
use App\Models\Store;
use App\Models\Team;
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

        $store = $inventory->store;
        $team = $store?->team;
        $variant = $inventory->variant;
        $product = $variant?->product;

        if ($store === null || $team === null || $variant === null || $product === null) {
            return;
        }

        $this->notifyStoreOwner($store, [
            'event' => 'inventory_low_stock',
            'title' => 'Low stock alert',
            'message' => $product->name.' ('.$variant->name.') has '.$available.' available units remaining.',
            'url' => '/settings/teams/'.$team->slug.'/stores/'.$inventory->store_id.'/catalog/products/'.$product->id.'/edit',
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
        $store = $order->store;
        $team = $store?->team;
        if ($store === null || $team === null) {
            return;
        }

        $this->notifyTeamManagers($team, [
            'event' => 'order_ready',
            'title' => 'New paid order',
            'message' => 'Order '.$order->order_number.' has been paid and is ready for processing.',
            'url' => '/settings/teams/'.$team->slug.'/stores/'.$order->store_id.'/orders/'.$order->id,
            'store_id' => $order->store_id,
            'subject_id' => $order->id,
            'order_id' => $order->id,
        ], true);
    }

    /** @param array<string, mixed> $data */
    private function notifyStoreOwner(Store $store, array $data): void
    {
        $team = $store->team;

        if ($team !== null) {
            $this->notifyTeamManagers($team, $data);
        }
    }

    /** @param array<string, mixed> $data */
    private function notifyTeamManagers(Team $team, array $data, bool $preventDuplicate = false): void
    {
        $recipients = $team->members()
            ->wherePivotIn('role', [TeamRole::Owner->value, TeamRole::Admin->value])
            ->get();

        foreach ($recipients as $recipient) {
            if ($preventDuplicate && $recipient->notifications()
                ->where('data->event', $data['event'] ?? null)
                ->where('data->subject_id', $data['subject_id'] ?? null)
                ->exists()) {
                continue;
            }

            $recipient->notify(new StoreEventNotification($data));
        }
    }
}
