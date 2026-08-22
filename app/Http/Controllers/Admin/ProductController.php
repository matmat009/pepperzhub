<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ProductController extends Controller
{
    /**
     * Placeholder catalogue.
     *
     * This is the seam that later becomes an Eloquent query. Keep the shape in
     * sync with the `Product` type in
     * resources/js/pages/admin/products/all-products/types.ts.
     *
     * @return array<int, array<string, mixed>>
     */
    private function products(): array
    {
        return [
            [
                'id' => 1,
                'name' => 'BPC-157',
                'description' => 'Body protection compound, 5mg lyophilised vial.',
                'type' => 'Vial',
                'category' => 'Healing',
                'purity' => 99.2,
                'status' => 'Active',
                'stock' => 142,
                'price' => 45.00,
                'thumbnail' => null,
                'created_at' => '2026-01-14',
            ],
            [
                'id' => 2,
                'name' => 'TB-500',
                'description' => 'Thymosin beta-4 fragment, 10mg research kit.',
                'type' => 'Kit',
                'category' => 'Recovery',
                'purity' => 98.7,
                'status' => 'Active',
                'stock' => 64,
                'price' => 89.50,
                'thumbnail' => null,
                'created_at' => '2026-02-03',
            ],
            [
                'id' => 3,
                'name' => 'GHK-Cu',
                'description' => 'Copper peptide complex, 50mg vial.',
                'type' => 'Vial',
                'category' => 'Cosmetic',
                'purity' => 99.6,
                'status' => 'Draft',
                'stock' => 0,
                'price' => 62.00,
                'thumbnail' => null,
                'created_at' => '2026-03-22',
            ],
            [
                'id' => 4,
                'name' => 'Ipamorelin',
                'description' => 'Selective GH secretagogue, 2mg vial.',
                'type' => 'Vial',
                'category' => 'Growth',
                'purity' => 97.9,
                'status' => 'Archived',
                'stock' => 8,
                'price' => 38.75,
                'thumbnail' => null,
                'created_at' => '2025-11-09',
            ],
            [
                'id' => 5,
                'name' => 'Semaglutide',
                'description' => 'GLP-1 receptor agonist, 5mg reconstitution kit.',
                'type' => 'Kit',
                'category' => 'Metabolic',
                'purity' => 99.8,
                'status' => 'Active',
                'stock' => 27,
                'price' => 175.00,
                'thumbnail' => null,
                'created_at' => '2026-04-18',
            ],
        ];
    }

    public function index(): Response
    {
        return Inertia::render('admin/products/all-products/Index', [
            'products' => $this->products(),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('admin/products/all-products/Create');
    }

    public function show(int $product): Response
    {
        $match = collect($this->products())->firstWhere('id', $product);

        abort_if($match === null, 404);

        return Inertia::render('admin/products/all-products/Show', [
            'product' => $match,
        ]);
    }

    /**
     * Stub: no persistence yet.
     */
    public function store(Request $request): RedirectResponse
    {
        return to_route('admin.products.index')
            ->with('success', 'Product created.');
    }

    /**
     * Stub: no persistence yet.
     */
    public function update(Request $request, int $product): RedirectResponse
    {
        return to_route('admin.products.show', $product)
            ->with('success', 'Product updated.');
    }

    /**
     * Stub: no persistence yet.
     */
    public function destroy(int $product): RedirectResponse
    {
        return to_route('admin.products.index')
            ->with('success', 'Product deleted.');
    }
}
