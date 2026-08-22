<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CategoryController extends Controller
{
    /**
     * Placeholder categories.
     *
     * `product_count` mirrors the catalogue in ProductController: each of the
     * five dummy products sits in one of these, and Research deliberately has
     * none so the delete flow can be exercised both ways.
     *
     * @return array<int, array<string, mixed>>
     */
    private function categories(): array
    {
        return [
            [
                'id' => 1,
                'name' => 'Recovery',
                'description' => 'Peptides supporting tissue repair and post-training recovery.',
                'product_count' => 1,
                'created_at' => '2025-10-02',
            ],
            [
                'id' => 2,
                'name' => 'Healing',
                'description' => 'Compounds targeting wound healing and gut lining repair.',
                'product_count' => 1,
                'created_at' => '2025-10-02',
            ],
            [
                'id' => 3,
                'name' => 'Growth',
                'description' => 'Growth hormone secretagogues and related compounds.',
                'product_count' => 1,
                'created_at' => '2025-11-18',
            ],
            [
                'id' => 4,
                'name' => 'Research',
                'description' => 'Reference compounds held for laboratory use only.',
                'product_count' => 0,
                'created_at' => '2026-01-07',
            ],
            [
                'id' => 5,
                'name' => 'Metabolic',
                'description' => 'GLP-1 agonists and metabolic regulation compounds.',
                'product_count' => 1,
                'created_at' => '2026-02-24',
            ],
        ];
    }

    public function index(): Response
    {
        return Inertia::render('admin/products/categories/Index', [
            'categories' => $this->categories(),
        ]);
    }

    /**
     * Stub: no persistence yet.
     */
    public function store(Request $request): RedirectResponse
    {
        return back()->with('success', 'Category created.');
    }

    /**
     * Stub: no persistence yet.
     */
    public function update(Request $request, int $category): RedirectResponse
    {
        return back()->with('success', 'Category updated.');
    }

    /**
     * Stub, but the guard is real: a category still holding products cannot be
     * deleted until they are reassigned. The UI blocks this too; this is the
     * server-side backstop.
     */
    public function destroy(int $category): RedirectResponse
    {
        $match = collect($this->categories())->firstWhere('id', $category);

        abort_if($match === null, 404);

        if ($match['product_count'] > 0) {
            return back()->withErrors([
                'category' => sprintf(
                    'Reassign %s before deleting this category.',
                    $match['product_count'] === 1
                        ? 'this 1 product'
                        : sprintf('these %d products', $match['product_count']),
                ),
            ]);
        }

        return back()->with('success', 'Category deleted.');
    }
}
