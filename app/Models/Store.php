<?php

namespace App\\Models;

use Illuminate\\Database\\Eloquent\\Attributes\\Fillable;
use Illuminate\\Database\\Eloquent\\Model;
use Illuminate\\Database\\Eloquent\\Relations\\BelongsTo;
use Illuminate\\Database\\Eloquent\\Relations\\HasMany;
use Illuminate\\Database\\Eloquent\\Relations\\HasOne;
use Illuminate\\Database\\Eloquent\\SoftDeletes;

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

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    public function domains(): HasMany
    {
        return $this->hasMany(StoreDomain::class);
    }

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
