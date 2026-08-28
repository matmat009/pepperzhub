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

    protected $fillable = ['product_id', 'type', 'label', 'value', 'sort_order'];

    /** @return BelongsTo<Product, $this> */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
