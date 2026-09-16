<?php

namespace Tests\Feature\Storefront;

use App\Models\Category;
use App\Models\Product;
use App\Models\Review;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * What reaches a public page, and what does not.
 *
 * Three surfaces show reviews — product details, the standalone /reviews list,
 * and the homepage showcase. An inactive review passing on one page and not
 * the others would be the bug worth catching.
 */
class ReviewVisibilityTest extends TestCase
{
    use RefreshDatabase;

    private function product(string $name = 'BPC-157', string $status = 'active'): Product
    {
        $category = Category::firstOrCreate(
            ['slug' => 'healing'],
            ['name' => 'Healing'],
        );

        return Product::create([
            'category_id' => $category->id,
            'name' => $name,
            'status' => $status,
            'short_description' => 'Peptide.',
        ]);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function review(array $overrides = []): Review
    {
        return Review::create(array_merge([
            'product_id' => null,
            'customer_name' => 'Maria S.',
            'title' => 'Great results',
            'description' => 'Exactly as described.',
            'is_active' => true,
            'sort_order' => 0,
        ], $overrides));
    }

    // ----- product detail page ----------------------------------------------

    public function test_a_tagged_review_shows_on_its_product_page(): void
    {
        $product = $this->product();
        $this->review(['product_id' => $product->id, 'title' => 'Tagged one']);

        $this->get(route('storefront.products.show', $product->slug))
            ->assertOk()
            ->assertInertia(
                fn ($page) => $page
                    ->has('product.reviews', 1)
                    ->where('product.reviews.0.title', 'Tagged one')
            );
    }

    /**
     * The "nothing extra for a product with none" rule the Protocol section
     * already follows — the section only renders when the array is non-empty.
     */
    public function test_a_product_with_no_reviews_sends_an_empty_list(): void
    {
        $product = $this->product();

        $this->get(route('storefront.products.show', $product->slug))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->has('product.reviews', 0));
    }

    public function test_an_inactive_review_does_not_reach_the_product_page(): void
    {
        $product = $this->product();
        $this->review(['product_id' => $product->id, 'is_active' => false]);

        $this->get(route('storefront.products.show', $product->slug))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->has('product.reviews', 0));
    }

    /** Another product's review must not leak onto this one. */
    public function test_a_product_page_shows_only_its_own_reviews(): void
    {
        $mine = $this->product('BPC-157');
        $other = $this->product('Semaglutide');

        $this->review(['product_id' => $mine->id, 'title' => 'Mine']);
        $this->review(['product_id' => $other->id, 'title' => 'Theirs']);

        $this->get(route('storefront.products.show', $mine->slug))
            ->assertOk()
            ->assertInertia(
                fn ($page) => $page
                    ->has('product.reviews', 1)
                    ->where('product.reviews.0.title', 'Mine')
            );
    }

    /** An untagged review belongs to /reviews only, never to a product page. */
    public function test_an_untagged_review_does_not_appear_on_any_product_page(): void
    {
        $product = $this->product();
        $this->review(['title' => 'About the shop']);

        $this->get(route('storefront.products.show', $product->slug))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->has('product.reviews', 0));
    }

    // ----- standalone reviews page ------------------------------------------

    public function test_the_reviews_page_lists_tagged_and_untagged_reviews(): void
    {
        $product = $this->product();

        $tagged = $this->review(['product_id' => $product->id, 'title' => 'Tagged', 'sort_order' => 0]);
        $this->review(['title' => 'Untagged', 'sort_order' => 1]);

        $this->get(route('storefront.reviews'))
            ->assertOk()
            ->assertInertia(
                fn ($page) => $page
                    ->component('storefront/Reviews')
                    ->has('reviews', 2)
                    ->where('reviews.0.title', 'Tagged')
                    ->where('reviews.0.created_at', $tagged->created_at->toDateString())
                    ->where('reviews.0.product_name', 'BPC-157')
                    ->where('reviews.1.title', 'Untagged')
                    ->where('reviews.1.product_name', null)
            );
    }

    public function test_an_inactive_review_does_not_reach_the_reviews_page(): void
    {
        $this->review(['title' => 'Shown']);
        $this->review(['title' => 'Hidden', 'is_active' => false]);

        $this->get(route('storefront.reviews'))
            ->assertOk()
            ->assertInertia(
                fn ($page) => $page
                    ->has('reviews', 1)
                    ->where('reviews.0.title', 'Shown')
            );
    }

    public function test_the_reviews_page_orders_by_sort_order(): void
    {
        $this->review(['title' => 'Third', 'sort_order' => 2]);
        $this->review(['title' => 'First', 'sort_order' => 0]);
        $this->review(['title' => 'Second', 'sort_order' => 1]);

        $this->get(route('storefront.reviews'))
            ->assertOk()
            ->assertInertia(
                fn ($page) => $page
                    ->where('reviews.0.title', 'First')
                    ->where('reviews.1.title', 'Second')
                    ->where('reviews.2.title', 'Third')
            );
    }

    /**
     * A review outlives the product it mentioned. It stays listed, simply
     * without the product chip.
     */
    public function test_a_review_survives_its_product_being_deleted(): void
    {
        $product = $this->product();
        $this->review(['product_id' => $product->id, 'title' => 'Outlives it']);

        $product->delete();

        $this->get(route('storefront.reviews'))
            ->assertOk()
            ->assertInertia(
                fn ($page) => $page
                    ->has('reviews', 1)
                    ->where('reviews.0.title', 'Outlives it')
                    ->where('reviews.0.product_name', null)
            );
    }

    /** No auth anywhere on the storefront; this page is no exception. */
    public function test_the_reviews_page_is_public(): void
    {
        $this->get(route('storefront.reviews'))->assertOk();
    }

    // ----- homepage showcase ------------------------------------------------

    public function test_the_homepage_shows_the_three_newest_active_reviews(): void
    {
        $longDescription = str_repeat('A detailed customer review. ', 24);
        $oldest = $this->review(['title' => 'Oldest']);
        $second = $this->review(['title' => 'Second newest']);
        $third = $this->review(['title' => 'Third newest']);
        $newest = $this->review([
            'title' => 'Newest',
            'description' => $longDescription,
        ]);
        $hidden = $this->review([
            'title' => 'Unpublished newest',
            'is_active' => false,
        ]);

        foreach ([$oldest, $second, $third, $newest, $hidden] as $index => $review) {
            $review->forceFill(['created_at' => now()->addMinutes($index)])->save();
        }

        $this->get(route('home'))
            ->assertOk()
            ->assertInertia(
                fn ($page) => $page
                    ->has('reviews', 3)
                    ->where('reviews.0.title', 'Newest')
                    ->where('reviews.0.description', $longDescription)
                    ->where('reviews.1.title', 'Third newest')
                    ->where('reviews.2.title', 'Second newest')
            );
    }

    public function test_the_homepage_review_payload_uses_public_images_and_preserves_anonymity(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('reviews/customer-note.jpg', 'image');

        $this->review([
            'customer_name' => null,
            'title' => 'Anonymous note',
            'image_path' => 'reviews/customer-note.jpg',
        ]);

        $this->get(route('home'))
            ->assertOk()
            ->assertInertia(
                fn ($page) => $page
                    ->has('reviews', 1)
                    ->where('reviews.0.customer_name', null)
                    ->where(
                        'reviews.0.image_url',
                        Storage::disk('public')->url('reviews/customer-note.jpg'),
                    )
                    ->missing('reviews.0.image_path')
            );
    }

    public function test_the_homepage_sends_no_review_cards_when_none_are_published(): void
    {
        $this->review(['is_active' => false]);

        $this->get(route('home'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->has('reviews', 0));
    }
}
