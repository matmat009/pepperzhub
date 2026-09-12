<?php

namespace Tests\Feature\Admin;

use App\Models\Category;
use App\Models\Product;
use App\Models\Review;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ReviewCrudTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs(User::factory()->create(['email_verified_at' => now()]));
    }

    private function product(string $name = 'BPC-157'): Product
    {
        $category = Category::firstOrCreate(
            ['slug' => 'healing'],
            ['name' => 'Healing'],
        );

        return Product::create([
            'category_id' => $category->id,
            'name' => $name,
            'status' => 'active',
            'short_description' => 'Peptide.',
        ]);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function payload(array $overrides = []): array
    {
        return array_merge([
            'product_id' => '',
            'customer_name' => 'Maria S.',
            'title' => 'Great results',
            'description' => 'Arrived quickly and exactly as described.',
            'is_active' => true,
            'sort_order' => 0,
        ], $overrides);
    }

    // ----- create -----------------------------------------------------------

    public function test_a_review_can_be_created_with_a_product_tag(): void
    {
        $product = $this->product();

        $this->post(route('admin.reviews.store'), $this->payload([
            'product_id' => $product->id,
        ]))->assertRedirect();

        $review = Review::sole();

        $this->assertSame($product->id, $review->product_id);
        $this->assertSame('Great results', $review->title);
        $this->assertSame('Maria S.', $review->customer_name);
        $this->assertTrue($review->is_active);
    }

    /**
     * The tag is optional at every level — a review about the shop in general
     * has none, and the blank select posts an empty string rather than null.
     */
    public function test_a_review_can_be_created_without_a_product_tag(): void
    {
        $this->post(route('admin.reviews.store'), $this->payload())
            ->assertRedirect();

        $review = Review::sole();

        $this->assertNull($review->product_id);
        $this->assertSame('Great results', $review->title);
    }

    public function test_a_review_requires_a_title_and_description(): void
    {
        $this->post(route('admin.reviews.store'), $this->payload([
            'title' => '',
            'description' => '',
        ]))->assertSessionHasErrors(['title', 'description']);

        $this->assertSame(0, Review::count());
    }

    public function test_the_customer_name_is_optional(): void
    {
        $this->post(route('admin.reviews.store'), $this->payload([
            'customer_name' => '',
        ]))->assertRedirect();

        $this->assertNull(Review::sole()->customer_name);
    }

    // ----- image ------------------------------------------------------------

    public function test_the_photo_is_stored_on_the_public_disk(): void
    {
        Storage::fake('public');

        $this->post(route('admin.reviews.store'), $this->payload([
            'image' => UploadedFile::fake()->image('review.jpg'),
        ]))->assertRedirect();

        $path = Review::sole()->image_path;

        $this->assertNotNull($path);
        Storage::disk('public')->assertExists($path);
    }

    /**
     * Same guarantee as the payment method QR code: replacing the file must not
     * leave the previous one orphaned on disk.
     */
    public function test_replacing_the_photo_deletes_the_old_file(): void
    {
        Storage::fake('public');

        $this->post(route('admin.reviews.store'), $this->payload([
            'image' => UploadedFile::fake()->image('first.jpg'),
        ]));

        $review = Review::sole();
        $original = $review->image_path;

        $this->put(route('admin.reviews.update', $review), $this->payload([
            'image' => UploadedFile::fake()->image('second.jpg'),
        ]));

        $replacement = $review->fresh()->image_path;

        $this->assertNotSame($original, $replacement);
        Storage::disk('public')->assertMissing($original);
        Storage::disk('public')->assertExists($replacement);
    }

    public function test_removing_the_photo_deletes_the_file_and_nulls_the_path(): void
    {
        Storage::fake('public');

        $this->post(route('admin.reviews.store'), $this->payload([
            'image' => UploadedFile::fake()->image('review.jpg'),
        ]));

        $review = Review::sole();
        $original = $review->image_path;

        $this->put(route('admin.reviews.update', $review), $this->payload([
            'remove_image' => true,
        ]));

        $this->assertNull($review->fresh()->image_path);
        Storage::disk('public')->assertMissing($original);
    }

    /**
     * An update that touches neither field leaves the stored photo alone —
     * otherwise editing the title would silently drop the image.
     */
    public function test_an_update_without_an_image_keeps_the_stored_one(): void
    {
        Storage::fake('public');

        $this->post(route('admin.reviews.store'), $this->payload([
            'image' => UploadedFile::fake()->image('review.jpg'),
        ]));

        $review = Review::sole();
        $original = $review->image_path;

        $this->put(route('admin.reviews.update', $review), $this->payload([
            'title' => 'Edited title',
        ]));

        $this->assertSame($original, $review->fresh()->image_path);
        $this->assertSame('Edited title', $review->fresh()->title);
        Storage::disk('public')->assertExists($original);
    }

    // ----- update and delete -------------------------------------------------

    public function test_a_review_can_be_untagged_by_clearing_the_product(): void
    {
        $product = $this->product();

        $this->post(route('admin.reviews.store'), $this->payload([
            'product_id' => $product->id,
        ]));

        $review = Review::sole();

        $this->put(route('admin.reviews.update', $review), $this->payload())
            ->assertRedirect();

        $this->assertNull($review->fresh()->product_id);
    }

    public function test_deleting_a_review_removes_its_photo(): void
    {
        Storage::fake('public');

        $this->post(route('admin.reviews.store'), $this->payload([
            'image' => UploadedFile::fake()->image('review.jpg'),
        ]));

        $review = Review::sole();
        $path = $review->image_path;

        $this->delete(route('admin.reviews.destroy', $review))
            ->assertRedirect();

        $this->assertSame(0, Review::count());
        Storage::disk('public')->assertMissing($path);
    }

    /**
     * The headline guarantee of the nullOnDelete foreign key: unlike an order
     * line, a review has no historical accuracy to preserve, so it outlives the
     * product it mentioned rather than being deleted or blocking the delete.
     */
    public function test_deleting_a_product_leaves_its_reviews_intact_but_untagged(): void
    {
        $product = $this->product();

        $this->post(route('admin.reviews.store'), $this->payload([
            'product_id' => $product->id,
        ]));

        $review = Review::sole();
        $this->assertSame($product->id, $review->product_id);

        $product->delete();

        $this->assertSame(1, Review::count(), 'the review was deleted with its product');
        $this->assertNull($review->fresh()->product_id);
        $this->assertSame('Great results', $review->fresh()->title);
    }

    // ----- index -------------------------------------------------------------

    public function test_the_index_lists_inactive_reviews_so_they_can_be_reactivated(): void
    {
        $this->post(route('admin.reviews.store'), $this->payload([
            'title' => 'Hidden one',
            'is_active' => false,
        ]));

        $this->get(route('admin.reviews.index'))
            ->assertOk()
            ->assertInertia(
                fn ($page) => $page
                    ->component('admin/reviews/Index')
                    ->has('reviews', 1)
                    ->where('reviews.0.is_active', false)
                    ->where('reviews.0.title', 'Hidden one')
            );
    }

    /**
     * A URL, never the storage path — the client has no use for where on disk
     * the file sits.
     */
    public function test_the_index_sends_a_url_rather_than_a_storage_path(): void
    {
        Storage::fake('public');

        $this->post(route('admin.reviews.store'), $this->payload([
            'image' => UploadedFile::fake()->image('review.jpg'),
        ]));

        $path = Review::sole()->image_path;

        $this->get(route('admin.reviews.index'))
            ->assertOk()
            ->assertInertia(function ($page) use ($path) {
                $url = $page->toArray()['props']['reviews'][0]['image_url'];

                $this->assertNotSame($path, $url);
                $this->assertStringContainsString($path, (string) $url);
                // Served through the public-disk mount, not the bare path.
                $this->assertStringContainsString('/storage/', (string) $url);
            });
    }
}
