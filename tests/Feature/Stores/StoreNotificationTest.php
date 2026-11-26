<?php

namespace Tests\Feature\Stores;

use App\Enums\TeamRole;
use App\Models\Order;
use App\Models\Store;
use App\Models\Team;
use App\Models\User;
use App\Notifications\Stores\StoreEventNotification;
use App\Services\Notifications\StoreNotificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\DatabaseNotification;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class StoreNotificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_stock_adjustment_notifies_store_owner_when_available_stock_crosses_threshold(): void
    {
        [$owner, $team, $store] = $this->ownedStore();
        $product = $store->products()->create([
            'name' => 'Canvas Bag', 'slug' => 'canvas-bag', 'status' => 'active',
            'price' => 125000, 'track_inventory' => true,
        ]);
        $variant = $product->variants()->create([
            'store_id' => $store->id, 'name' => 'Natural', 'sku' => 'BAG-NATURAL',
        ]);
        $variant->inventory()->create([
            'store_id' => $store->id, 'quantity' => 5, 'low_stock_threshold' => 3,
        ]);

        $this->actingAs($owner)->post(route('teams.stores.catalog.stock.adjust', [$team, $store, $product, $variant]), [
            'quantity' => -2,
            'notes' => 'Damaged stock',
        ])->assertRedirect();

        $notification = DatabaseNotification::query()
            ->where('notifiable_id', $owner->id)
            ->where('type', StoreEventNotification::class)
            ->where('data->event', 'inventory_low_stock')
            ->firstOrFail();

        $this->assertSame(3, data_get($notification->data, 'available_quantity'));
        $this->assertSame($store->id, data_get($notification->data, 'store_id'));

        $this->actingAs($owner)->get(route('teams.stores.notifications.index', [$team, $store]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('stores/notifications/index')
                ->where('unreadCount', 1)
                ->where('notifications.data.0.title', 'Low stock alert'));

        $this->actingAs($owner)->get(route('teams.stores.notifications.read', [$team, $store, $notification->id]))
            ->assertRedirect('/settings/teams/'.$team->slug.'/stores/'.$store->id.'/catalog/products/'.$product->id.'/edit');

        $this->assertNotNull($notification->fresh()->read_at);
    }

    public function test_low_stock_alert_is_not_sent_when_threshold_is_disabled(): void
    {
        [$owner, $team, $store] = $this->ownedStore();
        $product = $store->products()->create([
            'name' => 'Water Bottle', 'slug' => 'water-bottle', 'status' => 'active',
            'price' => 50000, 'track_inventory' => true,
        ]);
        $variant = $product->variants()->create([
            'store_id' => $store->id, 'name' => 'Default', 'sku' => 'BOTTLE-01',
        ]);
        $variant->inventory()->create(['store_id' => $store->id, 'quantity' => 2]);

        $this->actingAs($owner)->post(route('teams.stores.catalog.stock.adjust', [$team, $store, $product, $variant]), [
            'quantity' => -1,
        ])->assertRedirect();

        $this->assertDatabaseMissing('notifications', [
            'notifiable_id' => $owner->id,
            'type' => StoreEventNotification::class,
        ]);
    }

    public function test_paid_order_notification_is_idempotent_and_scoped_to_store_owner(): void
    {
        [$owner, , $store] = $this->ownedStore();
        $order = Order::query()->create([
            'store_id' => $store->id,
            'order_number' => 'ORD-STORE-NOTIFY-1',
            'status' => 'processing',
            'payment_status' => 'paid',
            'customer_name' => 'Test Customer',
            'customer_email' => 'customer@example.com',
            'customer_phone' => '+628123456789',
            'shipping_address' => 'Test Street',
            'shipping_city' => 'Jakarta',
            'shipping_province' => 'DKI Jakarta',
            'shipping_postal_code' => '12345',
            'currency' => 'IDR',
            'subtotal' => 100000,
            'shipping_amount' => 0,
            'discount_amount' => 0,
            'total' => 100000,
            'placed_at' => now(),
        ]);

        $service = app(StoreNotificationService::class);
        $service->notifyOrderReady($order);
        $service->notifyOrderReady($order);

        $this->assertSame(1, DatabaseNotification::query()
            ->where('notifiable_id', $owner->id)
            ->where('type', StoreEventNotification::class)
            ->where('data->event', 'order_ready')
            ->where('data->subject_id', $order->id)
            ->count());
    }

    /** @return array{User, Team, Store} */
    private function ownedStore(): array
    {
        $owner = User::factory()->create();
        $team = Team::factory()->create();
        $team->members()->attach($owner, ['role' => TeamRole::Owner->value]);
        $store = $team->stores()->create([
            'name' => 'Notification Store',
            'slug' => 'notification-store-'.$team->id,
            'status' => 'active',
            'timezone' => 'Asia/Jakarta',
            'currency' => 'IDR',
        ]);

        return [$owner, $team, $store];
    }
}
