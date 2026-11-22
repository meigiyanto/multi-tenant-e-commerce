<?php

namespace App\Contracts\Payments;

use App\Models\Order;
use App\Models\PaymentTransaction;

interface PaymentGateway
{
    /** @return array{token: string, redirect_url: string, response: array<string, mixed>} */
    public function createPayment(Order $order, PaymentTransaction $transaction): array;

    /** @param array<string, mixed> $payload */
    public function verifyNotification(array $payload): bool;

    /** @param array<string, mixed> $payload */
    public function normalizeStatus(array $payload): string;

    /** @return array<string, mixed>|null */
    public function getStatus(string $gatewayOrderId): ?array;
}
