<?php

namespace App\\Services\\Payments;

use App\\Models\\Order;
use App\\Models\\PaymentTransaction;
use App\\Support\\Payments\\PaymentGatewayManager;
use Illuminate\\Support\\Facades\\DB;
use Illuminate\\Support\\Str;
use RuntimeException;

class PaymentService
{
    public function __construct(private readonly PaymentGatewayManager $gateways) {}

    public function start(Order $order): PaymentTransaction
    {
        if ($order->payment_status === 'paid' || $order->status === 'cancelled') {
            throw new RuntimeException('Pesanan ini tidak dapat dibayar.');
        }

        if ($order->currency !== 'IDR') {
            throw new RuntimeException('Gateway Midtrans hanya menerima mata uang IDR.');
        }

        $gatewayName = (string) config('payments.default');
        $gateway = $this->gateways->driver($gatewayName);
        $existing = $order->paymentTransactions()
            ->where('gateway', $gatewayName)
            ->where('status', 'pending')
            ->whereNotNull('redirect_url')
            ->latest()
            ->first();

        if ($existing !== null) {
            return $existing;
        }

        $transaction = DB::transaction(function () use ($order, $gatewayName): PaymentTransaction {
            $lockedOrder = Order::query()->whereKey($order->id)->lockForUpdate()->firstOrFail();

            if ($lockedOrder->payment_status === 'paid' || $lockedOrder->status === 'cancelled') {
                throw new RuntimeException('Pesanan ini tidak dapat dibayar.');
            }

            $inProgress = $lockedOrder->paymentTransactions()
                ->where('gateway', $gatewayName)
                ->where('status', 'pending')
                ->whereNull('redirect_url')
                ->where('created_at', '>=', now()->subMinutes(5))
                ->exists();

            if ($inProgress) {
                throw new RuntimeException('Sesi pembayaran sedang dibuat. Silakan coba kembali beberapa saat lagi.');
            }

            $lockedOrder->update(['payment_status' => 'pending']);

            return $lockedOrder->paymentTransactions()->create([
                'store_id' => $lockedOrder->store_id,
                'gateway' => $gatewayName,
                'gateway_order_id' => $lockedOrder->order_number.'-'.Str::upper(Str::random(8)),
                'amount' => $lockedOrder->total,
                'currency' => $lockedOrder->currency,
                'status' => 'pending',
            ]);
        });

        try {
            $result = $gateway->createPayment($order, $transaction);
            $transaction->update([
                'token' => $result['token'] ?? null,
                'redirect_url' => $result['redirect_url'] ?? null,
                'metadata' => ['gateway_response' => $result['response'] ?? []],
            ]);
        } catch (\\Throwable $exception) {
            $transaction->update(['status' => 'failed', 'metadata' => ['error' => $exception->getMessage()]]);
            throw $exception;
        }

        return $transaction->refresh();
    }

    /** @param array<string, mixed> $payload */
    public function handleNotification(array $payload, string $gatewayName): void
    {
        $gateway = $this->gateways->driver($gatewayName, false);

        if (! $gateway->verifyNotification($payload)) {
            throw new RuntimeException('Signature notifikasi pembayaran tidak valid.');
        }

        DB::transaction(function () use ($payload, $gateway, $gatewayName): void {
            $transaction = PaymentTransaction::query()
                ->where('gateway', $gatewayName)
                ->where('gateway_order_id', $payload['order_id'] ?? '')
                ->lockForUpdate()
                ->firstOrFail();

            if ($this->normalizeAmount($payload['gross_amount'] ?? null) !== $this->normalizeAmount($transaction->amount)) {
                throw new RuntimeException('Nominal notifikasi pembayaran tidak sesuai dengan transaksi.');
            }

            $status = $gateway->normalizeStatus($payload);

            if (in_array($transaction->status, ['paid', 'failed', 'expired'], true)) {
                return;
            }

            $order = Order::query()->whereKey($transaction->order_id)->lockForUpdate()->firstOrFail();

            if ($order->status === 'cancelled' || ($order->payment_status === 'paid' && $status !== 'paid')) {
                throw new RuntimeException('Status pesanan tidak mengizinkan perubahan pembayaran ini.');
            }

            $this->applyInventoryTransition($order, $status);

            $transaction->update([
                'status' => $status,
                'gateway_transaction_id' => $payload['transaction_id'] ?? $transaction->gateway_transaction_id,
                'metadata' => array_merge((array) ($transaction->metadata ?? []), [
                    'last_notification' => $this->safeNotificationMetadata($payload),
                ]),
                'paid_at' => $status === 'paid' ? now() : null,
            ]);

            $order->update(['payment_status' => $status]);

            if ($status === 'paid' && $order->status === 'pending') {
                $order->update(['status' => 'processing']);
                $order->statusHistories()->create([
                    'store_id' => $order->store_id,
                    'from_status' => 'pending',
                    'to_status' => 'processing',
                    'note' => 'Payment confirmed by '.$gatewayName.'.',
                ]);
            } elseif (in_array($status, ['failed', 'expired'], true) && $order->status === 'pending') {
                $order->update(['status' => 'cancelled']);
                $order->statusHistories()->create([
                    'store_id' => $order->store_id,
                    'from_status' => 'pending',
                    'to_status' => 'cancelled',
                    'note' => 'Payment '.$status.'; inventory reservation released.',
                ]);
            }
        });
    }

    private function normalizeAmount(mixed $amount): string
    {
        if (! is_string($amount) && ! is_int($amount) && ! is_float($amount)) {
            throw new RuntimeException('Nominal notifikasi pembayaran tidak valid.');
        }

        $amount = (string) $amount;
        if (! preg_match('/^\\d{1,13}(?:\\.\\d{1,2})?$/', $amount)) {
            throw new RuntimeException('Nominal notifikasi pembayaran tidak valid.');
        }

        [$integer, $fraction] = array_pad(explode('.', $amount, 2), 2, '');
        $integer = ltrim($integer, '0') ?: '0';

        return $integer.'.'.str_pad($fraction, 2, '0');
    }

    /** @param array<string, mixed> $payload
     *  @return array<string, scalar|null>
     */
    private function safeNotificationMetadata(array $payload): array
    {
        $allowed = [
            'order_id', 'transaction_id', 'transaction_status', 'fraud_status',
            'status_code', 'gross_amount', 'currency', 'payment_type',
            'transaction_time', 'settlement_time', 'bank', 'card_type',
        ];

        return array_filter(
            array_intersect_key($payload, array_flip($allowed)),
            static fn (mixed $value): bool => is_scalar($value) || $value === null,
        );
    }

    private function applyInventoryTransition(Order $order, string $status): void
    {
        if (! in_array($status, ['paid', 'failed', 'expired'], true)) {
            return;
        }

        $quantities = $order->items()
            ->where('track_inventory', true)
            ->whereNotNull('product_variant_id')
            ->get()
            ->groupBy('product_variant_id')
            ->map(fn ($items): int => (int) $items->sum('quantity'));

        foreach ($quantities as $variantId => $quantity) {
            $inventory = \\App\\Models\\Inventory::query()
                ->where('store_id', $order->store_id)
                ->where('product_variant_id', $variantId)
                ->lockForUpdate()
                ->first();

            if ($inventory === null || $inventory->reserved_quantity < $quantity) {
                throw new RuntimeException('Reservasi inventori tidak tersedia atau tidak konsisten untuk pesanan ini.');
            }

            if ($status === 'paid' && $inventory->quantity < $quantity) {
                throw new RuntimeException('Stok inventori tidak mencukupi untuk menyelesaikan pembayaran.');
            }

            if ($status === 'paid') {
                $inventory->decrement('quantity', $quantity);
            }

            $inventory->decrement('reserved_quantity', $quantity);
        }
    }
}
