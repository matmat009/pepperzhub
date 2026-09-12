<?php

namespace Tests\Feature\Storefront;

use App\Models\Category;
use App\Models\Product;
use App\Models\Review;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * What reaches a public page, and what does not.
 *
 * Two surfaces show reviews — the product detail page and the standalone
 * /reviews list — so every rule here is asserted against both. An inactive
 * review passing on one page and not the other would be the bug worth catching.
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

        $this->review(['product_id' => $product->id, 'title' => 'Tagged', 'sort_order' => 0]);
        $this->review(['title' => 'Untagged', 'sort_order' => 1]);

        $this->get(route('storefront.reviews'))
            ->assertOk()
            ->assertInertia(
                fn ($page) => $page
                    ->component('storefront/Reviews')
                    ->has('reviews', 2)
                    ->where('reviews.0.title', 'Tagged')
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
}
