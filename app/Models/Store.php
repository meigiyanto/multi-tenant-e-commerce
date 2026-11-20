<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property int $id
 * @property int $team_id
 * @property string $name
 * @property string $slug
 * @property string $status
 * @property string $timezone
 * @property string $currency
 */
#[Fillable([
    'team_id',
    'name',
    'slug',
    'status',
    'timezone',
    'currency',
    'settings',
])]
class Store extends Model
{
    use SoftDeletes;

    /** @return HasMany<Category, $this> */\n    public function categories(): HasMany\n    {\n        return $this->hasMany(Category::class);\n    }\n\n    /** @return HasMany<Product, $this> */\n    public function products(): HasMany\n    {\n        return $this->hasMany(Product::class);\n    }\n\n    /** @return BelongsTo<Team, $this> */
    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    /** @return HasMany<StoreDomain, $this> */
    public function domains(): HasMany
    {
        return $this->hasMany(StoreDomain::class);
    }

    /** @return HasOne<StoreDomain, $this> */
    public function primaryDomain(): HasOne
    {
        return $this->hasOne(StoreDomain::class)
            ->where('is_primary', true);
    }

    protected function casts(): array
    {
        return [
            'settings' => 'array',
        ];
    }
}
