<?php

namespace Tests\Feature\Storefront;

use App\Services\Payments\PaymentService;
use Mockery;
use RuntimeException;
use Tests\TestCase;

class PaymentControllerTest extends TestCase
{
    public function test_midtrans_notification_returns_success_when_service_processes_payload(): void
    {
        $payload = [
            'order_id' => 'ORD-TEST-123',
            'transaction_id' => 'midtrans-transaction-123',
            'transaction_status' => 'settlement',
            'status_code' => '200',
            'gross_amount' => '105000.00',
            'signature_key' => 'test-signature',
        ];

        $payments = Mockery::mock(PaymentService::class);
        $payments->shouldReceive('handleNotification')
            ->once()
            ->with($payload, 'midtrans');

        $this->app->instance(PaymentService::class, $payments);

        $this->postJson(route('payments.midtrans.notification'), $payload)
            ->assertOk()
            ->assertExactJson(['success' => true]);
    }

    public function test_midtrans_notification_returns_bad_request_when_service_rejects_payload(): void
    {
        $payload = [
            'order_id' => 'ORD-TEST-INVALID',
            'transaction_status' => 'settlement',
            'status_code' => '200',
            'gross_amount' => '105000.00',
        ];

        $payments = Mockery::mock(PaymentService::class);
        $payments->shouldReceive('handleNotification')
            ->once()
            ->with($payload, 'midtrans')
            ->andThrow(new RuntimeException('Signature notifikasi pembayaran tidak valid.'));

        $this->app->instance(PaymentService::class, $payments);

        $this->postJson(route('payments.midtrans.notification'), $payload)
            ->assertBadRequest()
            ->assertExactJson([
                'success' => false,
                'message' => 'Signature notifikasi pembayaran tidak valid.',
            ]);
    }

    public function test_midtrans_notification_passes_the_complete_payload_without_mutation(): void
    {
        $payload = [
            'order_id' => 'ORD-TEST-AMOUNT',
            'transaction_id' => 'midtrans-transaction-456',
            'transaction_status' => 'capture',
            'fraud_status' => 'accept',
            'status_code' => '200',
            'gross_amount' => '342000.00',
            'currency' => 'IDR',
            'signature_key' => 'another-test-signature',
        ];

        $payments = Mockery::mock(PaymentService::class);
        $payments->shouldReceive('handleNotification')
            ->once()
            ->with($payload, 'midtrans');

        $this->app->instance(PaymentService::class, $payments);

        $this->postJson(route('payments.midtrans.notification'), $payload)
            ->assertOk()
            ->assertExactJson(['success' => true]);
    }
}
