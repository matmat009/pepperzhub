<?php

namespace Tests\Feature\Storefront;

use App\Models\Category;
use App\Models\Product;
use App\Models\ProductTechnicalDetail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * A product reaches /protocols only if the owner actually wrote protocol data
 * for it. Nothing is ever filled in on a product's behalf, so a product with
 * none of it is absent from the payload rather than listed with placeholder
 * dosing text.
 */
class ProtocolsPageTest extends TestCase
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

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function product(string $name, string $slug, array $attributes = []): Product
    {
        return Product::create([
            'category_id' => $this->category->id,
            'name' => $name,
            'slug' => $slug,
            'status' => 'active',
            'short_description' => 'Body protection compound.',
            ...$attributes,
        ]);
    }

    public function test_a_product_with_no_protocol_data_is_absent_from_the_payload(): void
    {
        $this->product('BPC-157', 'bpc-157');

        $this->get(route('storefront.protocols'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('storefront/Protocols')
                ->has('products', 0)
                ->where('categories', []),
            );
    }

    public function test_a_product_with_only_protocol_notes_is_included(): void
    {
        $product = $this->product('TB-500', 'tb-500');
        $product->technicalDetails()->create([
            'type' => ProductTechnicalDetail::TYPE_PROTOCOL,
            'label' => null,
            'value' => 'Reconstitute with 2ml bacteriostatic water.',
            'sort_order' => 0,
        ]);

        $this->get(route('storefront.protocols'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('storefront/Protocols')
                ->has('products', 1)
                ->where('products.0.name', 'TB-500')
                // No dosage, frequency or duration — the page renders nothing
                // for these rather than inventing a line.
                ->where('products.0.dosage', '')
                ->where('products.0.frequency', '')
                ->where('products.0.duration', '')
                ->where('products.0.protocol_notes', [
                    'Reconstitute with 2ml bacteriostatic water.',
                ]),
            );
    }

    public function test_a_product_with_only_a_dosage_is_included(): void
    {
        $this->product('Ipamorelin', 'ipamorelin', ['dosage' => '200-300mcg']);

        $this->get(route('storefront.protocols'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('storefront/Protocols')
                ->has('products', 1)
                ->where('products.0.dosage', '200-300mcg')
                ->where('products.0.protocol_notes', []),
            );
    }

    /**
     * Storage instructions are not protocol data: nearly every product carries
     * them, and on their own they do not describe how a compound is dosed.
     */
    public function test_storage_instructions_alone_do_not_list_a_product(): void
    {
        $product = $this->product('Semaglutide', 'semaglutide');
        $product->technicalDetails()->create([
            'type' => ProductTechnicalDetail::TYPE_STORAGE,
            'label' => 'Temperature',
            'value' => '2-8°C',
            'sort_order' => 0,
        ]);

        $this->get(route('storefront.protocols'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('storefront/Protocols')
                ->has('products', 0),
            );
    }

    /** The storefront visibility rule still applies to this page. */
    public function test_a_draft_product_with_a_protocol_stays_off_the_page(): void
    {
        $this->product('Draft compound', 'draft-compound', [
            'status' => 'draft',
            'dosage' => '250mcg',
        ]);

        $this->get(route('storefront.protocols'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('storefront/Protocols')
                ->has('products', 0),
            );
    }

    public function test_category_tabs_are_limited_to_the_listed_products(): void
    {
        Category::create(['name' => 'Empty category', 'slug' => 'empty-category']);

        $this->product('Ipamorelin', 'ipamorelin', ['dosage' => '200-300mcg']);

        $this->get(route('storefront.protocols'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('storefront/Protocols')
                ->where('categories', ['Healing']),
            );
    }
}
