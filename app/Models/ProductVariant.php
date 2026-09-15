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
     */
    public const LOW_STOCK_THRESHOLD = 5;

    /*
     * The three-way split the Inventory screen shows.
     *
     * A finer reading of the same threshold the dashboard tile counts by, not
     * a second rule: LOW and OUT together are exactly what that tile calls low
     * stock. Zero is pulled out of LOW because "order more soon" and "cannot
     * sell this at all" are different problems to the person reading the row.
     */
    public const STATUS_IN_STOCK = 'In Stock';

    public const STATUS_LOW_STOCK = 'Low Stock';

    public const STATUS_OUT_OF_STOCK = 'Out of Stock';

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

    /**
     * Where this variant's stock currently stands, in the Inventory screen's
     * terms.
     *
     * On the model rather than in the controller for the same reason the
     * threshold is: it is a fact about stock, and the next screen that needs
     * to say it should not be re-deriving the rule.
     */
    public function stockStatus(): string
    {
        $stock = (int) $this->stock;

        if ($stock <= 0) {
            return self::STATUS_OUT_OF_STOCK;
        }

        return $stock <= self::LOW_STOCK_THRESHOLD
            ? self::STATUS_LOW_STOCK
            : self::STATUS_IN_STOCK;
    }

    /** @return BelongsTo<Product, $this> */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * Every recorded change to this variant's stock, oldest first.
     *
     * Ordered by id rather than created_at: several movements can share a
     * second — an order taking two formats, or a correction right after a
     * restock — and only insertion order puts them back in sequence.
     *
     * @return HasMany<StockMovement, $this>
     */
    public function movements(): HasMany
    {
        return $this->hasMany(StockMovement::class)->orderBy('id');
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
