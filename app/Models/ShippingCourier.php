<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ShippingCourier extends Model
{
    protected $fillable = [
        'name',
        'is_active',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    /** @return HasMany<ShippingRegion, $this> */
    public function regions(): HasMany
    {
        return $this->hasMany(ShippingRegion::class)->orderBy('sort_order');
    }

    /** @return HasMany<ShippingRegion, $this> */
    public function activeRegions(): HasMany
    {
        return $this->regions()->where('is_active', true);
    }
}
