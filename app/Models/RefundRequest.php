<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

#[Fillable([
    'store_id', 'order_id', 'customer_id', 'payment_transaction_id', 'reviewed_by',
    'status', 'reason', 'admin_notes', 'refund_reference', 'result_notes', 'reviewed_at', 'resolved_at',
])]
/** @property Carbon|null $reviewed_at @property Carbon|null $resolved_at */
class RefundRequest extends Model
{
    protected function casts(): array
    {
        return ['reviewed_at' => 'datetime', 'resolved_at' => 'datetime'];
    }

    /** @return BelongsTo<Order, $this> */
    public function order(): BelongsTo { return $this->belongsTo(Order::class); }

    /** @return BelongsTo<Customer, $this> */
    public function customer(): BelongsTo { return $this->belongsTo(Customer::class); }

    /** @return BelongsTo<PaymentTransaction, $this> */
    public function paymentTransaction(): BelongsTo { return $this->belongsTo(PaymentTransaction::class); }

    /** @return BelongsTo<User, $this> */
    public function reviewer(): BelongsTo { return $this->belongsTo(User::class, 'reviewed_by'); }
}
