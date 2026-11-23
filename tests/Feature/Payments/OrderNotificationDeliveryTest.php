<?php

namespace Tests\Feature\Payments;

use App\Enums\TeamRole;
use App\Jobs\SendOrderPaidEmail;
use App\Models\NotificationDelivery;
use App\Models\Order;
use App\Models\Team;
use App\Models\User;
use App\Services\Notifications\OrderNotificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class OrderNotificationDeliveryTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_queues_only_one_payment_confirmation_email_per_order(): void
    {
        Queue::fake();
        $order = $this->order();

        $first = app(OrderNotificationService::class)->queuePaymentConfirmed($order->id);
        $second = app(OrderNotificationService::class)->queuePaymentConfirmed($order->id);

        $this->assertNotNull($first);
        $this->assertSame($first->id, $second?->id);
        $this->assertDatabaseCount('notification_deliveries', 1);
        Queue::assertPushed(SendOrderPaidEmail::class, 1);
    }

    public function test_it_marks_email_sent_after_mail_transport_accepts_it(): void
    {
        $order = $this->order();
        $delivery = NotificationDelivery::query()->create([
            'uuid' => (string) \Illuminate\Support\Str::uuid(),
            'store_id' => $order->store_id,
            'order_id' => $order->id,
            'event' => 'payment_confirmed',
            'channel' => 'mail',
            'recipient' => $order->customer_email,
            'status' => 'queued',
            'queued_at' => now(),
        ]);
        Mail::shouldReceive('raw')->once();

        (new SendOrderPaidEmail($delivery->id))->handle();

        $this->assertSame('sent', $delivery->fresh()->status);
        $this->assertNotNull($delivery->fresh()->sent_at);
    }

    public function test_it_accepts_signed_delivery_webhook_and_records_delivered_status(): void
    {
        $delivery = NotificationDelivery::query()->create([
            'uuid' => (string) \Illuminate\Support\Str::uuid(),
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
