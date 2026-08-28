<?php

namespace App\Models;

use Database\Factories\ProductFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Product extends Model
{
    /** @use HasFactory<ProductFactory> */
    use HasFactory;

    protected $fillable = [
        'category_id',
        'name',
        'slug',
        'status',
        'featured',
        'short_description',
        'full_description',
    ];

    protected function casts(): array
    {
        return [
            'featured' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (Product $product) {
            if (blank($product->slug)) {
                $product->slug = Str::slug($product->name);
            }
        });
    }

    /** @return BelongsTo<Category, $this> */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    /** @return HasMany<ProductVariant, $this> */
    public function variants(): HasMany
    {
        return $this->hasMany(ProductVariant::class)->orderBy('sort_order');
    }

    /** @return HasMany<ProductTechnicalDetail, $this> */
    public function technicalDetails(): HasMany
    {
        return $this->hasMany(ProductTechnicalDetail::class)->orderBy('sort_order');
    }

    /** @return HasMany<ProductImage, $this> */
    public function images(): HasMany
    {
        return $this->hasMany(ProductImage::class)->orderBy('sort_order');
    }

    /** @return HasMany<ProductTechnicalDetail, $this> */
    public function purityDetails(): HasMany
    {
        return $this->technicalDetails()->where('type', ProductTechnicalDetail::TYPE_PURITY);
    }

    /** @return HasMany<ProductTechnicalDetail, $this> */
    public function storageDetails(): HasMany
    {
        return $this->technicalDetails()->where('type', ProductTechnicalDetail::TYPE_STORAGE);
    }
}
