<?php

namespace Tests\Feature\Storefront;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ProductDescriptionTest extends TestCase
{
    use RefreshDatabase;

    private Category $category;

    protected function setUp(): void
    {
        parent::setUp();

        $this->category = Category::create([
            'name' => 'Healing',
            'slug' => 'healing',
        ]);
    }

    private function product(?string $short, ?string $full, bool $featured = false): Product
    {
        return Product::create([
            'category_id' => $this->category->id,
            'name' => 'BPC-157',
            'slug' => 'bpc-157',
            'status' => 'active',
            'featured' => $featured,
            'short_description' => $short,
            'full_description' => $full,
        ]);
    }

    public function test_catalog_payload_exposes_the_short_description_used_by_product_cards(): void
    {
        $this->product('Body protection compound.', null);

        $this->get(route('storefront.products.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('storefront/Catalog')
                ->where('products.0.short_description', 'Body protection compound.')
                ->where('products.0.full_description', ''),
            );
    }

    public function test_home_payload_exposes_short_descriptions_for_featured_cards(): void
    {
        $this->product('Featured product summary.', null, true);

        $this->get(route('home'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('storefront/Home')
                ->where('featured.0.short_description', 'Featured product summary.'),
            );
    }

    public function test_product_detail_keeps_both_descriptions_separate(): void
    {
        $product = $this->product(
            'Short storefront summary.',
            'Dedicated long-form description.',
        );

        $this->get(route('storefront.products.show', $product->slug))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('storefront/Product')
                ->where('product.short_description', 'Short storefront summary.')
                ->where('product.full_description', 'Dedicated long-form description.'),
            );
    }

    public function test_short_only_product_does_not_copy_short_text_into_full_description(): void
    {
        $product = $this->product('Only available description.', null);

        $this->get(route('storefront.products.show', $product->slug))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('storefront/Product')
                ->where('product.short_description', 'Only available description.')
                ->where('product.full_description', ''),
            );
    }

    public function test_storefront_components_enforce_the_description_visibility_contract(): void
    {
        // There is no Vue test runner in this repository, so keep the critical
        // SFC branches under a lightweight source contract instead.
        $card = file_get_contents(resource_path('js/components/storefront/ProductCard.vue'));
        $detail = file_get_contents(resource_path('js/pages/storefront/Product.vue'));

        $this->assertStringContainsString('{{ product.short_description }}', $card);
        $this->assertStringContainsString('v-if="shortDescription"', $detail);
        $this->assertStringContainsString('{{ shortDescription }}', $detail);
        $this->assertStringContainsString('v-if="fullDescription"', $detail);
        $this->assertStringContainsString('{{ shownDescription }}', $detail);
        $this->assertStringNotContainsString(
            'props.product.full_description || props.product.short_description',
            $detail,
        );
    }
}
