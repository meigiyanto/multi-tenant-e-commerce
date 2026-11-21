<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'store_id', 'order_id', 'product_id', 'product_variant_id', 'product_name',
    'variant_name', 'sku', 'options', 'quantity', 'track_inventory', 'unit_price', 'line_total',
])]
class OrderItem extends Model
{
    protected function casts(): array
    {
        return [
            'options' => 'array',
            'quantity' => 'integer',
            'track_inventory' => 'boolean',
            'unit_price' => 'decimal:2',
            'line_total' => 'decimal:2',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'product_variant_id');
    }
}
