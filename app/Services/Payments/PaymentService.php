<?php

namespace App\Services\Payments;

use App\Contracts\Payments\PaymentGateway;
use App\Exceptions\Payments\PaymentGatewayUnavailableException;
use App\Models\Inventory;
use App\Models\InventoryReservation;
use App\Models\Order;
use App\Models\PaymentTransaction;
use App\Models\RefundRequest;
use App\Services\Notifications\OrderNotificationService;
use App\Notifications\Storefront\RefundRequestResult;
use App\Support\Payments\PaymentGatewayManager;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class PaymentService
{
    public function __construct(private readonly PaymentGatewayManager $gateways) {}

    public function start(Order $order): PaymentTransaction
    {
        if ($order->payment_status !== 'pending' || $order->status !== 'pending') {
            throw new RuntimeException('This order cannot be paid.');
        }

        if ($order->currency !== 'IDR') {
            throw new RuntimeException('The Midtrans gateway only accepts IDR transactions.');
        }

        $gatewayName = (string) config('payments.default');
        $gateway = $this->gateways->driver($gatewayName);
        $transaction = DB::transaction(function () use ($order, $gatewayName): PaymentTransaction {
            $lockedOrder = Order::query()->whereKey($order->id)->lockForUpdate()->firstOrFail();

            if ($lockedOrder->payment_status !== 'pending' || $lockedOrder->status !== 'pending') {
                throw new RuntimeException('This order cannot be paid.');
            }

            if ($lockedOrder->currency !== 'IDR') {
                throw new RuntimeException('The Midtrans gateway only accepts IDR transactions.');
            }

            $existing = $lockedOrder->paymentTransactions()
                ->where('gateway', $gatewayName)
                ->where('status', 'pending')
                ->whereNotNull('redirect_url')
                ->latest()
                ->first();

            if ($existing !== null) {
                return $existing;
            }

            $inProgress = $lockedOrder->paymentTransactions()
                ->where('gateway', $gatewayName)
                ->where('status', 'pending')
                ->whereNull('redirect_url')
                ->exists();

            if ($inProgress) {
                throw new RuntimeException('A payment session is being created. Please try again in a few moments.');
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

        if ($transaction->redirect_url !== null) {
            return $transaction;
        }

        try {
            $result = $gateway->createPayment($order, $transaction);
            $transaction->update([
                'token' => $result['token'],
                'redirect_url' => $result['redirect_url'],
                'metadata' => ['gateway_response' => $result['response']],
            ]);
        } catch (PaymentGatewayUnavailableException|ConnectionException $exception) {
            DB::transaction(function () use ($transaction, $exception): void {

                $lockedTransaction = PaymentTransaction::query()
                    ->whereKey($transaction->id)
                    ->lockForUpdate()
                    ->firstOrFail();

                $metadata = array_merge((array) ($lockedTransaction->metadata ?? []), [
                    'session_creation_uncertain' => [
                        'message' => $exception->getMessage(),
                        'occurred_at' => now()->toISOString(),
                    ],
                ]);

                $lockedTransaction->update(['metadata' => $metadata]);
            });

            throw $exception;
        } catch (Throwable $exception) {
            DB::transaction(function () use ($transaction, $exception): void {

                $lockedTransaction = PaymentTransaction::query()
                    ->whereKey($transaction->id)
                    ->lockForUpdate()
                    ->firstOrFail();

                $order = Order::query()
                    ->whereKey($lockedTransaction->order_id)
                    ->lockForUpdate()
                    ->firstOrFail();

                $inventoryError = $this->applyInventoryTransition($order, 'failed');

                $metadata = array_merge((array) ($lockedTransaction->metadata ?? []), [
                    'session_creation_error' => [
                        'message' => $exception->getMessage(),
                        'occurred_at' => now()->toISOString(),
                    ],
                ]);

                if ($inventoryError !== null) {
                    $metadata['inventory_review_required'] = $inventoryError;
                }

                $lockedTransaction->update(['status' => 'failed', 'metadata' => $metadata]);
                $order->update(['payment_status' => 'failed']);

                if ($order->status === 'pending') {
                    $order->update(['status' => 'cancelled']);
                    $order->statusHistories()->create([
                        'store_id' => $order->store_id,
                        'from_status' => 'pending',
                        'to_status' => 'cancelled',
                        'note' => 'Payment session creation failed; inventory reservation release attempted.',
                    ]);
                }

                if ($inventoryError !== null) {
                    Log::critical('Payment session failed and inventory requires manual review', [
                        'payment_transaction_id' => $lockedTransaction->id,
                        'order_id' => $order->order_number,
                        'reason' => $inventoryError,
                    ]);
                }
            });

            throw $exception;
        }

        return $transaction->refresh();
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function handleNotification(array $payload, string $gatewayName): void
    {
        $gateway = $this->gateways->driver($gatewayName, false);

        if ($gateway->verifyNotification($payload) === false) {
            throw new RuntimeException('The payment notification signature is invalid.');
        }

        if ($gatewayName === 'midtrans' && ($payload['transaction_status'] ?? null) === 'refund') {
            $this->applyVerifiedMidtransRefund($payload);

            return;
        }

        $this->assertValidStatusCode($payload);
        $this->applyVerifiedStatus($payload, $gateway, $gatewayName, true);
    }

    /**
     * A Midtrans refund notification is a refund event, not a payment-status
     * transition. Keep the original payment transaction marked as paid.
     *
     * @param  array<string, mixed>  $payload
     */
    private function applyVerifiedMidtransRefund(array $payload): void
    {
        if ((string) ($payload['status_code'] ?? '') !== '200') {
            throw new RuntimeException('The Midtrans refund notification status code is invalid.');
        }

        $refundAmount = $this->normalizeAmount($payload['refund_amount'] ?? null);
        $refunds = $payload['refunds'] ?? null;

        if (! is_array($refunds) || $refunds === []) {
            throw new RuntimeException('The Midtrans refund notification does not contain refund details.');
        }

        $refundTotal = '0.00';
        $refundDetail = null;

        foreach ($refunds as $refund) {
            if (! is_array($refund) || ! isset($refund['refund_amount'])) {
                throw new RuntimeException('The Midtrans refund detail is invalid.');
            }

            $amount = $this->normalizeAmount($refund['refund_amount']);
            [$totalInteger, $totalFraction] = explode('.', $refundTotal);
            [$amountInteger, $amountFraction] = explode('.', $amount);
            $refundTotal = number_format(
                ((int) $totalInteger * 100 + (int) $totalFraction + (int) $amountInteger * 100 + (int) $amountFraction) / 100,
                2,
                '.',
                ''
            );

            if ($refundDetail === null || $amount === $refundAmount) {
                $refundDetail = $refund;
            }
        }

        if ($refundTotal !== $refundAmount) {
            throw new RuntimeException('The Midtrans refund details do not match the reported refund amount.');
        }

        $notificationReference = $refundDetail['refund_chargeback_id']
            ?? $refundDetail['refund_key']
            ?? null;

        if (blank($notificationReference)) {
            throw new RuntimeException('The Midtrans refund notification does not include a refund reference.');
        }

        $notification = [
            'order_id' => (string) ($payload['order_id'] ?? ''),
            'transaction_id' => (string) ($payload['transaction_id'] ?? ''),
            'transaction_status' => 'refund',
            'status_code' => '200',
            'gross_amount' => (string) ($payload['gross_amount'] ?? ''),
            'refund_amount' => $refundAmount,
            'payment_type' => $payload['payment_type'] ?? null,
            'refund_reference' => (string) $notificationReference,
            'refund_key' => $refundDetail['refund_key'] ?? null,
            'refund_method' => $refundDetail['refund_method'] ?? null,
            'refund_reason' => $refundDetail['reason'] ?? null,
            'bank_confirmed_at' => $refundDetail['bank_confirmed_at'] ?? null,
            'created_at' => $refundDetail['created_at'] ?? null,
        ];

        $notifiableRefundId = null;
        $alreadyResolved = false;

        DB::transaction(function () use ($payload, $refundAmount, $notification, &$notifiableRefundId, &$alreadyResolved): void {
            $transaction = PaymentTransaction::query()
                ->where('gateway', 'midtrans')
                ->where('gateway_order_id', (string) ($payload['order_id'] ?? ''))
                ->lockForUpdate()
                ->firstOrFail();

            if ($this->normalizeAmount($payload['gross_amount'] ?? null) !== $this->normalizeAmount($transaction->amount)) {
                throw new RuntimeException('The Midtrans refund gross amount does not match the original payment.');
            }

            $metadata = (array) ($transaction->metadata ?? []);
            $metadata['last_refund_notification'] = $notification;
            $transaction->update(['metadata' => $metadata]);

            $refundRequest = RefundRequest::query()
                ->where('payment_transaction_id', $transaction->id)
                ->whereIn('status', ['requested', 'under_review', 'processing', 'refunded'])
                ->latest('id')
                ->lockForUpdate()
                ->first();

            if ($refundRequest === null) {
                Log::critical('Midtrans confirmed a refund without a matching customer refund request', [
                    'payment_transaction_id' => $transaction->id,
                    'gateway_order_id' => $transaction->gateway_order_id,
                    'refund_reference' => $notification['refund_reference'],
                    'refund_amount' => $refundAmount,
                ]);

                return;
            }

            if ($refundRequest->status === 'refunded') {
                $alreadyResolved = true;

                return;
            }

            if ($refundAmount !== $this->normalizeAmount($transaction->amount)) {
                Log::critical('Midtrans confirmed a partial refund that the full-refund workflow cannot resolve automatically', [
                    'payment_transaction_id' => $transaction->id,
                    'refund_request_id' => $refundRequest->id,
                    'refund_amount' => $refundAmount,
                    'payment_amount' => (string) $transaction->amount,
                    'refund_reference' => $notification['refund_reference'],
                ]);

                return;
            }

            $refundRequest->update([
                'status' => 'refunded',
                'refund_reference' => (string) $notification['refund_reference'],
                'result_notes' => 'Midtrans confirmed the refund. Method: '.($notification['refund_method'] ?? 'not provided')
                    .'; bank confirmation: '.($notification['bank_confirmed_at'] ?? 'not provided')
                    .'; reason: '.($notification['refund_reason'] ?? 'not provided').'.',
                'reviewed_at' => $refundRequest->reviewed_at ?? now(),
                'resolved_at' => now(),
            ]);

            $notifiableRefundId = $refundRequest->id;
        });

        if ($notifiableRefundId !== null) {
            $refundRequest = RefundRequest::query()->with(['customer', 'order'])->findOrFail($notifiableRefundId);
            $refundRequest->customer->notify(new RefundRequestResult($refundRequest));
        }

        if ($alreadyResolved) {
            Log::info('Duplicate Midtrans refund notification acknowledged', [
                'order_id' => $payload['order_id'] ?? null,
                'refund_reference' => $notification['refund_reference'],
            ]);
        }
    }

    public function reconcilePendingTransactions(string $gatewayName = 'midtrans'): int
    {
        $gateway = $this->gateways->driver($gatewayName, false);
        $reconciled = 0;

        PaymentTransaction::query()
            ->where('gateway', $gatewayName)
            ->where('status', 'pending')
            ->where('updated_at', '<=', now()->subMinutes(5))
            ->orderBy('id')
            ->chunkById(50, function ($transactions) use ($gateway, $gatewayName, &$reconciled): void {
                foreach ($transactions as $transaction) {
                    try {
                        $payload = $gateway->getStatus($transaction->gateway_order_id);

                        if ($payload === null) {
                            $attempts = $this->recordReconciliationAttempt($transaction, 'not_found');
                            if ($transaction->redirect_url === null && $attempts >= 3) {
                                $this->failUnconfirmedSession($transaction);
                            }

                            continue;
                        }

                        if ((string) ($payload['order_id'] ?? '') !== $transaction->gateway_order_id) {
                            throw new RuntimeException('Order ID dari Midtrans GET Status tidak cocok.');
                        }

                        $this->assertValidStatusCode($payload);
                        $this->applyVerifiedStatus($payload, $gateway, $gatewayName, false);
                        $transaction->refresh();

                        $this->recordReconciliationAttempt($transaction, 'checked');

                        if (in_array($transaction->status, ['paid', 'failed', 'expired'], true)) {
                            $reconciled++;
                            Log::info('Midtrans payment reconciled from status API', [
                                'payment_transaction_id' => $transaction->id,
                                'order_id' => $transaction->gateway_order_id,
                                'status' => $transaction->status,
                            ]);
                        }
                    } catch (Throwable $exception) {
                        $this->recordReconciliationAttempt($transaction, 'error', $exception);
                        Log::warning('Midtrans payment reconciliation failed', [
                            'payment_transaction_id' => $transaction->id,
                            'order_id' => $transaction->gateway_order_id,
                            'exception' => $exception::class,
                            'message' => $exception->getMessage(),
                        ]);
                    }
                }
            });

        return $reconciled;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function applyVerifiedStatus(
        array $payload,
        PaymentGateway $gateway,
        string $gatewayName,
        bool $isNotification,
    ): void {
        $paidOrderId = null;

        DB::transaction(function () use ($payload, $gateway, $gatewayName, $isNotification, &$paidOrderId): void {
            $transaction = PaymentTransaction::query()
                ->where('gateway', $gatewayName)
                ->where('gateway_order_id', $payload['order_id'] ?? '')
                ->lockForUpdate()
                ->firstOrFail();

            if ($this->normalizeAmount($payload['gross_amount'] ?? null) !== $this->normalizeAmount($transaction->amount)) {
                throw new RuntimeException('The payment notification amount does not match the transaction.');
            }

            if ($isNotification === false && isset($payload['currency']) === false) {
                throw new RuntimeException('The Midtrans status response does not include a currency.');
            }

            if (isset($payload['currency']) && strtoupper((string) $payload['currency']) !== strtoupper($transaction->currency)) {
                throw new RuntimeException('The payment notification currency does not match the transaction.');
            }

            $status = $gateway->normalizeStatus($payload);

            if (in_array($transaction->status, ['paid', 'failed', 'expired'], true)) {
                if ($transaction->status === 'paid' && $status === 'paid') {
                    $paidOrderId = $transaction->order_id;
                }

                return;
            }

            $order = Order::query()->whereKey($transaction->order_id)->lockForUpdate()->firstOrFail();

            if ($order->payment_status === 'paid' && $status !== 'paid') {
                throw new RuntimeException('The order status does not allow this payment update.');
            }

            $inventoryError = null;

            if ($order->status === 'cancelled') {
                if ($status === 'paid') {
                    $inventoryError = 'Payment was received after the order was cancelled; manual review is required.';
                }
            } elseif ($order->status !== 'pending') {
                $inventoryError = 'The order status does not allow an automatic payment transition; manual review is required.';
            } else {
                $inventoryError = $this->applyInventoryTransition($order, $status);
            }

            $metadata = array_merge((array) ($transaction->metadata ?? []), [
                $isNotification ? 'last_notification' : 'last_status_response' => $this->safeNotificationMetadata($payload),
            ]);

            if ($inventoryError !== null) {
                $metadata['inventory_review_required'] = $inventoryError;
            }

            $transaction->update([
                'status' => $status,
                'gateway_transaction_id' => $payload['transaction_id'] ?? $transaction->gateway_transaction_id,
                'metadata' => $metadata,
                'paid_at' => $status === 'paid' ? now() : null,
            ]);

            $order->update(['payment_status' => $status]);

            if ($status === 'paid') {
                $paidOrderId = $order->id;
            }

            if ($status === 'paid' && $order->status === 'pending' && $inventoryError === null) {
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

            if ($inventoryError !== null) {
                Log::critical('Payment confirmed but inventory/order requires manual review', [
                    'payment_transaction_id' => $transaction->id,
                    'order_id' => $transaction->gateway_order_id,
                    'order_status' => $order->status,
                    'payment_status' => $status,
                    'reason' => $inventoryError,
                ]);
            }
        });

        if ($paidOrderId !== null) {
            app(OrderNotificationService::class)->queuePaymentConfirmed($paidOrderId);
        }
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function assertValidStatusCode(array $payload): void
    {
        $statusCode = $payload['status_code'] ?? null;

        if (
            (is_string($statusCode) === false && is_int($statusCode) === false)
            || preg_match('/^\\d{3}$/', (string) $statusCode) !== 1
        ) {
            throw new RuntimeException('The Midtrans status code is invalid.');
        }

        $transactionStatus = (string) ($payload['transaction_status'] ?? '');

        if (in_array($transactionStatus, ['settlement', 'capture'], true) && (string) $statusCode !== '200') {
            throw new RuntimeException('Successful Midtrans transactions must use status code 200.');
        }
    }

    private function recordReconciliationAttempt(
        PaymentTransaction $transaction,
        string $result,
        ?Throwable $exception = null,
    ): int {
        return DB::transaction(function () use ($transaction, $result, $exception): int {
            $locked = PaymentTransaction::query()->whereKey($transaction->id)->lockForUpdate()->first();

            if ($locked === null || $locked->status !== 'pending') {
                return 0;
            }

            $metadata = (array) ($locked->metadata ?? []);
            $attempts = $result === 'not_found' ? (int) data_get($metadata, 'reconciliation.not_found_attempts', 0) + 1 : 0;
            $metadata['reconciliation'] = [
                'checked_at' => now()->toISOString(),
                'result' => $result,
                'not_found_attempts' => $attempts,
                'error' => $exception?->getMessage(),
            ];

            $locked->update(['metadata' => $metadata]);

            return $attempts;
        });
    }

    private function failUnconfirmedSession(PaymentTransaction $transaction): void
    {
        DB::transaction(function () use ($transaction): void {
            $lockedTransaction = PaymentTransaction::query()
                ->whereKey($transaction->id)
                ->lockForUpdate()
                ->first();

            if (
                $lockedTransaction === null
                || $lockedTransaction->status !== 'pending'
                || $lockedTransaction->redirect_url !== null
                || (int) data_get($lockedTransaction->metadata, 'reconciliation.not_found_attempts', 0) < 3
            ) {
                return;
            }

            $order = Order::query()
                ->whereKey($lockedTransaction->order_id)
                ->lockForUpdate()
                ->firstOrFail();
            $inventoryError = $this->applyInventoryTransition($order, 'failed');
            $metadata = (array) ($lockedTransaction->metadata ?? []);
            $metadata['session_creation_unconfirmed_at'] = now()->toISOString();

            if ($inventoryError !== null) {
                $metadata['inventory_review_required'] = $inventoryError;
            }

            $lockedTransaction->update(['status' => 'failed', 'metadata' => $metadata]);
            $order->update(['payment_status' => 'failed']);

            if ($order->status === 'pending') {
                $order->update(['status' => 'cancelled']);
                $order->statusHistories()->create([
                    'store_id' => $order->store_id,
                    'from_status' => 'pending',
                    'to_status' => 'cancelled',
                    'note' => 'Midtrans session was not found after repeated status checks; inventory release attempted.',
                ]);
            }

            if ($inventoryError !== null) {
                Log::critical('Unconfirmed payment session requires inventory review', [
                    'payment_transaction_id' => $lockedTransaction->id,
                    'order_id' => $order->order_number,
                    'reason' => $inventoryError,
                ]);
            }
        });
    }

    private function normalizeAmount(mixed $amount): string
    {
        if (is_string($amount) === false && is_int($amount) === false && is_float($amount) === false) {
            throw new RuntimeException('The payment notification amount is invalid.');
        }

        $amount = (string) $amount;
        if (preg_match('/^\d{1,13}(?:\.\d{1,2})?$/', $amount) === 0) {
            throw new RuntimeException('The payment notification amount is invalid.');
        }

        [$integer, $fraction] = array_pad(explode('.', $amount, 2), 2, '');
        $integer = ltrim($integer, '0') ?: '0';

        return $integer.'.'.str_pad($fraction, 2, '0');
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, scalar|null>
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

    private function applyInventoryTransition(Order $order, string $status): ?string
    {
        if (in_array($status, ['paid', 'failed', 'expired'], true) === false) {
            return null;
        }

        $expected = $order->items()
            ->where('track_inventory', true)
            ->whereNotNull('product_variant_id')
            ->get()
            ->groupBy('product_variant_id')
            ->map(fn ($items): int => (int) $items->sum('quantity'));

        if ($expected->isEmpty()) {
            return null;
        }

        $reservations = InventoryReservation::query()
            ->where('store_id', $order->store_id)
            ->where('order_id', $order->id)
            ->where('status', 'reserved')
            ->lockForUpdate()
            ->get()
            ->keyBy('product_variant_id');

        if ($reservations->count() !== $expected->count()) {
            return 'The inventory reservation ledger is incomplete for this order.';
        }

        $transitions = [];
        foreach ($expected as $variantId => $quantity) {
            $reservation = $reservations->get($variantId);
            if ($reservation === null || $reservation->quantity !== $quantity) {
                return 'The inventory reservation quantity does not match the order items.';
            }

            $inventory = Inventory::query()
                ->where('store_id', $order->store_id)
                ->whereKey($reservation->inventory_id)
                ->where('product_variant_id', $variantId)
                ->lockForUpdate()
                ->first();

            if ($inventory === null || $inventory->reserved_quantity < $quantity) {
                return 'The inventory reservation is missing or inconsistent for this order.';
            }

            if ($status === 'paid' && $inventory->quantity < $quantity) {
                return 'Insufficient inventory to complete the payment.';
            }

            $transitions[] = [$reservation, $inventory, $quantity];
        }

        foreach ($transitions as [$reservation, $inventory, $quantity]) {
            if ($status === 'paid') {
                $inventory->decrement('quantity', $quantity);
                $reservation->update(['status' => 'consumed', 'consumed_at' => now()]);
            } else {
                $reservation->update(['status' => 'released', 'released_at' => now()]);
            }

            $inventory->decrement('reserved_quantity', $quantity);
        }

        return null;
    }
}
