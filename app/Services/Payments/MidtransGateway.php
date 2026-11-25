<?php

namespace App\Services\Payments;

use App\Contracts\Payments\PaymentGateway;
use App\Exceptions\Payments\PaymentGatewayUnavailableException;
use App\Models\Order;
use App\Models\PaymentTransaction;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class MidtransGateway implements PaymentGateway
{
    /** @return array{token: string, redirect_url: string, response: array<string, mixed>} */
    public function createPayment(Order $order, PaymentTransaction $transaction): array
    {
        $serverKey = (string) config('payments.gateways.midtrans.server_key');

        if ($serverKey === '') {
            throw new RuntimeException('The Midtrans Server Key is not configured.');
        }

        if ($order->currency !== 'IDR') {
            throw new RuntimeException('Midtrans is currently enabled only for orders denominated in IDR.');
        }

        $baseUrl = config('payments.gateways.midtrans.production')
            ? 'https://app.midtrans.com'
            : 'https://app.sandbox.midtrans.com';

        if ($transaction->currency !== $order->currency) {
            throw new RuntimeException('The transaction currency does not match the order currency.');
        }

        $amount = (string) $transaction->amount;
        if (! preg_match('/^\\d+(?:\\.00)?$/', $amount) || (int) explode('.', $amount)[0] < 1) {
            throw new RuntimeException('The Midtrans transaction amount must be a valid whole-number IDR amount.');
        }

        $parameters = [
            'transaction_details' => [
                'order_id' => $transaction->gateway_order_id,
                'gross_amount' => (int) explode('.', $amount)[0],
            ],
            'customer_details' => [
                'first_name' => $order->customer_name,
                'email' => $order->customer_email,
                'phone' => $order->customer_phone,
            ],
        ];
        $appUrl = rtrim((string) config('app.url'), '/');
        $appUrlParts = parse_url($appUrl);
        if (! is_array($appUrlParts) || ! in_array($appUrlParts['scheme'] ?? null, ['http', 'https'], true)
            || empty($appUrlParts['host'])) {
            throw new RuntimeException('APP_URL must be a valid HTTP(S) URL for the Midtrans Finish URL.');
        }

        $appKey = (string) config('app.key');
        if ($appKey === '') {
            throw new RuntimeException('APP_KEY is required to secure the Midtrans Finish URL.');
        }

        $expires = now()->addDays(7)->timestamp;
        $finishKey = hash_hmac('sha256', $order->order_number.'|'.$expires, $appKey);
        $parameters['callbacks'] = [
            'finish' => $appUrl.'/orders/'.rawurlencode($order->order_number).'?expires='.$expires.'&key='.$finishKey,
        ];

        $response = Http::acceptJson()
            ->asJson()
            ->withBasicAuth($serverKey, '')
            ->timeout(20)
            ->post($baseUrl.'/snap/v1/transactions', $parameters);

        if ($response->status() >= 500 || in_array($response->status(), [408, 429], true)) {
            throw new PaymentGatewayUnavailableException('Midtrans could not confirm the payment session creation result.');
        }

        if ($response->successful() === false) {
            throw new RuntimeException('Midtrans failed to create the payment session: '.($response->json('error_messages.0') ?? $response->body()));
        }

        if (empty($response->json('token')) || empty($response->json('redirect_url'))) {
            throw new PaymentGatewayUnavailableException('The Midtrans session response is incomplete; the transaction must be reconciled.');
        }

        return [
            'token' => (string) $response->json('token'),
            'redirect_url' => (string) $response->json('redirect_url'),
            'response' => (array) $response->json(),
        ];
    }

    /** @param array<string, mixed> $payload */
    public function verifyNotification(array $payload): bool
    {
        $secret = (string) config('payments.gateways.midtrans.signature_secret_key');
        if ($secret === '') {
            return false;
        }

        $expected = hash('sha512',
            (string) ($payload['order_id'] ?? '').
            (string) ($payload['status_code'] ?? '').
            (string) ($payload['gross_amount'] ?? '').
            $secret
        );

        return isset($payload['signature_key']) && hash_equals($expected, (string) $payload['signature_key']);
    }

    /** @param array<string, mixed> $payload */
    public function normalizeStatus(array $payload): string
    {
        $status = (string) ($payload['transaction_status'] ?? '');
        $fraud = (string) ($payload['fraud_status'] ?? '');

        if ($status === 'settlement') {
            return 'paid';
        }

        if ($status === 'capture') {
            return match ($fraud) {
                'accept' => 'paid',
                'deny' => 'failed',
                'challenge' => 'pending',
                default => throw new RuntimeException('The Midtrans notification fraud status is unsupported.'),
            };
        }

        return match ($status) {
            'expire' => 'expired',
            'cancel', 'deny', 'failure' => 'failed',
            'pending', 'authorize' => 'pending',
            default => throw new RuntimeException('Unsupported Midtrans transaction status: '.$status),
        };
    }

    /** @return array<string, mixed>|null */
    public function getStatus(string $gatewayOrderId): ?array
    {
        $serverKey = (string) config('payments.gateways.midtrans.server_key');

        if ($serverKey === '') {
            throw new RuntimeException('The Midtrans Server Key is not configured.');
        }

        $baseUrl = config('payments.gateways.midtrans.production')
            ? 'https://api.midtrans.com'
            : 'https://api.sandbox.midtrans.com';

        $response = Http::acceptJson()
            ->withBasicAuth($serverKey, '')
            ->timeout(10)
            ->get($baseUrl.'/v2/'.rawurlencode($gatewayOrderId).'/status');

        if ($response->status() === 404) {
            return null;
        }

        if ($response->successful() === false) {
            throw new RuntimeException('Midtrans GET Status failed with HTTP '.$response->status().'.');
        }

        $payload = $response->json();

        if (! is_array($payload)) {
            throw new RuntimeException('The Midtrans GET Status response is invalid.');
        }

        return $payload;
    }
}
