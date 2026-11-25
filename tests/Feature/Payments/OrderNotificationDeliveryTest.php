<?php

namespace Tests\Feature\Payments;

use App\Enums\TeamRole;
use App\Jobs\SendOrderPaidEmail;
use App\Jobs\SendOrderPaidWhatsApp;
use App\Models\NotificationDelivery;
use App\Models\Order;
use App\Models\Team;
use App\Models\User;
use App\Services\Notifications\OrderNotificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Tests\TestCase;

class OrderNotificationDeliveryTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_queues_only_one_payment_confirmation_per_channel_and_order(): void
    {
        Queue::fake();
        $order = $this->order();

        $first = app(OrderNotificationService::class)->queuePaymentConfirmed($order->id);
        $second = app(OrderNotificationService::class)->queuePaymentConfirmed($order->id);

        $this->assertNotNull($first);
        $this->assertSame($first->id, $second?->id);
        $this->assertDatabaseCount('notification_deliveries', 2);
        Queue::assertPushed(SendOrderPaidEmail::class, 1);
        Queue::assertPushed(SendOrderPaidWhatsApp::class, 1);
    }

    public function test_it_sends_payment_confirmation_through_fonnte_and_stores_message_id(): void
    {
        $order = $this->order();
        $delivery = NotificationDelivery::query()->create([
            'uuid' => (string) Str::uuid(),
            'store_id' => $order->store_id,
            'order_id' => $order->id,
            'event' => 'payment_confirmed',
            'channel' => 'whatsapp',
            'recipient' => '628123456789',
            'status' => 'queued',
            'provider' => 'fonnte',
            'queued_at' => now(),
        ]);
        config(['services.fonnte.token' => 'test-fonnte-token']);
        Http::fake([
            'api.fonnte.com/send' => Http::response([
                'status' => true,
                'id' => ['fonnte-message-123'],
                'process' => 'pending',
            ]),
        ]);

        (new SendOrderPaidWhatsApp($delivery->id))->handle(app(\App\Services\Orders\OrderTrackingUrl::class));

        $this->assertSame('sent', $delivery->fresh()->status);
        $this->assertSame('fonnte', $delivery->fresh()->provider);
        $this->assertSame('fonnte-message-123', $delivery->fresh()->provider_message_id);
        Http::assertSent(fn ($request): bool => $request->url() === 'https://api.fonnte.com/send'
            && $request->hasHeader('Authorization', 'test-fonnte-token')
            && $request['target'] === '628123456789');
    }

    public function test_it_marks_email_sent_after_mail_transport_accepts_it(): void
    {
        $order = $this->order();
        $delivery = NotificationDelivery::query()->create([
            'uuid' => (string) Str::uuid(),
            'store_id' => $order->store_id,
            'order_id' => $order->id,
            'event' => 'payment_confirmed',
            'channel' => 'mail',
            'recipient' => $order->customer_email,
            'status' => 'queued',
            'queued_at' => now(),
        ]);
        Mail::shouldReceive('raw')->once();

        (new SendOrderPaidEmail($delivery->id))->handle(app(\App\Services\Orders\OrderTrackingUrl::class));

        $this->assertSame('sent', $delivery->fresh()->status);
        $this->assertNotNull($delivery->fresh()->sent_at);
    }

    public function test_it_accepts_signed_delivery_webhook_and_records_delivered_status(): void
    {
        $delivery = NotificationDelivery::query()->create([
            'uuid' => (string) Str::uuid(),
            'store_id' => $this->order()->store_id,
            'order_id' => Order::query()->firstOrFail()->id,
            'event' => 'payment_confirmed',
            'channel' => 'mail',
            'recipient' => 'customer@example.com',
            'status' => 'sent',
            'sent_at' => now(),
        ]);
        $payload = ['status' => 'delivered', 'provider_message_id' => 'provider-message-123'];
        $body = json_encode($payload, JSON_THROW_ON_ERROR);
        $signature = hash_hmac('sha256', $body, 'delivery-test-secret');
        config(['services.notification_delivery_webhook.secret' => 'delivery-test-secret']);

        $this->call('POST', '/webhooks/notification-deliveries/'.$delivery->uuid, [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_DELIVERY_SIGNATURE' => $signature,
        ], $body)->assertOk()->assertJson(['success' => true]);

        $this->assertSame('delivered', $delivery->fresh()->status);
        $this->assertSame('provider-message-123', $delivery->fresh()->provider_message_id);
        $this->assertNotNull($delivery->fresh()->delivered_at);
    }

    public function test_it_rejects_unsigned_delivery_webhook(): void
    {
        config(['services.notification_delivery_webhook.secret' => 'delivery-test-secret']);

        $this->postJson('/webhooks/notification-deliveries/unknown', ['status' => 'delivered'])
            ->assertUnauthorized();
    }

    public function test_fonnte_webhook_records_delivered_then_read_without_downgrading_status(): void
    {
        $delivery = $this->whatsappDelivery('fonnte-message-delivered');
        config(['services.fonnte.webhook_secret' => 'fonnte-test-secret']);

        $this->postJson('/webhooks/fonnte/fonnte-test-secret', [
            'id' => 'fonnte-message-delivered',
            'status' => 'Sent',
            'state' => 'delivered',
            'timestamp' => '2026-11-23T12:00:00+07:00',
        ])->assertOk()->assertJson(['success' => true, 'updated' => true]);

        $this->assertSame('delivered', $delivery->fresh()->status);
        $this->assertNotNull($delivery->fresh()->delivered_at);

        $this->postJson('/webhooks/fonnte/fonnte-test-secret', [
            'id' => 'fonnte-message-delivered',
            'status' => 'Sent',
            'state' => 'read',
        ])->assertOk()->assertJson(['success' => true, 'updated' => true]);

        $this->assertSame('read', $delivery->fresh()->status);
        $this->assertNotNull($delivery->fresh()->read_at);

        $this->postJson('/webhooks/fonnte/fonnte-test-secret', [
            'id' => 'fonnte-message-delivered',
            'status' => 'Sent',
            'state' => 'delivered',
        ])->assertOk()->assertJson(['success' => true, 'updated' => false]);

        $this->assertSame('read', $delivery->fresh()->status);
    }

    public function test_fonnte_zero_state_is_recorded_as_failed_not_delivered(): void
    {
        $delivery = $this->whatsappDelivery('fonnte-message-state-zero');
        config(['services.fonnte.webhook_secret' => 'fonnte-test-secret']);

        $this->postJson('/webhooks/fonnte/fonnte-test-secret', [
            'id' => 'fonnte-message-state-zero',
            'status' => 'Sent',
            'state' => '0',
            'timestamp' => 1795406400,
        ])->assertOk()->assertJson(['success' => true, 'updated' => true]);

        $this->assertSame('failed', $delivery->fresh()->status);
        $this->assertNull($delivery->fresh()->delivered_at);
        $this->assertNotNull($delivery->fresh()->failed_at);
        $this->assertSame('0', $delivery->fresh()->metadata['fonnte_webhook']['state']);
    }

    public function test_fonnte_failed_status_is_recorded_and_is_terminal(): void
    {
        $delivery = $this->whatsappDelivery('fonnte-message-failed');
        config(['services.fonnte.webhook_secret' => 'fonnte-test-secret']);

        $this->postJson('/webhooks/fonnte/fonnte-test-secret', [
            'id' => 'fonnte-message-failed',
            'status' => 'Failed',
            'state' => '0',
        ])->assertOk()->assertJson(['success' => true, 'updated' => true]);

        $this->assertSame('failed', $delivery->fresh()->status);
        $this->assertNotNull($delivery->fresh()->failed_at);
        $this->assertNotNull($delivery->fresh()->last_error);

        $this->postJson('/webhooks/fonnte/fonnte-test-secret', [
            'id' => 'fonnte-message-failed',
            'status' => 'Sent',
            'state' => 'delivered',
        ])->assertOk()->assertJson(['success' => true, 'updated' => false]);

        $this->assertSame('failed', $delivery->fresh()->status);
    }

    public function test_fonnte_webhook_ignores_unknown_message_id_and_rejects_wrong_secret(): void
    {
        $this->whatsappDelivery('fonnte-message-known');
        config(['services.fonnte.webhook_secret' => 'fonnte-test-secret']);

        $this->postJson('/webhooks/fonnte/wrong-secret', [
            'id' => 'fonnte-message-known',
            'status' => 'Sent',
            'state' => 'read',
        ])->assertUnauthorized();

        $this->postJson('/webhooks/fonnte/fonnte-test-secret', [
            'id' => 'fonnte-message-unknown',
            'status' => 'Sent',
            'state' => 'read',
        ])->assertOk()->assertJson(['success' => true, 'ignored' => true]);
    }

    public function test_it_does_not_send_a_whatsapp_delivery_already_claimed_by_another_worker(): void
    {
        $delivery = $this->whatsappDelivery('fonnte-message-in-progress');
        $delivery->update(['status' => 'sending', 'updated_at' => now()]);
        Http::fake();

        (new SendOrderPaidWhatsApp($delivery->id))->handle(app(\App\Services\Orders\OrderTrackingUrl::class));

        Http::assertNothingSent();
        $this->assertSame('sending', $delivery->fresh()->status);
    }

    public function test_it_records_fonnte_acceptance_without_message_id_as_sent_but_untrackable(): void
    {
        $delivery = $this->whatsappDelivery('fonnte-message-missing-id');
        $delivery->update(['status' => 'queued', 'provider_message_id' => null]);
        config(['services.fonnte.token' => 'test-fonnte-token']);
        Http::fake(['api.fonnte.com/send' => Http::response(['status' => true, 'process' => 'pending'])]);

        (new SendOrderPaidWhatsApp($delivery->id))->handle(app(\App\Services\Orders\OrderTrackingUrl::class));

        $this->assertSame('sent', $delivery->fresh()->status);
        $this->assertNull($delivery->fresh()->provider_message_id);
        $this->assertTrue(data_get($delivery->fresh()->metadata, 'fonnte.tracking_unavailable'));
    }

    public function test_generic_delivery_webhook_reports_duplicate_callback_idempotently(): void
    {
        $delivery = NotificationDelivery::query()->create([
            'uuid' => (string) Str::uuid(),
            'store_id' => $this->order()->store_id,
            'order_id' => Order::query()->firstOrFail()->id,
            'event' => 'payment_confirmed',
            'channel' => 'mail',
            'recipient' => 'customer@example.com',
            'status' => 'sent',
            'sent_at' => now(),
        ]);
        config(['services.notification_delivery_webhook.secret' => 'delivery-test-secret']);
        $payload = ['status' => 'delivered'];
        $body = json_encode($payload, JSON_THROW_ON_ERROR);
        $signature = hash_hmac('sha256', $body, 'delivery-test-secret');

        $send = fn () => $this->call('POST', '/webhooks/notification-deliveries/'.$delivery->uuid, [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_DELIVERY_SIGNATURE' => $signature,
        ], $body);

        $send()->assertOk()->assertJson(['success' => true, 'duplicate' => false]);
        $send()->assertOk()->assertJson(['success' => true, 'duplicate' => true]);
        $this->assertSame('delivered', $delivery->fresh()->status);
    }

    private function whatsappDelivery(string $messageId): NotificationDelivery
    {
        $order = $this->order();

        return NotificationDelivery::query()->create([
            'uuid' => (string) Str::uuid(),
            'store_id' => $order->store_id,
            'order_id' => $order->id,
            'event' => 'payment_confirmed',
            'channel' => 'whatsapp',
            'recipient' => '628123456789',
            'status' => 'sent',
            'provider' => 'fonnte',
            'provider_message_id' => $messageId,
            'queued_at' => now(),
            'sent_at' => now(),
        ]);
    }

    private function order(): Order
    {
        $user = User::factory()->create();
        $team = Team::factory()->create();
        $team->members()->attach($user, ['role' => TeamRole::Owner->value]);
        $store = $team->stores()->create([
            'name' => 'Notification Test Store',
            'slug' => 'notification-test-store-'.$team->id,
            'status' => 'active',
            'timezone' => 'Asia/Jakarta',
            'currency' => 'IDR',
        ]);

        return Order::query()->create([
            'store_id' => $store->id,
            'user_id' => $user->id,
            'order_number' => 'ORD-NOTIFY-'.$store->id,
            'status' => 'processing',
            'payment_status' => 'paid',
            'customer_name' => 'Notification Customer',
            'customer_email' => 'customer@example.com',
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
    }
}
