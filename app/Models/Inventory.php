<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['store_id', 'product_variant_id', 'quantity', 'reserved_quantity', 'low_stock_threshold'])]
class Inventory extends Model
{
    protected function casts(): array
    {
        return ['quantity' => 'integer', 'reserved_quantity' => 'integer', 'low_stock_threshold' => 'integer'];
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'product_variant_id');
    }

    public function movements(): HasMany
    {
        return $this->hasMany(InventoryMovement::class);
    }
}
