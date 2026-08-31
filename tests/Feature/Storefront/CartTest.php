<?php

namespace Tests\Feature\Storefront;

use App\Models\Category;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Support\SessionCart;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CartTest extends TestCase
{
    use RefreshDatabase;

    private function variant(int $stock = 10, string $status = 'active', float $price = 2450): ProductVariant
    {
        $category = Category::firstOrCreate(['name' => 'Healing'], ['slug' => 'healing']);

        $product = Product::create([
            'category_id' => $category->id,
            'name' => 'BPC-157 '.uniqid(),
            'slug' => 'bpc-'.uniqid(),
            'status' => $status,
            'short_description' => 'Peptide.',
        ]);

        return $product->variants()->create([
            'label' => '5mg vial',
            'price' => $price,
            'stock' => $stock,
            'is_kit' => false,
            'sort_order' => 0,
        ]);
    }

    public function test_adding_the_same_variant_twice_merges_rather_than_duplicating(): void
    {
        $variant = $this->variant(stock: 10);

        $this->post(route('storefront.cart.store'), ['variant_id' => $variant->id, 'quantity' => 2]);
        $this->post(route('storefront.cart.store'), ['variant_id' => $variant->id, 'quantity' => 3]);

        $this->assertSame([$variant->id => 5], session(SessionCart::SESSION_KEY));
    }

    public function test_quantity_is_clamped_to_live_stock(): void
    {
        $variant = $this->variant(stock: 4);

        $this->post(route('storefront.cart.store'), ['variant_id' => $variant->id, 'quantity' => 9]);

        $this->assertSame([$variant->id => 4], session(SessionCart::SESSION_KEY));
    }

    public function test_update_sets_an_explicit_quantity_and_zero_removes_the_line(): void
    {
        $variant = $this->variant(stock: 10);
        $this->post(route('storefront.cart.store'), ['variant_id' => $variant->id, 'quantity' => 2]);

        $this->patch(route('storefront.cart.update'), ['variant_id' => $variant->id, 'quantity' => 7]);
        $this->assertSame([$variant->id => 7], session(SessionCart::SESSION_KEY));

        $this->patch(route('storefront.cart.update'), ['variant_id' => $variant->id, 'quantity' => 0]);
        $this->assertSame([], session(SessionCart::SESSION_KEY));
    }

    public function test_destroy_removes_a_line(): void
    {
        $variant = $this->variant();
        $this->post(route('storefront.cart.store'), ['variant_id' => $variant->id, 'quantity' => 1]);

        $this->delete(route('storefront.cart.destroy'), ['variant_id' => $variant->id]);

        $this->assertSame([], session(SessionCart::SESSION_KEY));
    }

    /** The client sends a variant id and a quantity — never a price. */
    public function test_cart_prices_are_hydrated_from_the_database(): void
    {
        $variant = $this->variant(stock: 10, price: 2450);

        $this->withSession([SessionCart::SESSION_KEY => [$variant->id => 2]])
            ->get(route('storefront.cart'))
            ->assertInertia(fn ($page) => $page
                ->where('lines.0.unit_price', 2450)
                ->where('lines.0.line_total', 4900)
                ->where('subtotal', 4900)
            );
    }

    public function test_a_price_change_is_reflected_on_the_next_read(): void
    {
        $variant = $this->variant(stock: 10, price: 2450);
        $variant->update(['price' => 3000]);

        $this->withSession([SessionCart::SESSION_KEY => [$variant->id => 1]])
            ->get(route('storefront.cart'))
            ->assertInertia(fn ($page) => $page->where('subtotal', 3000));
    }

    public function test_a_line_whose_product_is_no_longer_active_is_dropped(): void
    {
        $variant = $this->variant(stock: 10, status: 'draft');

        $this->withSession([SessionCart::SESSION_KEY => [$variant->id => 1]])
            ->get(route('storefront.cart'))
            ->assertInertia(fn ($page) => $page->where('lines', [])->where('subtotal', 0));
    }

    public function test_an_out_of_stock_variant_cannot_be_added(): void
    {
        $variant = $this->variant(stock: 0);

        $this->post(route('storefront.cart.store'), ['variant_id' => $variant->id, 'quantity' => 1]);

        $this->assertSame([], session(SessionCart::SESSION_KEY, []));
    }

    public function test_the_shared_cart_count_is_exposed_to_every_page(): void
    {
        $variant = $this->variant(stock: 10);

        $this->withSession([SessionCart::SESSION_KEY => [$variant->id => 3]])
            ->get(route('storefront.products.index'))
            ->assertInertia(fn ($page) => $page->where('cartCount', 3));
    }
}
