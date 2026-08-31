<?php

namespace Tests\Feature\Admin;

use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductDescriptionValidationTest extends TestCase
{
    use RefreshDatabase;

    private Category $category;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs(User::factory()->create());
        $this->category = Category::create([
            'name' => 'Healing',
            'slug' => 'healing',
        ]);
    }

    /** @return array<string, mixed> */
    private function payload(array $overrides = []): array
    {
        return [
            'name' => 'BPC-157',
            'category_id' => $this->category->id,
            'status' => 'draft',
            'featured' => false,
            'short_description' => null,
            'full_description' => null,
            'variants' => [],
            'purity' => [],
            'storage' => [],
            'kept_image_ids' => [],
            ...$overrides,
        ];
    }

    public function test_draft_product_can_be_saved_without_descriptions(): void
    {
        $this->post(route('admin.products.store'), $this->payload())
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('products', [
            'name' => 'BPC-157',
            'status' => 'draft',
            'short_description' => null,
            'full_description' => null,
        ]);
    }

    public function test_active_product_without_a_short_description_is_rejected(): void
    {
        $this->post(route('admin.products.store'), $this->payload([
            'status' => 'active',
        ]))->assertSessionHasErrors([
            'short_description' => 'A short description is required for active products.',
        ]);

        $this->assertDatabaseMissing('products', ['name' => 'BPC-157']);
    }

    public function test_whitespace_is_not_a_valid_active_short_description(): void
    {
        $this->post(route('admin.products.store'), $this->payload([
            'status' => 'active',
            'short_description' => '   ',
        ]))->assertSessionHasErrors('short_description');
    }

    public function test_active_product_can_be_saved_with_only_a_short_description(): void
    {
        $this->post(route('admin.products.store'), $this->payload([
            'status' => 'active',
            'short_description' => 'Body protection compound.',
        ]))->assertSessionHasNoErrors();

        $this->assertDatabaseHas('products', [
            'name' => 'BPC-157',
            'status' => 'active',
            'short_description' => 'Body protection compound.',
            'full_description' => null,
        ]);
    }

    public function test_description_fields_are_saved_separately(): void
    {
        $this->post(route('admin.products.store'), $this->payload([
            'status' => 'active',
            'short_description' => 'Short storefront summary.',
            'full_description' => 'Long-form product information.',
        ]))->assertSessionHasNoErrors();

        $this->assertDatabaseHas('products', [
            'name' => 'BPC-157',
            'short_description' => 'Short storefront summary.',
            'full_description' => 'Long-form product information.',
        ]);
    }

    public function test_draft_cannot_be_activated_without_a_short_description(): void
    {
        $product = Product::create([
            'category_id' => $this->category->id,
            'name' => 'BPC-157',
            'slug' => 'bpc-157',
            'status' => 'draft',
        ]);

        $this->put(route('admin.products.update', $product), $this->payload([
            'status' => 'active',
        ]))->assertSessionHasErrors('short_description');

        $this->assertSame('draft', $product->refresh()->status);
    }

    public function test_draft_can_be_activated_with_only_a_short_description(): void
    {
        $product = Product::create([
            'category_id' => $this->category->id,
            'name' => 'BPC-157',
            'slug' => 'bpc-157',
            'status' => 'draft',
        ]);

        $this->put(route('admin.products.update', $product), $this->payload([
            'status' => 'active',
            'short_description' => 'Ready for the storefront.',
        ]))->assertSessionHasNoErrors();

        $this->assertDatabaseHas('products', [
            'id' => $product->id,
            'status' => 'active',
            'short_description' => 'Ready for the storefront.',
            'full_description' => null,
        ]);
    }
}
