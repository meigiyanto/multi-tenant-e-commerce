<?php

namespace Tests\Feature\Payments;

use App\Enums\TeamRole;
use App\Models\Inventory;
use App\Models\Order;
use App\Models\PaymentTransaction;
use App\Models\Store;
use App\Models\Team;
use App\Models\User;
use App\Services\Payments\PaymentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class PaymentReconciliationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'payments.enabled' => ['midtrans'],
            'payments.gateways.midtrans.server_key' => 'SB-server-key',
            'payments.gateways.midtrans.production' => false,
        ]);
    }

    public function test_it_reconciles_a_successful_payment_and_consumes_inventory_once(): void
    {
        [$transaction, $order, $inventory] = $this->pendingPayment();
        Http::fake([
            'https://api.sandbox.midtrans.com/v2/*/status' => Http::response($this->statusPayload($transaction, 'settlement')),
        ]);

        $reconciled = app(PaymentService::class)->reconcilePendingTransactions();

        $this->assertSame(1, $reconciled);
        $this->assertDatabaseHas('payment_transactions', ['id' => $transaction->id, 'status' => 'paid']);
        $this->assertDatabaseHas('orders', ['id' => $order->id, 'payment_status' => 'paid', 'status' => 'processing']);
        $this->assertDatabaseHas('inventories', [
            'id' => $inventory->id,
            'quantity' => 3,
            'reserved_quantity' => 0,
        ]);

        $this->assertSame(0, app(PaymentService::class)->reconcilePendingTransactions());
        Http::assertSentCount(1);
    }

    public function test_it_keeps_a_transaction_pending_when_midtrans_still_reports_pending(): void
    {
        [$transaction, $order] = $this->pendingPayment();
        Http::fake([
            'https://api.sandbox.midtrans.com/v2/*/status' => Http::response($this->statusPayload($transaction, 'pending')),
        ]);

        $this->assertSame(0, app(PaymentService::class)->reconcilePendingTransactions());

        $transaction->refresh();
        $this->assertSame('pending', $transaction->status);
        $this->assertSame('pending', $order->fresh()->payment_status);
        $this->assertSame('checked', data_get($transaction->metadata, 'reconciliation.result'));
    }

    public function test_it_does_not_mark_a_payment_paid_when_the_status_api_amount_differs(): void
    {
        [$transaction, $order] = $this->pendingPayment();
        $payload = $this->statusPayload($transaction, 'settlement');
        $payload['gross_amount'] = '1.00';
        Http::fake([
            'https://api.sandbox.midtrans.com/v2/*/status' => Http::response($payload),
        ]);

        $this->assertSame(0, app(PaymentService::class)->reconcilePendingTransactions());

        $transaction->refresh();
        $this->assertSame('pending', $transaction->status);
        $this->assertSame('pending', $order->fresh()->payment_status);
        $this->assertSame('error', data_get($transaction->metadata, 'reconciliation.result'));
    }

    public function test_it_records_successful_payment_even_when_inventory_requires_manual_review(): void
    {
        [$transaction, $order, $inventory] = $this->pendingPayment();
        $inventory->update(['reserved_quantity' => 0]);
        Http::fake([
            'https://api.sandbox.midtrans.com/v2/*/status' => Http::response($this->statusPayload($transaction, 'settlement')),
        ]);

        $this->assertSame(1, app(PaymentService::class)->reconcilePendingTransactions());

        $transaction->refresh();
        $order->refresh();
        $inventory->refresh();

        $this->assertSame('paid', $transaction->status);
        $this->assertSame('paid', $order->payment_status);
        $this->assertSame('pending', $order->status);
        $this->assertNotNull(data_get($transaction->metadata, 'inventory_review_required'));
        $this->assertSame(5, $inventory->quantity);
        $this->assertSame(0, $inventory->reserved_quantity);
    }

    public function test_it_records_payment_after_order_cancellation_for_manual_review(): void
    {
        [$transaction, $order] = $this->pendingPayment();
        $order->update(['status' => 'cancelled']);
        Http::fake([
            'https://api.sandbox.midtrans.com/v2/*/status' => Http::response($this->statusPayload($transaction, 'settlement')),
        ]);

        $this->assertSame(1, app(PaymentService::class)->reconcilePendingTransactions());

        $transaction->refresh();
        $order->refresh();

        $this->assertSame('paid', $transaction->status);
        $this->assertSame('paid', $order->payment_status);
        $this->assertSame('cancelled', $order->status);
        $this->assertNotNull(data_get($transaction->metadata, 'inventory_review_required'));
    }

    private function pendingPayment(): array
    {
        $user = User::factory()->create();
        $team = Team::factory()->create();
        $team->members()->attach($user, ['role' => TeamRole::Owner->value]);
        $store = $team->stores()->create([
            'name' => 'Payment Test Store',
            'slug' => 'payment-test-store-'.$team->id,
            'status' => 'active',
            'timezone' => 'Asia/Jakarta',
            'currency' => 'IDR',
        ]);

        $product = $store->products()->create([
            'name' => 'Payment Test Product',
            'slug' => 'payment-test-product-'.$store->id,
            'status' => 'active',
            'price' => 50000,
            'track_inventory' => true,
        ]);
        $variant = $product->variants()->create([
            'store_id' => $store->id,
            'name' => 'Default',
            'sku' => 'PAYMENT-'.$store->id,
            'price' => 50000,
            'options' => [],
        ]);
        $inventory = $variant->inventory()->create([
            'store_id' => $store->id,
            'quantity' => 5,
            'reserved_quantity' => 2,
        ]);

        $order = Order::query()->create([
            'store_id' => $store->id,
            'user_id' => $user->id,
            'order_number' => 'ORD-RECON-'.$store->id,
            'status' => 'pending',
            'payment_status' => 'pending',
            'customer_name' => 'Payment Customer',
            'customer_email' => 'payment@example.com',
            'customer_phone' => '+628123456789',
            'shipping_address' => 'Jl. Test No. 1',
            'shipping_city' => 'Jakarta',
            'shipping_province' => 'DKI Jakarta',
            'shipping_postal_code' => '12345',
            'currency' => 'IDR',
            'subtotal' => 100000,
            'shipping_amount' => 15000,
            'discount_amount' => 0,
            'total' => 115000,
            'placed_at' => now()->subMinutes(10),
        ]);
        $order->items()->create([
            'store_id' => $store->id,
            'product_id' => $product->id,
            'product_variant_id' => $variant->id,
            'product_name' => $product->name,
            'variant_name' => 'Default',
            'sku' => $variant->sku,
            'options' => [],
            'quantity' => 2,
            'track_inventory' => true,
            'unit_price' => 50000,
            'line_total' => 100000,
        ]);
        $transaction = $order->paymentTransactions()->create([
            'store_id' => $store->id,
            'gateway' => 'midtrans',
            'gateway_order_id' => 'MIDTRANS-RECON-'.$store->id,
            'amount' => 115000,
            'currency' => 'IDR',
            'status' => 'pending',
            'redirect_url' => 'https://app.sandbox.midtrans.com/snap/v2/vtweb/test-token',
        ]);

        DB::table('payment_transactions')->where('id', $transaction->id)->update([
            'created_at' => now()->subMinutes(10),
            'updated_at' => now()->subMinutes(10),
        ]);

        return [$transaction->fresh(), $order, $inventory];
    }

    /** @return array<string, mixed> */
    private function statusPayload(PaymentTransaction $transaction, string $status): array
    {
        return [
            'status_code' => '200',
            'order_id' => $transaction->gateway_order_id,
            'transaction_id' => 'midtrans-transaction-'.$transaction->id,
            'transaction_status' => $status,
            'fraud_status' => $status === 'capture' ? 'accept' : null,
            'gross_amount' => '115000.00',
            'currency' => 'IDR',
        ];
    }
}
