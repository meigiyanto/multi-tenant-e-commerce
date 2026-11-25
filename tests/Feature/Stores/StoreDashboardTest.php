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

class StoreDashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_store_owner_can_view_dashboard_with_store_scoped_order_metrics(): void
    {
        $user = User::factory()->create();
        $team = Team::factory()->create();
        $team->members()->attach($user, ['role' => TeamRole::Owner->value]);
        $store = $team->stores()->create(['name' => 'Main Store', 'slug' => 'main-store', 'currency' => 'IDR']);
        $otherTeam = Team::factory()->create();
        $otherStore = $otherTeam->stores()->create(['name' => 'Other Store', 'slug' => 'other-store', 'currency' => 'IDR']);

        $this->createOrder($store, 'ORD-MAIN-PAID', 'paid', 'paid', '125000.00');
        $this->createOrder($store, 'ORD-MAIN-PENDING', 'pending', 'pending', '50000.00');
        $this->createOrder($otherStore, 'ORD-OTHER-PAID', 'processing', 'paid', '900000.00');

        $this->actingAs($user)
            ->get(route('teams.stores.dashboard', ['team' => $team, 'store' => $store]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('stores/dashboard')
                ->where('team.slug', $team->slug)
                ->where('store.id', $store->id)
                ->where('stats.total_orders', 2)
                ->where('stats.pending_orders', 1)
                ->where('stats.paid_orders', 1)
                ->where('stats.paid_revenue', '125000.00')
                ->has('recentOrders', 2)
                ->where('recentOrders.0.order_number', 'ORD-MAIN-PENDING')
                ->where('recentOrders.1.order_number', 'ORD-MAIN-PAID'),
            );
    }

    public function test_team_member_without_store_management_permission_cannot_view_order_dashboard(): void
    {
        $user = User::factory()->create();
        $team = Team::factory()->create();
        $team->members()->attach($user, ['role' => TeamRole::Member->value]);
        $store = $team->stores()->create(['name' => 'Main Store', 'slug' => 'main-store']);

        $this->actingAs($user)
            ->get(route('teams.stores.dashboard', ['team' => $team, 'store' => $store]))
            ->assertForbidden();
    }

    public function test_store_dashboard_rejects_store_from_another_team(): void
    {
        $user = User::factory()->create();
        $team = Team::factory()->create();
        $otherTeam = Team::factory()->create();
        $team->members()->attach($user, ['role' => TeamRole::Owner->value]);
        $otherStore = $otherTeam->stores()->create(['name' => 'Other Store', 'slug' => 'other-store']);

        $this->actingAs($user)
            ->get(route('teams.stores.dashboard', ['team' => $team, 'store' => $otherStore]))
            ->assertNotFound();
    }

    private function createOrder(Store $store, string $number, string $status, string $paymentStatus, string $total): Order
    {
        return Order::query()->create([
            'store_id' => $store->id,
            'order_number' => $number,
            'status' => $status,
            'payment_status' => $paymentStatus,
            'customer_name' => 'Customer '.$number,
            'customer_email' => 'customer@example.test',
            'customer_phone' => '08123456789',
            'shipping_address' => 'Jalan Merdeka 1',
            'shipping_city' => 'Jakarta',
            'shipping_province' => 'DKI Jakarta',
            'shipping_postal_code' => '10110',
            'currency' => $store->currency ?? 'IDR',
            'subtotal' => $total,
            'shipping_amount' => '0.00',
            'discount_amount' => '0.00',
            'total' => $total,
            'placed_at' => $status === 'pending' ? now() : now()->subMinute(),
        ]);
    }
}
