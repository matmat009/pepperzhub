<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One recorded change to a variant's stock.
 *
 * Append-only: nothing edits or deletes a movement. A miscount is corrected by
 * writing the opposite movement, which is why REASON_CORRECTION exists.
 */
class StockMovement extends Model
{
    /*
     * Reasons an admin chooses. These are the only values the Adjust dialog
     * offers, and the only ones its endpoint accepts.
     */
    public const REASON_RESTOCK = 'Restock';

    public const REASON_DAMAGED = 'Damaged';

    public const REASON_CORRECTION = 'Correction';

    /*
     * Reasons the system writes for itself. Never selectable: they mean "stock
     * moved because an order did something", and an admin picking one by hand
     * would be asserting an order event that never happened.
     */
    public const REASON_ORDER_FULFILLED = 'Order Fulfilled';

    public const REASON_ORDER_CANCELLED = 'Order Cancelled';

    /** @var list<string> */
    public const MANUAL_REASONS = [
        self::REASON_RESTOCK,
        self::REASON_DAMAGED,
        self::REASON_CORRECTION,
    ];

    /** @var list<string> */
    public const AUTOMATIC_REASONS = [
        self::REASON_ORDER_FULFILLED,
        self::REASON_ORDER_CANCELLED,
    ];

    protected $fillable = [
        'product_variant_id',
        'delta',
        'reason',
        'note',
        'resulting_stock',
    ];

    protected function casts(): array
    {
        return [
            'delta' => 'integer',
            'resulting_stock' => 'integer',
        ];
    }

    /**
     * Log a change that has **already** been applied to the variant.
     *
     * The caller applies the change and this records it, rather than the other
     * way round: checkout, the cancel path and the product form each move stock
     * in their own way — decrement, increment, a plain update — and folding all
     * three into one setter would mean rewriting working code to fit a logger.
     *
     * So the contract is that $variant->stock already reflects $delta by the
     * time this is called. Eloquent's increment() and decrement() update the
     * in-memory attribute alongside the column, so that holds for both without
     * a re-read. Every caller must be inside the transaction that made the
     * change, so stock and its history cannot land independently of each other.
     */
    public static function record(
        ProductVariant $variant,
        int $delta,
        string $reason,
        ?string $note = null,
    ): self {
        return static::create([
            'product_variant_id' => $variant->id,
            'delta' => $delta,
            'reason' => $reason,
            'note' => $note,
            'resulting_stock' => (int) $variant->stock,
        ]);
    }

    /** @return BelongsTo<ProductVariant, $this> */
    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'product_variant_id');
    }
}
