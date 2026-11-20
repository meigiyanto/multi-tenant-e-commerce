<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * @property int $id
 * @property int $store_id
 * @property string $domain
 * @property string $status
 * @property bool $is_primary
 * @property \Illuminate\Support\Carbon|null $verified_at
 */
#[Fillable([
    'store_id',
    'domain',
    'type',
    'is_primary',
    'status',
    'verified_at',
])]
class StoreDomain extends Model
{
    protected static function booted(): void
    {
        static::saving(function (StoreDomain $domain) {
            $domain->domain = rtrim(
                Str::lower(trim($domain->domain)),
                '.',
            );
        });
    }

    /** @return BelongsTo<Store, $this> */
    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    protected function casts(): array
    {
        return [
            'is_primary' => 'boolean',
            'verified_at' => 'datetime',
        ];
    }
}
