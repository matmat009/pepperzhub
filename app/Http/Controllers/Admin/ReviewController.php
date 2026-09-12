<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ReviewRequest;
use App\Models\Product;
use App\Models\Review;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Admin CRUD for customer testimonials.
 *
 * Structurally the payment-methods screen: its own nav item, one image on the
 * public disk, is_active plus sort_order, and a hard delete with no referential
 * guard — nothing downstream depends on a review surviving, so there is nothing
 * for a guard to protect.
 *
 * The product tag is optional at every level. A review about the shop in
 * general has none, and reviews.product_id is nullOnDelete, so deleting a
 * product untags its reviews rather than taking them with it.
 */
class ReviewController extends Controller
{
    /**
     * @return array<string, mixed>
     */
    private function toRow(Review $review): array
    {
        return [
            'id' => $review->id,
            'product_id' => $review->product_id,
            // Resolved through the relation, so a review whose product was
            // deleted reads as untagged rather than pointing at a dead id.
            'product_name' => $review->product?->name,
            'customer_name' => $review->customer_name,
            'title' => $review->title,
            'description' => $review->description,
            // A URL, never the raw path — the client has no use for where on
            // disk this sits, matching payment methods and order proofs.
            'image_url' => $review->image_path
                ? Storage::disk('public')->url($review->image_path)
                : null,
            'is_active' => (bool) $review->is_active,
            'sort_order' => (int) $review->sort_order,
        ];
    }

    public function index(): Response
    {
        // Inactive rows included: this screen is where they are reactivated.
        $reviews = Review::query()
            ->with('product')
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get()
            ->map(fn (Review $review) => $this->toRow($review))
            ->all();

        return Inertia::render('admin/reviews/Index', [
            'reviews' => $reviews,
            'products' => $this->productOptions(),
        ]);
    }

    public function store(ReviewRequest $request): RedirectResponse
    {
        $review = Review::create($this->attributes($request));

        $this->syncImage($review, $request);

        $this->toast('Review created.');

        return back();
    }

    public function update(ReviewRequest $request, Review $review): RedirectResponse
    {
        $review->update($this->attributes($request));

        $this->syncImage($review, $request);

        $this->toast('Review updated.');

        return back();
    }

    /**
     * Hard delete. See the class docblock for why no referential guard applies.
     */
    public function destroy(Review $review): RedirectResponse
    {
        if ($review->image_path) {
            Storage::disk('public')->delete($review->image_path);
        }

        $review->delete();

        $this->toast('Review deleted.');

        return back();
    }

    /**
     * Every product, not just active ones: a review can outlive a product being
     * taken off sale, and the select has to be able to show what it is tagged
     * with rather than silently dropping to untagged on the next save.
     *
     * @return array<int, array<string, mixed>>
     */
    private function productOptions(): array
    {
        return Product::query()
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (Product $product) => [
                'id' => $product->id,
                'name' => $product->name,
            ])
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function attributes(ReviewRequest $request): array
    {
        $validated = $request->validated();

        return [
            'product_id' => $validated['product_id'] ?? null,
            'customer_name' => $validated['customer_name'] ?? null,
            'title' => $validated['title'],
            'description' => $validated['description'],
            'is_active' => $validated['is_active'] ?? true,
            'sort_order' => $validated['sort_order'],
        ];
    }

    /**
     * Replace, remove, or leave the photo alone.
     *
     * Same shape as PaymentMethodController::syncQrCode: the old file is
     * deleted from the public disk whenever it stops being referenced, so
     * replacing a photo does not leave the previous one orphaned.
     */
    private function syncImage(Review $review, ReviewRequest $request): void
    {
        $file = $request->file('image');
        $removing = $request->boolean('remove_image');

        if (! $file && ! $removing) {
            return;
        }

        if ($review->image_path) {
            Storage::disk('public')->delete($review->image_path);
        }

        $review->update([
            'image_path' => $file ? $file->store('reviews', 'public') : null,
        ]);
    }
}
