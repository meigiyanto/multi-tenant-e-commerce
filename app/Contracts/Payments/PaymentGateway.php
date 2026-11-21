<?php

namespace App\Contracts\Payments;

use App\Models\Order;
use App\Models\PaymentTransaction;

interface PaymentGateway
{
    public function createPayment(Order $order, PaymentTransaction $transaction): array;

    public function verifyNotification(array $payload): bool;

    public function normalizeStatus(array $payload): string;
}
