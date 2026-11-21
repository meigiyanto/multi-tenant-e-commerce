<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'store_id', 'order_id', 'gateway', 'gateway_order_id', 'gateway_transaction_id',
    'token', 'redirect_url', 'amount', 'currency', 'status', 'metadata', 'paid_at',
])]
/**
 * @property array<string, mixed>|null $metadata
 */
class PaymentTransaction extends Model
{
    protected function casts(): array
    {
        return ['amount' => 'decimal:2', 'metadata' => 'array', 'paid_at' => 'datetime'];
    }

    /** @return BelongsTo<Order, $this> */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /** @return BelongsTo<Store, $this> */
    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }
}
