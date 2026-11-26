<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property array<string, mixed>|null $settings
 * @property int $id
 * @property int $team_id
 * @property string $name
 * @property string $slug
 * @property string $status
 * @property string $timezone
 * @property string $currency
 * @property-read Team|null $team
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

    /** @return BelongsTo<Team, $this> */
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
        return $this->hasOne(StoreDomain::class)->where('is_primary', true);
    }

    /** @return HasMany<Customer, $this> */
    public function customers(): HasMany
    {
        return $this->hasMany(Customer::class);
    }

    /** @return HasMany<Category, $this> */
    public function categories(): HasMany
    {
        return $this->hasMany(Category::class);
    }

    /** @return HasMany<Product, $this> */
    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    /** @return array<string, mixed> */
    public function storefrontSettings(): array
    {
        $settings = $this->settings ?? [];
        $storefront = $settings['storefront'] ?? [];

        return is_array($storefront) ? $storefront : [];
    }

    protected function casts(): array
    {
        return ['settings' => 'array'];
    }
}
