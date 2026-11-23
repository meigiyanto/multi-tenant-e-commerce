<?php

namespace Tests\\Feature\\Payments;

use App\\Enums\\TeamRole;
use App\\Jobs\\SendOrderPaidEmail;
use App\\Jobs\\SendOrderPaidWhatsApp;
use App\\Models\\Order;
use App\\Models\\PaymentTransaction;
use App\\Models\\Team;
use App\\Models\\User;
use Illuminate\\Foundation\\Testing\\RefreshDatabase;
use Illuminate\\Support\\Facades\\Queue;
use Tests\\TestCase;

class BackfillPaidOrderNotificationsTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_defaults_to_dry_run_without_creating_deliveries_or_queueing_messages(): void
    {
        Queue::fake();
        $order = $this->paidOrder();

        $this->artisan('notifications:backfill-paid-orders')
            ->expectsOutputToContain('DRY RUN')
            ->assertSuccessful();

        $this->assertDatabaseCount('notification_deliveries', 0);
        Queue::assertNothingPushed();
        $this->assertSame('paid', $order->fresh()->payment_status);
    }

    public function test_it_dispatches_missing_channels_only_when_explicitly_requested(): void
    {
        Queue::fake();
        $order = $this->paidOrder();

        $this->artisan('notifications:backfill-paid-orders', ['--dispatch' => true])
            ->expectsOutputToContain('Backfill completed')
            ->assertSuccessful();

        $this->assertDatabaseCount('notification_deliveries', 2);
        Queue::assertPushed(SendOrderPaidEmail::class, 1);
        Queue::assertPushed(SendOrderPaidWhatsApp::class, 1);

        $this->artisan('notifications:backfill-paid-orders', ['--dispatch' => true])
            ->assertSuccessful();

        $this->assertDatabaseCount('notification_deliveries', 2);
        Queue::assertPushed(SendOrderPaidEmail::class, 1);
        Queue::assertPushed(SendOrderPaidWhatsApp::class, 1);
    }

    public function test_it_can_limit_backfill_to_a_single_paid_order(): void
    {
        Queue::fake();
        $first = $this->paidOrder();
        $second = $this->paidOrder();

        $this->artisan('notifications:backfill-paid-orders', [
            '--dispatch' => true,
            '--order' => (string) $first->id,
        ])->assertSuccessful();

        $this->assertDatabaseCount('notification_deliveries', 2);
        $this->assertDatabaseHas('notification_deliveries', ['order_id' => $first->id, 'channel' => 'mail']);
        $this->assertDatabaseMissing('notification_deliveries', ['order_id' => $second->id]);
        Queue::assertPushed(SendOrderPaidEmail::class, 1);
        Queue::assertPushed(SendOrderPaidWhatsApp::class, 1);
    }

    private function paidOrder(): Order
    {
        $user = User::factory()->create();
        $team = Team::factory()->create();
        $team->members()->attach($user, ['role' => TeamRole::Owner->value]);
        $store = $team->stores()->create([
            'name' => 'Backfill Test Store',
            'slug' => 'backfill-test-store-'.$team->id,
            'status' => 'active',
            'timezone' => 'Asia/Jakarta',
            'currency' => 'IDR',
        ]);
        $order = Order::query()->create([
            'store_id' => $store->id,
            'user_id' => $user->id,
            'order_number' => 'ORD-BACKFILL-'.$store->id,
            'status' => 'processing',
            'payment_status' => 'paid',
            'customer_name' => 'Backfill Customer',
            'customer_email' => 'backfill@example.com',
            'customer_phone' => '+628123456789',
            'shipping_address' => 'Jl. Test No. 1',
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
        PaymentTransaction::query()->create([
            'store_id' => $store->id,
            'order_id' => $order->id,
            'gateway' => 'midtrans',
            'gateway_order_id' => $order->order_number,
            'amount' => 100000,
            'currency' => 'IDR',
            'status' => 'paid',
            'paid_at' => now(),
        ]);

        return $order;
    }
}
