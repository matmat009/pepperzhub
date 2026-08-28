<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\CategoryRequest;
use App\Models\Category;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class CategoryController extends Controller
{
    public function index(): Response
    {
        $categories = Category::query()
            ->withCount('products')
            ->orderBy('name')
            ->get()
            ->map(fn (Category $category) => [
                'id' => $category->id,
                'name' => $category->name,
                'description' => (string) $category->description,
                'product_count' => $category->products_count,
                'created_at' => $category->created_at?->toDateString(),
            ])
            ->all();

        return Inertia::render('admin/products/categories/Index', [
            'categories' => $categories,
        ]);
    }

    public function store(CategoryRequest $request): RedirectResponse
    {
        Category::create($request->validated());

        $this->toast('Category created.');

        return back();
    }

    public function update(CategoryRequest $request, Category $category): RedirectResponse
    {
        $category->update($request->validated());

        $this->toast('Category updated.');

        return back();
    }

    /**
     * A category still holding products cannot be deleted until they are
     * reassigned. The products.category_id foreign key is restricted on delete,
     * so this check is the friendly face of a constraint the database enforces
     * either way.
     */
    public function destroy(Category $category): RedirectResponse
    {
        $count = $category->products()->count();

        if ($count > 0) {
            return back()->withErrors([
                'category' => sprintf(
                    'Reassign %s before deleting this category.',
                    $count === 1 ? 'this 1 product' : sprintf('these %d products', $count),
                ),
            ]);
        }

        $category->delete();

        $this->toast('Category deleted.');

        return back();
    }
}
