<?php

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductTechnicalDetail;
use Illuminate\Database\Eloquent\Builder;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Public catalogue reads.
 *
 * Home, Catalog and Product Detail. Cart, Checkout, Order Confirmation and
 * Track Order are real and persisted too, each with its own controller in this
 * namespace; this one covers the catalogue reads only.
 *
 * The payload shape deliberately matches the one in
 * App\Http\Controllers\Admin\ProductController so the storefront pages can
 * reuse the `Product` type and its helpers from
 * resources/js/pages/admin/products/all-products/types.ts rather than growing a
 * second, drifting definition.
 */
class ProductController extends Controller
{
    /**
     * Every public query runs through here, so a draft or archived product can
     * never reach a storefront page by way of a new call site forgetting the
     * filter.
     *
     * @return Builder<Product>
     */
    private function visible(): Builder
    {
        return Product::query()
            ->where('status', 'active')
            ->with(['category', 'variants', 'images', 'technicalDetails']);
    }

    /**
     * @return array<string, mixed>
     */
    private function toPayload(Product $product): array
    {
        return [
            'id' => $product->id,
            'name' => $product->name,
            'slug' => $product->slug,
            'category' => $product->category?->name ?? '',
            'category_id' => $product->category_id,
            // Capitalised to match the admin payload the `Product` type is
            // written against; the column itself is a lowercase enum.
            'status' => ucfirst($product->status),
            'featured' => (bool) $product->featured,
            'short_description' => (string) $product->short_description,
            'full_description' => (string) $product->full_description,
            'purity_entries' => $this->entries($product, ProductTechnicalDetail::TYPE_PURITY),
            'storage_instructions' => $this->entries($product, ProductTechnicalDetail::TYPE_STORAGE),
            'images' => $product->images
                ->map(fn ($image) => ['id' => $image->id, 'url' => $image->url()])
                ->values()
                ->all(),
            'variants' => $product->variants
                ->map(fn ($variant) => [
                    'id' => (string) $variant->id,
                    'label' => $variant->label,
                    'price' => (float) $variant->price,
                    'stock' => (int) $variant->stock,
                    'is_kit' => (bool) $variant->is_kit,
                    'kit_inclusions' => $variant->kit_inclusions ?? [],
                ])
                ->values()
                ->all(),
            'created_at' => $product->created_at?->toDateString(),
        ];
    }

    /**
     * Filters the already-loaded relation rather than issuing another query.
     *
     * @return array<int, array<string, mixed>>
     */
    private function entries(Product $product, string $type): array
    {
        return $product->technicalDetails
            ->where('type', $type)
            ->sortBy('sort_order')
            ->map(fn ($detail) => [
                'id' => (string) $detail->id,
                'label' => (string) $detail->label,
                'value' => $detail->value,
            ])
            ->values()
            ->all();
    }

    /**
     * Category names that actually have something to show, so the catalogue
     * filter never offers a tab that resolves to an empty grid.
     *
     * @return array<int, string>
     */
    private function activeCategoryNames(): array
    {
        return Category::query()
            ->whereHas('products', fn (Builder $query) => $query->where('status', 'active'))
            ->orderBy('name')
            ->pluck('name')
            ->all();
    }

    public function home(): Response
    {
        $featured = $this->visible()
            ->where('featured', true)
            ->orderBy('name')
            ->get()
            ->map(fn (Product $product) => $this->toPayload($product))
            ->all();

        return Inertia::render('storefront/Home', [
            'featured' => $featured,
            'categories' => $this->activeCategoryNames(),
        ]);
    }

    public function index(): Response
    {
        // Not paginated: the catalogue filters by category and search entirely
        // client-side, the same call the admin index makes. Revisit when the
        // active catalogue outgrows a single payload.
        $products = $this->visible()
            ->orderBy('name')
            ->get()
            ->map(fn (Product $product) => $this->toPayload($product))
            ->all();

        return Inertia::render('storefront/Catalog', [
            'products' => $products,
            'categories' => $this->activeCategoryNames(),
        ]);
    }

    public function show(string $slug): Response
    {
        $product = $this->visible()
            ->where('slug', $slug)
            ->firstOrFail();

        // Same category first, minus the product being viewed.
        $related = $this->visible()
            ->whereKeyNot($product->id)
            ->when(
                $product->category_id !== null,
                fn (Builder $query) => $query->where('category_id', $product->category_id),
            )
            ->orderBy('name')
            ->limit(4)
            ->get();

        // A product that is alone in its category would otherwise render an
        // empty strip, so top up from the rest of the active catalogue.
        if ($related->count() < 4) {
            $related = $related->concat(
                $this->visible()
                    ->whereKeyNot($product->id)
                    ->whereKeyNot($related->modelKeys())
                    ->orderBy('name')
                    ->limit(4 - $related->count())
                    ->get()
            );
        }

        $related = $related
            ->map(fn (Product $item) => $this->toPayload($item))
            ->all();

        return Inertia::render('storefront/Product', [
            'product' => $this->toPayload($product),
            'related' => $related,
        ]);
    }
}
