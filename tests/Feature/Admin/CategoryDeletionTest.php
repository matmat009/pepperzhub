<?php

namespace Tests\Feature\Admin;

use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Support\SessionKey;
use Tests\TestCase;

class CategoryDeletionTest extends TestCase
{
    use RefreshDatabase;

    private function actingAsAdmin(): User
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        return $user;
    }

    private function category(string $name): Category
    {
        return Category::create(['name' => $name, 'slug' => str($name)->slug()->value()]);
    }

    private function productIn(Category $category, string $name): Product
    {
        return Product::create([
            'category_id' => $category->id,
            'name' => $name,
            'slug' => str($name)->slug()->value(),
            'status' => 'active',
        ]);
    }

    public function test_a_category_holding_one_product_cannot_be_deleted()
    {
        $this->actingAsAdmin();

        $category = $this->category('Healing');
        $this->productIn($category, 'BPC-157');

        $response = $this->delete(route('admin.products.categories.destroy', $category));

        $response->assertSessionHasErrors([
            'category' => 'Reassign this 1 product before deleting this category.',
        ]);
        $this->assertDatabaseHas('categories', ['id' => $category->id]);
    }

    public function test_the_blocked_message_pluralises()
    {
        $this->actingAsAdmin();

        $category = $this->category('Recovery');
        $this->productIn($category, 'TB-500');
        $this->productIn($category, 'GHK-Cu');

        $this->delete(route('admin.products.categories.destroy', $category))
            ->assertSessionHasErrors([
                'category' => 'Reassign these 2 products before deleting this category.',
            ]);
    }

    public function test_an_empty_category_can_be_deleted()
    {
        $this->actingAsAdmin();

        $category = $this->category('Research');

        $response = $this->delete(route('admin.products.categories.destroy', $category));

        $response->assertSessionHasNoErrors();

        // Asserts the contract the frontend actually reads: Inertia's flash
        // bag, not the ordinary session bag.
        $this->assertSame(
            ['type' => 'success', 'message' => 'Category deleted.'],
            session(SessionKey::FLASH_DATA)['toast'] ?? null,
        );
        $this->assertDatabaseMissing('categories', ['id' => $category->id]);
    }

    public function test_deleting_an_unknown_category_returns_not_found()
    {
        $this->actingAsAdmin();

        $this->delete(route('admin.products.categories.destroy', 9999))
            ->assertNotFound();
    }
}
