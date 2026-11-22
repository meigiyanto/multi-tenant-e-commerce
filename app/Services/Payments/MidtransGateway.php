<?php

namespace App\Services\Payments;

use App\Contracts\Payments\PaymentGateway;
use App\Models\Order;
use App\Models\PaymentTransaction;
use App\Models\StoreDomain;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class MidtransGateway implements PaymentGateway
{
    /** @return array{token: string, redirect_url: string, response: array<string, mixed>} */
    public function createPayment(Order $order, PaymentTransaction $transaction): array
    {
        $serverKey = (string) config('payments.gateways.midtrans.server_key');

        if ($serverKey === '') {
            throw new RuntimeException('Midtrans Server Key belum dikonfigurasi.');
        }

        if ($order->currency !== 'IDR') {
            throw new RuntimeException('Midtrans saat ini hanya diaktifkan untuk pesanan berdenominasi IDR.');
        }

        $baseUrl = config('payments.gateways.midtrans.production')
            ? 'https://app.midtrans.com'
            : 'https://app.sandbox.midtrans.com';

        $parameters = [
            'transaction_details' => [
                'order_id' => $transaction->gateway_order_id,
                'gross_amount' => (int) round((float) $order->total),
            ],
            'customer_details' => [
                'first_name' => $order->customer_name,
                'email' => $order->customer_email,
                'phone' => $order->customer_phone,
            ],
        ];
        $domain = $order->store_id === null
            ? null
            : StoreDomain::query()->where('store_id', $order->store_id)->where('is_primary', true)->where('status', 'active')->value('domain');

        if ($domain) {
            $scheme = rtrim((string) config('storefront.scheme', 'https'), ':/');
            $port = parse_url((string) config('app.url'), PHP_URL_PORT);
            $defaultPort = ($scheme === 'https' && $port === 443) || ($scheme === 'http' && $port === 80);
            $host = $domain.($port && ! $defaultPort ? ':'.$port : '');

            $parameters['callbacks'] = [
                'finish' => $scheme.'://'.$host.'/orders/'.rawurlencode($order->order_number),
            ];
        }

        $response = Http::acceptJson()
            ->asJson()
            ->withBasicAuth($serverKey, '')
            ->timeout(20)
            ->post($baseUrl.'/snap/v1/transactions', $parameters);

        if ($response->successful() === false || empty($response->json('token')) || empty($response->json('redirect_url'))) {
            throw new RuntimeException('Midtrans gagal membuat sesi pembayaran: '.($response->json('error_messages.0') ?? $response->body()));
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
                default => throw new RuntimeException('Fraud status notifikasi Midtrans tidak didukung.'),
            };
        }

        return match ($status) {
            'expire' => 'expired',
            'cancel', 'deny' => 'failed',
            'pending', 'authorize' => 'pending',
            default => throw new RuntimeException('Status transaksi Midtrans tidak didukung: '.$status),
        };
    }
}
