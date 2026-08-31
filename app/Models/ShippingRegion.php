<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ShippingRegion extends Model
{
    protected $fillable = [
        'shipping_courier_id',
        'name',
        'note',
        'rate',
        'is_active',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'rate' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }

    /** @return BelongsTo<ShippingCourier, $this> */
    public function courier(): BelongsTo
    {
        return $this->belongsTo(ShippingCourier::class, 'shipping_courier_id');
    }

    /**
     * What gets snapshotted onto the order, so a later rename or re-price
     * cannot rewrite what the customer agreed to.
     */
    public function snapshotLabel(): string
    {
        return trim($this->name.($this->note ? " ({$this->note})" : ''));
    }
}
