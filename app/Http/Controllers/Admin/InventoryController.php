<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ProductVariant;
use App\Models\StockMovement;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Stock, per format.
 *
 * One row per product_variant, never per product. Stock is a property of the
 * format, and summing a product's formats into a single row would hide the one
 * thing this screen exists to catch: a 5mg vial down to its last unit while a
 * well-stocked 10mg sibling keeps the product's total looking comfortable.
 *
 * Everything here reads from and writes to real columns. The screen previously
 * rendered a hardcoded list and its adjust action returned a toast without
 * touching anything, which is why it was kept out of the sidebar.
 */
class InventoryController extends Controller
{
    public function index(): Response
    {
        $variants = ProductVariant::query()
            ->with(['product.category', 'product.images', 'movements'])
            // Joined rather than sorted after the fact so the ordering is the
            // database's: by product, then by the format order the product
            // form already established.
            ->join('products', 'products.id', '=', 'product_variants.product_id')
            ->orderBy('products.name')
            ->orderBy('product_variants.sort_order')
            ->select('product_variants.*')
            ->get();

        return Inertia::render('admin/products/inventory/Index', [
            'items' => $variants
                ->map(fn (ProductVariant $variant) => $this->toPayload($variant))
                ->all(),
        ]);
    }

    /**
     * Apply an adjustment and record it.
     *
     * Locked for the same reason checkout and the cancel path lock: an order
     * landing mid-adjustment would otherwise read a total that is about to be
     * overwritten, and one of the two changes would vanish. Reading the stock
     * inside the transaction — not from the row the page was rendered with —
     * is what makes the delta apply to what is actually on the shelf.
     */
    public function adjust(Request $request, ProductVariant $variant): RedirectResponse
    {
        $data = $request->validate([
            // not_in:0 rather than min:1 — the delta is signed, and a zero
            // movement is an entry in the log that says nothing happened.
            'delta' => ['required', 'integer', 'not_in:0'],
            'reason' => ['required', 'string', Rule::in(StockMovement::MANUAL_REASONS)],
            'note' => ['nullable', 'string', 'max:500'],
        ], [
            'reason.in' => 'Choose one of the manual adjustment reasons.',
        ]);

        DB::transaction(function () use ($variant, $data): void {
            /** @var ProductVariant $locked */
            $locked = ProductVariant::query()
                ->whereKey($variant->id)
                ->lockForUpdate()
                ->firstOrFail();

            $before = (int) $locked->stock;
            /*
             * Clamped at zero, matching the preview the dialog showed before
             * the click — and the column is unsigned, so a negative total is
             * not storable anyway. The movement records the delta that was
             * actually applied rather than the one that was asked for, so
             * resulting_stock still reconciles with the entry above it.
             */
            $after = max(0, $before + (int) $data['delta']);

            $locked->forceFill(['stock' => $after])->save();

            StockMovement::record(
                $locked,
                $after - $before,
                $data['reason'],
                filled($data['note'] ?? null) ? $data['note'] : null,
            );
        });

        $this->toast('Stock adjusted.');

        return back();
    }

    /**
     * Serialised shape consumed by
     * resources/js/pages/admin/products/inventory/types.ts.
     *
     * @return array<string, mixed>
     */
    private function toPayload(ProductVariant $variant): array
    {
        $movements = $variant->movements;

        return [
            'id' => $variant->id,
            'product_name' => $variant->product->name,
            'variant_label' => $variant->label,
            'type' => $variant->is_kit ? 'Kit' : 'Vial',
            'category' => $variant->product->category?->name ?? '',
            'thumbnail' => $variant->product->images
                ->sortBy('sort_order')
                ->first()
                ?->url(),
            'stock' => (int) $variant->stock,
            // Computed here from ProductVariant::LOW_STOCK_THRESHOLD, so the
            // client never carries a threshold of its own — the reason the
            // placeholder screen's 10 and the real 5 drifted apart before.
            'status' => $variant->stockStatus(),
            /*
             * When the stock last moved, which is not the same as when the row
             * was last written: a price edit touches updated_at without
             * changing a unit. The variant's own timestamp is the fallback for
             * a format nothing has happened to yet.
             */
            'updated_at' => ($movements->last()?->created_at ?? $variant->updated_at)
                ?->toDateString(),
            'history' => $movements
                ->map(fn (StockMovement $movement) => [
                    'id' => $movement->id,
                    'date' => $movement->created_at?->toDateString(),
                    'delta' => $movement->delta,
                    'reason' => $movement->reason,
                    'resulting_stock' => $movement->resulting_stock,
                    'note' => $movement->note,
                ])
                ->values()
                ->all(),
        ];
    }
}
