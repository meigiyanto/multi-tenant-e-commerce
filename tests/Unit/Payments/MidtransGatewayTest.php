<?php

namespace Tests\Unit\Payments;

use App\Models\Order;
use App\Models\PaymentTransaction;
use App\Services\Payments\MidtransGateway;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class MidtransGatewayTest extends TestCase
{
    public function test_it_creates_a_snap_payment_session(): void
    {
        config([
            'payments.gateways.midtrans.server_key' => 'SB-server-key',
            'payments.gateways.midtrans.production' => false,
        ]);
        Http::fake([
            'https://app.sandbox.midtrans.com/snap/v1/transactions' => Http::response([
                'token' => 'snap-token',
                'redirect_url' => 'https://app.sandbox.midtrans.com/snap/v2/vtweb/snap-token',
            ]),
        ]);

        $order = new Order([
            'order_number' => 'ORD-20261121-ABC12345',
            'total' => '265000.00',
            'currency' => 'IDR',
            'customer_name' => 'Demo Customer',
            'customer_email' => 'customer@example.com',
            'customer_phone' => '+628123456789',
        ]);
        $transaction = new PaymentTransaction(['gateway_order_id' => 'ORD-20261121-ABC12345-XYZ12345']);

        $result = app(MidtransGateway::class)->createPayment($order, $transaction);

        $this->assertSame('snap-token', $result['token']);
        $this->assertSame('https://app.sandbox.midtrans.com/snap/v2/vtweb/snap-token', $result['redirect_url']);
        Http::assertSent(fn ($request): bool => $request->url() === 'https://app.sandbox.midtrans.com/snap/v1/transactions'
            && $request->hasHeader('Authorization')
            && $request['transaction_details']['order_id'] === 'ORD-20261121-ABC12345-XYZ12345'
            && $request['transaction_details']['gross_amount'] === 265000);
    }

    public function test_it_verifies_notification_with_signature_secret_key(): void
    {
        config(['payments.gateways.midtrans.signature_secret_key' => 'signature-secret']);
        $payload = [
            'order_id' => 'ORDER-123',
            'status_code' => '200',
            'gross_amount' => '100000.00',
        ];
        $payload['signature_key'] = hash('sha512', 'ORDER-123200100000.00signature-secret');

        $this->assertTrue(app(MidtransGateway::class)->verifyNotification($payload));
        $payload['gross_amount'] = '1.00';
        $this->assertFalse(app(MidtransGateway::class)->verifyNotification($payload));
    }

    public function test_it_maps_midtrans_transaction_statuses(): void
    {
        $gateway = app(MidtransGateway::class);

        $this->assertSame('paid', $gateway->normalizeStatus(['transaction_status' => 'settlement']));
        $this->assertSame('paid', $gateway->normalizeStatus(['transaction_status' => 'capture', 'fraud_status' => 'accept']));
        $this->assertSame('pending', $gateway->normalizeStatus(['transaction_status' => 'pending']));
        $this->assertSame('expired', $gateway->normalizeStatus(['transaction_status' => 'expire']));
        $this->assertSame('failed', $gateway->normalizeStatus(['transaction_status' => 'deny']));
    }

    public function test_it_marks_capture_with_denied_fraud_status_as_failed(): void
    {
        $this->assertSame('failed', app(MidtransGateway::class)->normalizeStatus([
            'transaction_status' => 'capture',
            'fraud_status' => 'deny',
        ]));
    }

    public function test_it_keeps_capture_with_challenge_fraud_status_pending(): void
    {
        $this->assertSame('pending', app(MidtransGateway::class)->normalizeStatus([
            'transaction_status' => 'capture',
            'fraud_status' => 'challenge',
        ]));
    }

    public function test_it_rejects_capture_without_a_supported_fraud_status(): void
    {
        $this->expectException(\\RuntimeException::class);
        app(MidtransGateway::class)->normalizeStatus(['transaction_status' => 'capture']);
    }

    public function test_it_rejects_unknown_transaction_statuses(): void
    {
        $this->expectException(\\RuntimeException::class);
        app(MidtransGateway::class)->normalizeStatus(['transaction_status' => 'refunded']);
    }
}