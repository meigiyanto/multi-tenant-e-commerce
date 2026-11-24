<?php

namespace Tests\Feature\Payments;

use App\Enums\TeamRole;
use App\Jobs\SendOrderPaidEmail;
use App\Models\InventoryReservation;
use App\Models\NotificationDelivery;
use App\Models\Order;
use App\Models\PaymentTransaction;
use App\Models\Team;
use App\Models\User;
use App\Services\Payments\PaymentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class PaymentReconciliationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'payments.enabled' => ['midtrans'],
            'payments.default' => 'midtrans',
            'payments.gateways.midtrans.server_key' => 'SB-server-key',
            'payments.gateways.midtrans.production' => false,
            'payments.gateways.midtrans.signature_secret_key' => 'test-signature-secret',
        ]);
    }

    public function test_it_reconciles_a_successful_payment_and_consumes_inventory_once(): void
    {
        Queue::fake();
        [$transaction, $order, $inventory] = $this->pendingPayment();
        Http::fake([
            'https://api.sandbox.midtrans.com/v2/*/status' => Http::response($this->statusPayload($transaction, 'settlement')),
        ]);

        $reconciled = app(PaymentService::class)->reconcilePendingTransactions();

        $this->assertSame(1, $reconciled);
        Queue::assertPushed(SendOrderPaidEmail::class, 1);
        $this->assertDatabaseHas('payment_transactions', ['id' => $transaction->id, 'status' => 'paid']);
        $this->assertDatabaseHas('orders', ['id' => $order->id, 'payment_status' => 'paid', 'status' => 'processing']);
        $this->assertDatabaseHas('inventories', [
            'id' => $inventory->id,
            'quantity' => 3,
            'reserved_quantity' => 0,
        ]);
        $this->assertDatabaseHas('inventory_reservations', [
            'order_id' => $order->id,
            'status' => 'consumed',
            'quantity' => 2,
        ]);

        $this->assertSame(0, app(PaymentService::class)->reconcilePendingTransactions());
        Http::assertSentCount(1);
    }

    public function test_it_processes_duplicate_successful_webhooks_without_consuming_inventory_twice(): void
    {
        Queue::fake();
        [$transaction, $order, $inventory] = $this->pendingPayment();
        $payload = [
            'order_id' => $transaction->gateway_order_id,
            'transaction_id' => 'midtrans-webhook-transaction-'.$transaction->id,
            'transaction_status' => 'settlement',
            'status_code' => '200',
            'gross_amount' => '115000.00',
            'currency' => 'IDR',
            'payment_type' => 'bank_transfer',
        ];
        $payload['signature_key'] = hash(
            'sha512',
            $payload['order_id'].$payload['status_code'].$payload['gross_amount'].'test-signature-secret',
        );

        $this->postJson(route('payments.midtrans.notification'), $payload)
            ->assertOk()
            ->assertExactJson(['success' => true]);

        $this->postJson(route('payments.midtrans.notification'), $payload)
            ->assertOk()
            ->assertExactJson(['success' => true]);

        $transaction->refresh();
        $order->refresh();
        $inventory->refresh();

        $this->assertSame('paid', $transaction->status);
        $this->assertSame('paid', $order->payment_status);
        $this->assertSame('processing', $order->status);
        $this->assertSame(3, $inventory->quantity);
        $this->assertSame(0, $inventory->reserved_quantity);
        $this->assertDatabaseHas('inventory_reservations', [
            'order_id' => $order->id,
            'status' => 'consumed',
            'quantity' => 2,
        ]);
        $this->assertSame(1, $order->statusHistories()->count());
        Queue::assertPushed(SendOrderPaidEmail::class, 1);
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

    public function test_notification_dispatch_failure_does_not_fail_payment_reconciliation(): void
    {
        config([
            'queue.default' => 'sync',
            'services.fonnte.token' => null,
        ]);
        [$transaction, $order] = $this->pendingPayment();
        Http::fake([
            'https://api.sandbox.midtrans.com/v2/*/status' => Http::response($this->statusPayload($transaction, 'settlement')),
        ]);

        $this->assertSame(1, app(PaymentService::class)->reconcilePendingTransactions());

        $this->assertSame('paid', $transaction->fresh()->status);
        $this->assertDatabaseHas('notification_deliveries', [
            'order_id' => $order->id,
            'channel' => 'whatsapp',
            'status' => 'failed',
        ]);
    }

    public function test_duplicate_paid_webhook_retries_notification_dispatch_after_recoverable_failure(): void
    {
        config(['queue.default' => 'sync', 'services.fonnte.token' => null]);
        [$transaction] = $this->pendingPayment();
        $payload = [
            'order_id' => $transaction->gateway_order_id,
            'transaction_id' => 'midtrans-retry-notification-'.$transaction->id,
            'transaction_status' => 'settlement',
            'status_code' => '200',
            'gross_amount' => '115000.00',
            'currency' => 'IDR',
            'payment_type' => 'bank_transfer',
        ];
        $payload['signature_key'] = hash(
            'sha512',
            $payload['order_id'].$payload['status_code'].$payload['gross_amount'].'test-signature-secret',
        );

        $this->postJson(route('payments.midtrans.notification'), $payload)->assertOk();
        $whatsAppDelivery = NotificationDelivery::query()
            ->where('order_id', $transaction->order_id)
            ->where('channel', 'whatsapp')
            ->firstOrFail();
        $this->assertSame('failed', $whatsAppDelivery->status);
        $this->assertNotNull(data_get($whatsAppDelivery->metadata, 'job_failure'));

        config(['services.fonnte.token' => 'test-fonnte-token']);
        Http::fake([
            'api.fonnte.com/send' => Http::response([
                'status' => true,
                'id' => ['retry-message-id'],
                'process' => 'pending',
            ]),
        ]);

        $this->postJson(route('payments.midtrans.notification'), $payload)->assertOk();

        $this->assertSame('sent', $whatsAppDelivery->fresh()->status);
        $this->assertSame('retry-message-id', $whatsAppDelivery->fresh()->provider_message_id);
    }

    public function test_it_records_successful_payment_even_when_inventory_requires_manual_review(): void
    {
        Queue::fake();
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
        Queue::fake();
        [$transaction, $order] = $this->pendingPayment();
        $order->update(['status' => 'cancelled']);
        InventoryReservation::query()->where('order_id', $order->id)->update([
            'status' => 'released',
            'released_at' => now(),
        ]);
        $order->items()->first()->variant->inventory()->update(['reserved_quantity' => 0]);
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

    public function test_it_returns_a_retryable_response_for_unknown_webhook_transactions(): void
    {
        $payload = [
            'order_id' => 'MIDTRANS-UNKNOWN-ORDER',
            'status_code' => '200',
            'gross_amount' => '100000.00',
            'transaction_status' => 'settlement',
        ];
        $payload['signature_key'] = hash(
            'sha512',
            $payload['order_id'].$payload['status_code'].$payload['gross_amount'].'test-signature-secret',
        );

        $this->postJson(route('payments.midtrans.notification'), $payload)
            ->assertStatus(503)
            ->assertJsonPath('success', false);
    }

    public function test_it_releases_order_reservations_when_snap_session_creation_fails(): void
    {
        [$transaction, $order, $inventory] = $this->pendingPayment();
        $transaction->delete();
        Http::fake([
            'https://app.sandbox.midtrans.com/snap/v1/transactions' => Http::response([
                'error_messages' => ['Invalid Snap request'],
            ], 400),
        ]);

        try {
            app(PaymentService::class)->start($order);
            $this->fail('Payment session creation should fail.');
        } catch (\RuntimeException $exception) {
            $this->assertStringContainsString('Midtrans gagal membuat sesi pembayaran', $exception->getMessage());
        }

        $inventory->refresh();
        $order->refresh();
        $reservation = InventoryReservation::query()->where('order_id', $order->id)->firstOrFail();

        $this->assertSame('failed', $order->payment_status);
        $this->assertSame('cancelled', $order->status);
        $this->assertSame(0, $inventory->reserved_quantity);
        $this->assertSame('released', $reservation->status);
        $this->assertNotNull($reservation->released_at);
        $this->assertDatabaseHas('payment_transactions', [
            'order_id' => $order->id,
            'status' => 'failed',
        ]);
    }

    public function test_it_keeps_reservations_when_snap_creation_result_is_uncertain(): void
    {
        [$transaction, $order, $inventory] = $this->pendingPayment();
        $transaction->delete();
        Http::fake([
            'https://app.sandbox.midtrans.com/snap/v1/transactions' => fn () => throw new ConnectionException('Connection timed out'),
        ]);

        try {
            app(PaymentService::class)->start($order);
            $this->fail('The simulated connection failure should be rethrown.');
        } catch (ConnectionException) {
            $this->assertTrue(true);
        }

        $order->refresh();
        $inventory->refresh();
        $createdTransaction = PaymentTransaction::query()->where('order_id', $order->id)->firstOrFail();
        $reservation = InventoryReservation::query()->where('order_id', $order->id)->firstOrFail();

        $this->assertSame('pending', $createdTransaction->status);
        $this->assertNotNull(data_get($createdTransaction->metadata, 'session_creation_uncertain'));
        $this->assertSame('pending', $order->payment_status);
        $this->assertSame('pending', $order->status);
        $this->assertSame(2, $inventory->reserved_quantity);
        $this->assertSame('reserved', $reservation->status);
    }

    public function test_it_releases_reservations_after_three_not_found_checks_for_an_unconfirmed_session(): void
    {
        [$transaction, $order, $inventory] = $this->pendingPayment();
        $transaction->update(['redirect_url' => null]);
        DB::table('payment_transactions')->where('id', $transaction->id)->update([
            'updated_at' => now()->subMinutes(10),
        ]);
        Http::fake([
            'https://api.sandbox.midtrans.com/v2/*/status' => Http::response([
                'status_code' => '404',
                'status_message' => 'The requested resource is not found',
            ], 404),
        ]);

        for ($attempt = 1; $attempt <= 3; $attempt++) {
            $this->assertSame(0, app(PaymentService::class)->reconcilePendingTransactions());
            if ($attempt < 3) {
                DB::table('payment_transactions')->where('id', $transaction->id)->update([
                    'updated_at' => now()->subMinutes(10),
                ]);
            }
        }

        $transaction->refresh();
        $order->refresh();
        $inventory->refresh();
        $reservation = InventoryReservation::query()->where('order_id', $order->id)->firstOrFail();

        $this->assertSame('failed', $transaction->status);
        $this->assertSame(3, data_get($transaction->metadata, 'reconciliation.not_found_attempts'));
        $this->assertSame('failed', $order->payment_status);
        $this->assertSame('cancelled', $order->status);
        $this->assertSame(0, $inventory->reserved_quantity);
        $this->assertSame('released', $reservation->status);
    }

    public function test_it_rejects_a_status_response_with_a_different_currency(): void
    {
        [$transaction, $order] = $this->pendingPayment();
        $payload = $this->statusPayload($transaction, 'settlement');
        $payload['currency'] = 'USD';
        Http::fake([
            'https://api.sandbox.midtrans.com/v2/*/status' => Http::response($payload),
        ]);

        $this->assertSame(0, app(PaymentService::class)->reconcilePendingTransactions());

        $transaction->refresh();
        $this->assertSame('pending', $transaction->status);
        $this->assertSame('pending', $order->fresh()->payment_status);
        $this->assertSame('error', data_get($transaction->metadata, 'reconciliation.result'));
    }

    public function test_it_releases_reservations_when_midtrans_reports_expired(): void
    {
        [$transaction, $order, $inventory] = $this->pendingPayment();
        Http::fake([
            'https://api.sandbox.midtrans.com/v2/*/status' => Http::response($this->statusPayload($transaction, 'expire')),
        ]);

        $this->assertSame(1, app(PaymentService::class)->reconcilePendingTransactions());

        $transaction->refresh();
        $order->refresh();
        $inventory->refresh();
        $reservation = InventoryReservation::query()->where('order_id', $order->id)->firstOrFail();

        $this->assertSame('expired', $transaction->status);
        $this->assertSame('expired', $order->payment_status);
        $this->assertSame('cancelled', $order->status);
        $this->assertSame(5, $inventory->quantity);
        $this->assertSame(0, $inventory->reserved_quantity);
        $this->assertSame('released', $reservation->status);
        $this->assertNotNull($reservation->released_at);
    }

    public function test_it_rejects_successful_notifications_with_invalid_status_codes(): void
    {
        Queue::fake();
        [$transaction, $order, $inventory] = $this->pendingPayment();
        $payload = [
            'order_id' => $transaction->gateway_order_id,
            'transaction_id' => 'midtrans-invalid-status-code-'.$transaction->id,
            'transaction_status' => 'settlement',
            'status_code' => '0200',
            'gross_amount' => '115000.00',
            'currency' => 'IDR',
            'payment_type' => 'bank_transfer',
        ];
        $payload['signature_key'] = hash(
            'sha512',
            $payload['order_id'].$payload['status_code'].$payload['gross_amount'].'test-signature-secret',
        );

        $this->postJson(route('payments.midtrans.notification'), $payload)
            ->assertBadRequest()
            ->assertJsonPath('success', false);

        $this->assertSame('pending', $transaction->fresh()->status);
        $this->assertSame('pending', $order->fresh()->payment_status);
        $this->assertSame(5, $inventory->fresh()->quantity);
        $this->assertSame(2, $inventory->fresh()->reserved_quantity);
        Queue::assertNothingPushed();
    }

    public function test_it_rejects_success_statuses_without_status_code_200(): void
    {
        Queue::fake();
        [$transaction, $order] = $this->pendingPayment();
        $payload = [
            'order_id' => $transaction->gateway_order_id,
            'transaction_id' => 'midtrans-wrong-success-code-'.$transaction->id,
            'transaction_status' => 'settlement',
            'status_code' => '201',
            'gross_amount' => '115000.00',
            'currency' => 'IDR',
            'payment_type' => 'bank_transfer',
        ];
        $payload['signature_key'] = hash(
            'sha512',
            $payload['order_id'].$payload['status_code'].$payload['gross_amount'].'test-signature-secret',
        );

        $this->postJson(route('payments.midtrans.notification'), $payload)
            ->assertBadRequest()
            ->assertJsonPath('success', false);

        $this->assertSame('pending', $transaction->fresh()->status);
        $this->assertSame('pending', $order->fresh()->payment_status);
        Queue::assertNothingPushed();
    }

    public function test_out_of_order_pending_notification_cannot_downgrade_a_paid_transaction(): void
    {
        Queue::fake();
        [$transaction, $order, $inventory] = $this->pendingPayment();
        $payload = [
            'order_id' => $transaction->gateway_order_id,
            'transaction_id' => 'midtrans-ordering-'.$transaction->id,
            'transaction_status' => 'settlement',
            'status_code' => '200',
            'gross_amount' => '115000.00',
            'currency' => 'IDR',
            'payment_type' => 'bank_transfer',
        ];
        $payload['signature_key'] = hash(
            'sha512',
            $payload['order_id'].$payload['status_code'].$payload['gross_amount'].'test-signature-secret',
        );

        $this->postJson(route('payments.midtrans.notification'), $payload)->assertOk();

        $payload['transaction_status'] = 'pending';
        $payload['status_code'] = '201';
        $payload['signature_key'] = hash(
            'sha512',
            $payload['order_id'].$payload['status_code'].$payload['gross_amount'].'test-signature-secret',
        );

        $this->postJson(route('payments.midtrans.notification'), $payload)->assertOk();

        $this->assertSame('paid', $transaction->fresh()->status);
        $this->assertSame('paid', $order->fresh()->payment_status);
        $this->assertSame('processing', $order->fresh()->status);
        $this->assertSame(3, $inventory->fresh()->quantity);
        $this->assertSame(0, $inventory->fresh()->reserved_quantity);
        $this->assertSame(1, $order->statusHistories()->count());
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

        InventoryReservation::query()->create([
            'store_id' => $store->id,
            'order_id' => $order->id,
            'inventory_id' => $inventory->id,
            'product_variant_id' => $variant->id,
            'quantity' => 2,
            'status' => 'reserved',
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
            'status_code' => in_array($status, ['settlement', 'capture'], true) ? '200' : (in_array($status, ['pending', 'authorize'], true) ? '201' : '202'),
            'order_id' => $transaction->gateway_order_id,
            'transaction_id' => 'midtrans-transaction-'.$transaction->id,
            'transaction_status' => $status,
            'fraud_status' => $status === 'capture' ? 'accept' : null,
            'gross_amount' => '115000.00',
            'currency' => 'IDR',
        ];
    }
}
