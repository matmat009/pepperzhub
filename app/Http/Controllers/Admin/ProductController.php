<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ProductRequest;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductTechnicalDetail;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class ProductController extends Controller
{
    /**
     * Serialised shape consumed by
     * resources/js/pages/admin/products/all-products/types.ts.
     *
     * `category` stays a name because the index filters and badges read it;
     * `category_id` is what the form posts back.
     *
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
            // The UI labels are capitalised; the column is a lowercase enum.
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
     * @return array<int, array<string, mixed>>
     */
    private function categoryOptions(): array
    {
        return Category::query()
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (Category $category) => [
                'id' => $category->id,
                'name' => $category->name,
            ])
            ->all();
    }

    public function index(): Response
    {
        // Deliberately not paginated server-side: the status tabs, search and
        // category filter all operate across the full set client-side. Revisit
        // once the catalogue outgrows a single payload.
        $products = Product::query()
            ->with(['category', 'variants', 'technicalDetails', 'images'])
            ->orderBy('name')
            ->get()
            ->map(fn (Product $product) => $this->toPayload($product))
            ->all();

        return Inertia::render('admin/products/all-products/Index', [
            'products' => $products,
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('admin/products/all-products/Create', [
            'categories' => $this->categoryOptions(),
        ]);
    }

    public function show(Product $product): Response
    {
        $product->load(['category', 'variants', 'technicalDetails', 'images']);

        return Inertia::render('admin/products/all-products/Show', [
            'product' => $this->toPayload($product),
            'categories' => $this->categoryOptions(),
        ]);
    }

    public function store(ProductRequest $request): RedirectResponse
    {
        $product = DB::transaction(function () use ($request) {
            $product = Product::create($this->attributes($request));

            $this->syncRelations($product, $request);

            return $product;
        });

        $this->toast('Product published.');

        return to_route('admin.products.show', $product);
    }

    public function update(ProductRequest $request, Product $product): RedirectResponse
    {
        DB::transaction(function () use ($request, $product) {
            $product->update($this->attributes($request));

            $this->syncRelations($product, $request);
        });

        $this->toast('Product updated.');

        return to_route('admin.products.show', $product);
    }

    public function destroy(Product $product): RedirectResponse
    {
        DB::transaction(function () use ($product) {
            // Variants, technical details and image rows cascade; the stored
            // files do not, so clear them first.
            foreach ($product->images as $image) {
                Storage::disk('public')->delete($image->path);
            }

            $product->delete();
        });

        $this->toast('Product deleted.');

        return to_route('admin.products.index');
    }

    /**
     * @return array<string, mixed>
     */
    private function attributes(ProductRequest $request): array
    {
        return [
            'category_id' => $request->integer('category_id'),
            'name' => $request->string('name')->trim()->value(),
            'status' => $request->string('status')->lower()->value(),
            'featured' => $request->boolean('featured'),
            'short_description' => $request->input('short_description'),
            'full_description' => $request->input('full_description'),
        ];
    }

    private function syncRelations(Product $product, ProductRequest $request): void
    {
        $this->syncVariants($product, $request);
        $this->syncTechnicalDetails($product, $request);
        $this->syncImages($product, $request);
    }

    /**
     * Id-preserving upsert.
     *
     * A row that arrives with an id belonging to this product is updated in
     * place; a row without one is inserted; a row the form no longer contains
     * is deleted on its own. Variant ids therefore survive an edit, which
     * matters the moment an order line references one.
     *
     * The lookup is scoped to the product's own variants, so an id that is
     * wrong, stale or forged falls through to an insert rather than reaching
     * another product's row.
     */
    private function syncVariants(Product $product, ProductRequest $request): void
    {
        $existing = $product->variants()->get()->keyBy('id');
        $keptIds = [];

        foreach ($request->input('variants', []) as $index => $row) {
            $isKit = filter_var($row['is_kit'] ?? false, FILTER_VALIDATE_BOOLEAN);

            $attributes = [
                'label' => $row['label'],
                'price' => $row['price'],
                'stock' => $row['stock'],
                'is_kit' => $isKit,
                'kit_inclusions' => $isKit
                    ? array_values(array_filter($row['kit_inclusions'] ?? []))
                    : [],
                'sort_order' => $index,
            ];

            $variant = $existing->get((int) ($row['id'] ?? 0));

            if ($variant) {
                $variant->update($attributes);
                $keptIds[] = $variant->id;

                continue;
            }

            $keptIds[] = $product->variants()->create($attributes)->id;
        }

        $product->variants()->whereNotIn('id', $keptIds ?: [0])->delete();
    }

    /**
     * Same upsert as variants, additionally scoped by type: an id sent in the
     * purity list can only ever match a purity row.
     */
    private function syncTechnicalDetails(Product $product, ProductRequest $request): void
    {
        $existing = $product->technicalDetails()->get();
        $keptIds = [];

        foreach ([
            ProductTechnicalDetail::TYPE_PURITY => $request->input('purity', []),
            ProductTechnicalDetail::TYPE_STORAGE => $request->input('storage', []),
        ] as $type => $rows) {
            $ofType = $existing->where('type', $type)->keyBy('id');

            foreach ($rows as $index => $row) {
                $attributes = [
                    'type' => $type,
                    'label' => $row['label'] ?? null,
                    'value' => $row['value'],
                    'sort_order' => $index,
                ];

                $detail = $ofType->get((int) ($row['id'] ?? 0));

                if ($detail) {
                    $detail->update($attributes);
                    $keptIds[] = $detail->id;

                    continue;
                }

                $keptIds[] = $product->technicalDetails()->create($attributes)->id;
            }
        }

        $product->technicalDetails()->whereNotIn('id', $keptIds ?: [0])->delete();
    }

    private function syncImages(Product $product, ProductRequest $request): void
    {
        $keptIds = array_map('intval', $request->input('kept_image_ids', []));

        foreach ($product->images()->whereNotIn('id', $keptIds ?: [0])->get() as $image) {
            Storage::disk('public')->delete($image->path);
            $image->delete();
        }

        // Order follows the gallery, so the first tile stays the main image.
        foreach ($keptIds as $index => $id) {
            $product->images()->whereKey($id)->update(['sort_order' => $index]);
        }

        $offset = count($keptIds);

        foreach ($request->file('new_images', []) as $index => $file) {
            $product->images()->create([
                'path' => $file->store('products', 'public'),
                'sort_order' => $offset + $index,
            ]);
        }
    }
}
