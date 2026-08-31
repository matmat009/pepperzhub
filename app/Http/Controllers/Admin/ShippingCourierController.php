<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ShippingCourierRequest;
use App\Models\ShippingCourier;
use App\Models\ShippingRegion;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Admin CRUD for couriers and their flat regional rates.
 *
 * A courier and its regions are saved together in one request, the same way a
 * product is saved with its variants — and, importantly, with the same
 * id-preserving upsert. See syncRegions.
 *
 * Deletion carries no referential guard, for the same structural reason as
 * PaymentMethodController: orders.shipping_courier_id and shipping_region_id
 * are both nullOnDelete, and every order snapshots shipping_courier_name and
 * shipping_region_label at creation time.
 */
class ShippingCourierController extends Controller
{
    /**
     * @return array<string, mixed>
     */
    private function toRow(ShippingCourier $courier): array
    {
        return [
            'id' => $courier->id,
            'name' => $courier->name,
            'is_active' => (bool) $courier->is_active,
            'sort_order' => (int) $courier->sort_order,
            'regions' => $courier->regions
                ->map(fn (ShippingRegion $region) => [
                    'id' => $region->id,
                    'name' => $region->name,
                    'note' => $region->note,
                    'rate' => (float) $region->rate,
                    'is_active' => (bool) $region->is_active,
                    'sort_order' => (int) $region->sort_order,
                ])
                ->values()
                ->all(),
        ];
    }

    public function index(): Response
    {
        // Inactive couriers and regions included: this screen reactivates them.
        $couriers = ShippingCourier::query()
            ->with('regions')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get()
            ->map(fn (ShippingCourier $courier) => $this->toRow($courier))
            ->all();

        return Inertia::render('admin/shipping-couriers/Index', [
            'couriers' => $couriers,
        ]);
    }

    public function store(ShippingCourierRequest $request): RedirectResponse
    {
        $courier = ShippingCourier::create($this->attributes($request));

        $this->syncRegions($courier, $request);

        $this->toast('Courier created.');

        return back();
    }

    public function update(ShippingCourierRequest $request, ShippingCourier $shippingCourier): RedirectResponse
    {
        $shippingCourier->update($this->attributes($request));

        $this->syncRegions($shippingCourier, $request);

        $this->toast('Courier updated.');

        return back();
    }

    /**
     * Deleting a courier takes its regions with it.
     *
     * Verified against the live schema, not assumed:
     * shipping_regions.shipping_courier_id is declared
     * `->constrained()->cascadeOnDelete()` in
     * 2026_08_30_000003_create_shipping_regions_table, and the database reports
     * DELETE_RULE = CASCADE for that constraint. Orders survive either way —
     * their own FKs are nullOnDelete and their labels are snapshotted.
     */
    public function destroy(ShippingCourier $shippingCourier): RedirectResponse
    {
        $shippingCourier->delete();

        $this->toast('Courier deleted. Existing orders keep their own copy of the courier and region.');

        return back();
    }

    /**
     * Remove one region without touching the rest of the courier.
     *
     * Scoped through the relation so an id belonging to a different courier
     * 404s rather than deleting someone else's row.
     */
    public function destroyRegion(ShippingCourier $shippingCourier, int $region): RedirectResponse
    {
        $shippingCourier->regions()->whereKey($region)->firstOrFail()->delete();

        $this->toast('Region removed. Existing orders keep their own copy of the region.');

        return back();
    }

    /**
     * @return array<string, mixed>
     */
    private function attributes(ShippingCourierRequest $request): array
    {
        $validated = $request->validated();

        return [
            'name' => $validated['name'],
            'is_active' => $validated['is_active'] ?? true,
            'sort_order' => $validated['sort_order'],
        ];
    }

    /**
     * Id-preserving upsert, identical in shape to
     * ProductController::syncVariants.
     *
     * Rows carrying a known id are updated in place, rows without one are
     * inserted, and only what was actually dropped is deleted. Never
     * delete-all-then-reinsert: orders.shipping_region_id points at these rows,
     * and reissuing ids would silently repoint historical orders at whichever
     * region happened to land on the old number — the same bug class already
     * fixed once for order_items.product_variant_id.
     *
     * An id belonging to a different courier is not in $existing, so it falls
     * through to the insert branch rather than hijacking that courier's row.
     */
    private function syncRegions(ShippingCourier $courier, ShippingCourierRequest $request): void
    {
        $existing = $courier->regions()->get()->keyBy('id');
        $keptIds = [];

        foreach ($request->input('regions', []) as $index => $row) {
            $attributes = [
                'name' => $row['name'],
                'note' => blank($row['note'] ?? null) ? null : $row['note'],
                'rate' => $row['rate'],
                'is_active' => filter_var($row['is_active'] ?? true, FILTER_VALIDATE_BOOLEAN),
                // Order follows the form, matching how variants are sequenced.
                'sort_order' => $index,
            ];

            $region = $existing->get((int) ($row['id'] ?? 0));

            if ($region) {
                $region->update($attributes);
                $keptIds[] = $region->id;

                continue;
            }

            $keptIds[] = $courier->regions()->create($attributes)->id;
        }

        $courier->regions()->whereNotIn('id', $keptIds ?: [0])->delete();
    }
}
