<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable(['store_id', 'product_id', 'name', 'sku', 'options', 'price', 'compare_at_price', 'is_active'])]
/**
 * @property int $product_id
 * @property int $store_id
 * @property string|null $sku
 * @property string|null $name
 * @property array<string, mixed>|null $options
 * @property numeric-string|float|int|null $price
 * @property bool $is_active
 * @property-read Inventory|null $inventory
 */
class ProductVariant extends Model
{
    protected function casts(): array
    {
        return ['options' => 'array', 'price' => 'decimal:2', 'compare_at_price' => 'decimal:2', 'is_active' => 'boolean'];
    }

    /** @return BelongsTo<Product, $this> */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /** @return HasOne<Inventory, $this> */
    public function inventory(): HasOne
    {
        return $this->hasOne(Inventory::class);
    }
}
