<?php

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Models\Review;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Public testimonials.
 *
 * Every active review, whether or not it names a product. Unlike /protocols
 * there are no category tabs: a review is not reliably about a product, so
 * there is nothing consistent to group it under.
 */
class ReviewController extends Controller
{
    public function index(): Response
    {
        $reviews = Review::query()
            ->where('is_active', true)
            // The product is a label here, nothing more — only the name is read
            // off it, so the whole row is not worth loading separately.
            ->with('product:id,name,slug')
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get()
            ->map(fn (Review $review) => $this->toPayload($review))
            ->all();

        return Inertia::render('storefront/Reviews', [
            'reviews' => $reviews,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function toPayload(Review $review): array
    {
        return [
            'id' => $review->id,
            'customer_name' => $review->customer_name,
            'title' => $review->title,
            'description' => $review->description,
            'created_at' => $review->created_at?->toDateString(),
            'image_url' => $review->image_path
                ? Storage::disk('public')->url($review->image_path)
                : null,
            // Null for an untagged review, and for one whose product was
            // deleted — the page treats both the same way.
            'product_name' => $review->product?->name,
            'product_slug' => $review->product?->slug,
        ];
    }
}
