<?php

namespace App\Models;

use Database\Factories\ProductVariantFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProductVariant extends Model
{
    /** @use HasFactory<ProductVariantFactory> */
    use HasFactory;

    /**
     * At or below this many units, stock counts as low.
     *
     * A fact about stock, so it lives on the model rather than on whichever
     * screen happens to display it. Shared to every Inertia response by
     * HandleInertiaRequests, so no Vue file carries its own copy — the
     * storefront badge a customer sees and the admin's low-stock tile are
     * reading the same number by construction, not by coincidence.
     *
     * (products/inventory/types.ts declares its own 10. That page is hidden
     * placeholder data with no connection to real stock; it is deliberately
     * left alone.)
     */
    public const LOW_STOCK_THRESHOLD = 5;

    protected $fillable = [
        'product_id',
        'label',
        'price',
        'stock',
        'is_kit',
        'kit_inclusions',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'stock' => 'integer',
            'is_kit' => 'boolean',
            'kit_inclusions' => 'array',
        ];
    }

    /** @return BelongsTo<Product, $this> */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * Inverse of the order-item link. Not used yet — the admin Orders screens
     * are Phase 2 — but kept available so history is reachable from a variant.
     *
     * @return HasMany<OrderItem, $this>
     */
    public function orderItems(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }
}
