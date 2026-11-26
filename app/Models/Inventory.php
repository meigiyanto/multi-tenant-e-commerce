<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['store_id', 'product_variant_id', 'quantity', 'reserved_quantity', 'low_stock_threshold'])]
/**
 * @property int $quantity
 * @property int $reserved_quantity
 */
class Inventory extends Model
{
    protected function casts(): array
    {
        return ['quantity' => 'integer', 'reserved_quantity' => 'integer', 'low_stock_threshold' => 'integer'];
    }

    /** @return BelongsTo<Store, $this> */
    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    /** @return BelongsTo<ProductVariant, $this> */
    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'product_variant_id');
    }

    /** @return HasMany<InventoryReservation, $this> */
    public function reservations(): HasMany
    {
        return $this->hasMany(InventoryReservation::class);
    }

    /** @return HasMany<InventoryMovement, $this> */
    public function movements(): HasMany
    {
        return $this->hasMany(InventoryMovement::class);
    }
}
