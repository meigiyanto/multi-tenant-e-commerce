<?php

namespace App\Services\Payments;

use App\Models\Order;
use App\Models\PaymentTransaction;
use App\Support\Payments\PaymentGatewayManager;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
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
        } catch (\Throwable $exception) {
            $transaction->update(['status' => 'failed', 'metadata' => ['error' => $exception->getMessage()]]);
            throw $exception;
        }

        return $transaction->refresh();
    }

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

            if (number_format((float) ($payload['gross_amount'] ?? 0), 2, '.', '') !== number_format((float) $transaction->amount, 2, '.', '')) {
                throw new RuntimeException('Nominal notifikasi pembayaran tidak sesuai dengan transaksi.');
            }

            if (in_array($transaction->status, ['paid', 'failed', 'expired'], true)) {
                return;
            }

            $status = $gateway->normalizeStatus($payload);
            $transaction->update([
                'status' => $status,
                'gateway_transaction_id' => $payload['transaction_id'] ?? $transaction->gateway_transaction_id,
                'metadata' => array_merge($transaction->metadata ?? [], ['last_notification' => $payload]),
                'paid_at' => $status === 'paid' ? now() : null,
            ]);

            $order = Order::query()->whereKey($transaction->order_id)->lockForUpdate()->firstOrFail();
            $order->update(['payment_status' => $status]);

            foreach ($order->items()->with('variant.inventory', 'product')->get() as $item) {
                if (! $item->product?->track_inventory) {
                    continue;
                }

                $inventory = $item->variant?->inventory;
                if ($inventory === null) {
                    continue;
                }

                $reservedToRelease = min((int) $inventory->reserved_quantity, (int) $item->quantity);
                if ($status === 'paid') {
                    $inventory->decrement('quantity', $item->quantity);
                    if ($reservedToRelease > 0) {
                        $inventory->decrement('reserved_quantity', $reservedToRelease);
                    }
                } elseif (in_array($status, ['failed', 'expired'], true) && $reservedToRelease > 0) {
                    $inventory->decrement('reserved_quantity', $reservedToRelease);
                }
            }

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
}
