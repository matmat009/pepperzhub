<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
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

    public function test_a_category_holding_products_cannot_be_deleted()
    {
        $this->actingAsAdmin();

        // Category 1 (Recovery) has one product assigned.
        $response = $this->delete(route('admin.products.categories.destroy', 1));

        $response->assertSessionHasErrors('category');
        $this->assertStringContainsString(
            'Reassign this 1 product',
            session('errors')->first('category'),
        );
    }

    public function test_an_empty_category_can_be_deleted()
    {
        $this->actingAsAdmin();

        // Category 4 (Research) has no products assigned.
        $response = $this->delete(route('admin.products.categories.destroy', 4));

        $response->assertSessionHasNoErrors();
        $response->assertSessionHas('success');
    }

    public function test_deleting_an_unknown_category_returns_not_found()
    {
        $this->actingAsAdmin();

        $this->delete(route('admin.products.categories.destroy', 99))
            ->assertNotFound();
    }
}
