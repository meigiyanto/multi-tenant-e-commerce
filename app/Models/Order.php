<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'store_id', 'user_id', 'customer_id', 'order_number', 'status', 'payment_status', 'customer_name',
    'customer_email', 'customer_phone', 'shipping_address', 'shipping_city',
    'shipping_province', 'shipping_postal_code', 'shipping_destination_id',
    'shipping_destination_label', 'shipping_provider', 'shipping_courier',
    'shipping_service', 'shipping_weight_grams', 'shipping_tracking_number',
    'shipping_tracking_url', 'shipped_at', 'delivered_at', 'customer_note', 'currency',
    'subtotal', 'shipping_amount', 'discount_amount', 'total', 'placed_at',
])]
class Order extends Model
{
    protected function casts(): array
    {
        return [
            'subtotal' => 'decimal:2',
            'shipping_amount' => 'decimal:2',
            'discount_amount' => 'decimal:2',
            'total' => 'decimal:2',
            'shipping_destination_id' => 'integer',
            'shipping_weight_grams' => 'integer',
            'placed_at' => 'datetime',
            'shipped_at' => 'datetime',
            'delivered_at' => 'datetime',
        ];
    }

    /**
     * Legacy scope retained while existing User-linked order history is migrated.
     *
     * @param  Builder<Order>  $query
     * @return Builder<Order>
     */
    public function scopeForCustomer(Builder $query, User $user): Builder
    {
        return $query->where(function (Builder $orders) use ($user): void {
            $orders->where('user_id', $user->id);

            if ($user->hasVerifiedEmail()) {
                $orders->orWhere(function (Builder $legacy) use ($user): void {
                    $legacy->whereNull('user_id')
                        ->whereRaw('LOWER(customer_email) = ?', [mb_strtolower($user->email)]);
                });
            }
        });
    }

    /** @return BelongsTo<Store, $this> */
    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<Customer, $this> */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /** @return HasMany<OrderItem, $this> */
    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    /** @return HasMany<OrderStatusHistory, $this> */
    public function statusHistories(): HasMany
    {
        return $this->hasMany(OrderStatusHistory::class);
    }

    /** @return HasMany<InventoryReservation, $this> */
    public function inventoryReservations(): HasMany
    {
        return $this->hasMany(InventoryReservation::class);
    }

    /** @return HasMany<PaymentTransaction, $this> */
    public function paymentTransactions(): HasMany
    {
        return $this->hasMany(PaymentTransaction::class);
    }

    /** @return HasMany<NotificationDelivery, $this> */
    public function notificationDeliveries(): HasMany
    {
        return $this->hasMany(NotificationDelivery::class);
    }
}
