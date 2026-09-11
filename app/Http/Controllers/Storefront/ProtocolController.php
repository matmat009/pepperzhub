<?php

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\ProductTechnicalDetail;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Public protocol reference.
 *
 * Only products the owner has actually written protocol data for reach this
 * page. A product with no dosage, frequency, duration or protocol note is left
 * out of the payload entirely rather than rendered against generic dosing
 * boilerplate — for a research compound, inventing that text is not a display
 * concern.
 *
 * The general injection and storage guidance at the top of the page is fixed
 * copy in the Vue component, not admin-editable, so none of it is sent here.
 */
class ProtocolController extends Controller
{
    public function index(): Response
    {
        // Built first, then filtered on the built row rather than in SQL: the
        // test for "has a protocol" is then literally the payload the page
        // renders, so a product can never be listed with nothing to show.
        $products = Product::query()
            ->where('status', 'active')
            ->with(['category', 'technicalDetails'])
            ->orderBy('name')
            ->get()
            ->map(fn (Product $product) => $this->toPayload($product))
            ->filter(fn (array $row) => $this->hasProtocolData($row))
            ->values()
            ->all();

        return Inertia::render('storefront/Protocols', [
            'products' => $products,
            'categories' => $this->categoryNames($products),
        ]);
    }

    /**
     * A slice of the product, not the catalogue shape: this page shows no
     * price, image or format, so it loads none of them.
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
            'dosage' => (string) $product->dosage,
            'frequency' => (string) $product->frequency,
            'duration' => (string) $product->duration,
            // Plain lines — a note has no label, so only its text travels.
            'protocol_notes' => $this->details($product, ProductTechnicalDetail::TYPE_PROTOCOL)
                ->pluck('value')
                ->all(),
            'storage_instructions' => $this->details($product, ProductTechnicalDetail::TYPE_STORAGE)
                ->map(fn ($detail) => [
                    'id' => (string) $detail->id,
                    'label' => (string) $detail->label,
                    'value' => $detail->value,
                ])
                ->values()
                ->all(),
        ];
    }

    /**
     * Filters the already-loaded relation rather than issuing another query.
     *
     * @return Collection<int, ProductTechnicalDetail>
     */
    private function details(Product $product, string $type): Collection
    {
        return $product->technicalDetails
            ->where('type', $type)
            ->sortBy('sort_order')
            ->values();
    }

    /**
     * The page's own inclusion rule. Storage instructions deliberately do not
     * count: nearly every product carries those, and on their own they are not
     * a protocol.
     *
     * @param  array<string, mixed>  $row
     */
    private function hasProtocolData(array $row): bool
    {
        return filled($row['dosage'])
            || filled($row['frequency'])
            || filled($row['duration'])
            || $row['protocol_notes'] !== [];
    }

    /**
     * Tabs are derived from the listed products, so the filter can never offer
     * a category that resolves to an empty list.
     *
     * @param  array<int, array<string, mixed>>  $products
     * @return array<int, string>
     */
    private function categoryNames(array $products): array
    {
        return collect($products)
            ->pluck('category')
            ->filter()
            ->unique()
            ->sort()
            ->values()
            ->all();
    }
}
