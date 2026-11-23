<?php

namespace Tests\Feature\Stores;

use App\Enums\TeamRole;
use App\Models\Order;
use App\Models\Store;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class StoreOrderManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_store_owner_can_filter_orders_without_leaking_other_store_orders(): void
    {
        [$user, $team, $store] = $this->ownedStore();
        $this->createOrder($store, 'ORD-PAID', 'processing', 'paid');
        $this->createOrder($store, 'ORD-PENDING', 'pending', 'pending');
        [, , $otherStore] = $this->ownedStore();
        $this->createOrder($otherStore, 'ORD-OTHER', 'processing', 'paid');

        $this->actingAs($user)
            ->get(route('teams.stores.orders.index', [
                'team' => $team,
                'store' => $store,
                'status' => 'processing',
            ]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('stores/orders/index')
                ->where('filters.status', 'processing')
                ->where('orders.total', 1)
                ->where('orders.data.0.order_number', 'ORD-PAID'),
            );
    }

    public function test_team_member_without_store_management_permission_cannot_view_orders(): void
    {
        $user = User::factory()->create();
        $team = Team::factory()->create();
        $team->members()->attach($user, ['role' => TeamRole::Member->value]);
        $store = $team->stores()->create(['name' => 'Member Store', 'slug' => 'member-store']);

        $this->actingAs($user)
            ->get(route('teams.stores.orders.index', ['team' => $team, 'store' => $store]))
            ->assertForbidden();
    }

    public function test_order_detail_rejects_order_from_another_store(): void
    {
        [$user, $team, $store] = $this->ownedStore();
        [, , $otherStore] = $this->ownedStore();
        $order = $this->createOrder($otherStore, 'ORD-OTHER', 'processing', 'paid');

        $this->actingAs($user)
            ->get(route('teams.stores.orders.show', ['team' => $team, 'store' => $store, 'order' => $order]))
            ->assertNotFound();
    }

    public function test_paid_processing_order_can_be_marked_shipped_and_history_is_recorded(): void
    {
        [$user, $team, $store] = $this->ownedStore();
        $order = $this->createOrder($store, 'ORD-SHIP', 'processing', 'paid');

        $this->actingAs($user)
            ->patch(route('teams.stores.orders.status', ['team' => $team, 'store' => $store, 'order' => $order]), [
                'status' => 'shipped',
                'note' => 'Handed to courier.',
            ])
            ->assertRedirect(route('teams.stores.orders.show', ['team' => $team->slug, 'store' => $store->id, 'order' => $order->id]));

        $this->assertDatabaseHas('orders', ['id' => $order->id, 'store_id' => $store->id, 'status' => 'shipped', 'payment_status' => 'paid']);
        $this->assertDatabaseHas('order_status_histories', [
            'store_id' => $store->id,
            'order_id' => $order->id,
            'user_id' => $user->id,
            'from_status' => 'processing',
            'to_status' => 'shipped',
            'note' => 'Handed to courier.',
        ]);
    }

    public function test_unpaid_order_cannot_enter_fulfillment(): void
    {
        [$user, $team, $store] = $this->ownedStore();
        $order = $this->createOrder($store, 'ORD-UNPAID', 'processing', 'pending');

        $this->actingAs($user)
            ->from(route('teams.stores.orders.show', ['team' => $team, 'store' => $store, 'order' => $order]))
            ->patch(route('teams.stores.orders.status', ['team' => $team, 'store' => $store, 'order' => $order]), [
                'status' => 'shipped',
            ])
            ->assertSessionHasErrors('status');

        $this->assertDatabaseHas('orders', ['id' => $order->id, 'status' => 'processing', 'payment_status' => 'pending']);
        $this->assertDatabaseCount('order_status_histories', 0);
    }

    public function test_order_cannot_skip_from_processing_to_delivered(): void
    {
        [$user, $team, $store] = $this->ownedStore();
        $order = $this->createOrder($store, 'ORD-SKIP', 'processing', 'paid');

        $this->actingAs($user)
            ->from(route('teams.stores.orders.show', ['team' => $team, 'store' => $store, 'order' => $order]))
            ->patch(route('teams.stores.orders.status', ['team' => $team, 'store' => $store, 'order' => $order]), [
                'status' => 'delivered',
            ])
            ->assertSessionHasErrors('status');

        $this->assertDatabaseHas('orders', ['id' => $order->id, 'status' => 'processing']);
        $this->assertDatabaseCount('order_status_histories', 0);
    }

    private function ownedStore(): array
    {
        $user = User::factory()->create();
        $team = Team::factory()->create();
        $team->members()->attach($user, ['role' => TeamRole::Owner->value]);
        $store = $team->stores()->create([
            'name' => 'Order Store',
            'slug' => 'order-store-'.$team->id,
            'status' => 'active',
            'timezone' => 'Asia/Jakarta',
            'currency' => 'IDR',
        ]);

        return [$user, $team, $store];
    }

    private function createOrder(Store $store, string $number, string $status, string $paymentStatus): Order
    {
        return Order::query()->create([
            'store_id' => $store->id,
            'order_number' => $number,
            'status' => $status,
            'payment_status' => $paymentStatus,
            'customer_name' => 'Order Customer',
            'customer_email' => 'customer@example.test',
            'customer_phone' => '08123456789',
            'shipping_address' => 'Jalan Merdeka 1',
            'shipping_city' => 'Jakarta',
            'shipping_province' => 'DKI Jakarta',
            'shipping_postal_code' => '10110',
            'currency' => $store->currency,
            'subtotal' => '100000.00',
            'shipping_amount' => '10000.00',
            'discount_amount' => '0.00',
            'total' => '110000.00',
            'placed_at' => now(),
        ]);
    }
}
