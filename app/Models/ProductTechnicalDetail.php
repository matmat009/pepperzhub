<?php

namespace App\Models;

use Database\Factories\ProductTechnicalDetailFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductTechnicalDetail extends Model
{
    /** @use HasFactory<ProductTechnicalDetailFactory> */
    use HasFactory;

    public const TYPE_PURITY = 'purity';

    public const TYPE_STORAGE = 'storage';

    /**
     * A protocol note is one plain line of guidance, so it carries no label —
     * the nullable `label` column already allows that, which is why these live
     * here rather than in a table of their own.
     */
    public const TYPE_PROTOCOL = 'protocol';

    protected $fillable = ['product_id', 'type', 'label', 'value', 'sort_order'];

    /** @return BelongsTo<Product, $this> */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
