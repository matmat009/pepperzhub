<?php

namespace Tests\Feature\Admin;

use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Tests\TestCase;

class ProductBulkActionTest extends TestCase
{
    use RefreshDatabase;

    private Category $category;

    protected function setUp(): void
    {
        parent::setUp();

        $this->category = Category::create([
            'name' => 'Research',
            'slug' => 'research',
        ]);
    }

    private function product(string $name, string $status = 'active'): Product
    {
        $product = Product::create([
            'category_id' => $this->category->id,
            'name' => $name,
            'status' => $status,
            'featured' => true,
            'short_description' => "Short description for {$name}.",
            'full_description' => "Full description for {$name}.",
        ]);

        $product->variants()->create([
            'label' => '5mg vial',
            'price' => 1500,
            'stock' => 10,
            'is_kit' => false,
            'kit_inclusions' => [],
            'sort_order' => 0,
        ]);

        $product->technicalDetails()->create([
            'type' => 'purity',
            'label' => 'HPLC',
            'value' => '99.2%',
            'sort_order' => 0,
        ]);

        return $product;
    }

    private function addImage(Product $product, string $contents): string
    {
        $path = "products/{$product->id}/primary.jpg";

        Storage::disk('public')->put($path, $contents);
        $product->images()->create(['path' => $path, 'sort_order' => 0]);

        return $path;
    }

    private function signIn(): void
    {
        $this->actingAs(User::factory()->create());
    }

    public function test_bulk_delete_removes_only_selected_products_and_their_image_files(): void
    {
        Storage::fake('public');
        $this->signIn();

        $selectedA = $this->product('BPC-157');
        $selectedB = $this->product('TB-500');
        $survivor = $this->product('GHK-Cu', 'draft');

        $selectedPathA = $this->addImage($selectedA, 'selected-a');
        $selectedPathB = $this->addImage($selectedB, 'selected-b');
        $survivorPath = $this->addImage($survivor, 'survivor');

        $survivorAttributes = $survivor->fresh()->getAttributes();
        $survivorVariants = $survivor->variants()->get()->map->getAttributes()->all();
        $survivorDetails = $survivor->technicalDetails()->get()->map->getAttributes()->all();
        $survivorImages = $survivor->images()->get()->map->getAttributes()->all();

        $this->delete(route('admin.products.bulk-destroy'), [
            'ids' => [$selectedA->id, $selectedB->id],
        ])
            ->assertRedirect()
            ->assertSessionHasNoErrors()
            ->assertSessionHas('inertia.flash_data.toast.message', '2 products deleted.');

        foreach ([$selectedA, $selectedB] as $selected) {
            $this->assertDatabaseMissing('products', ['id' => $selected->id]);
            $this->assertDatabaseMissing('product_variants', ['product_id' => $selected->id]);
            $this->assertDatabaseMissing('product_technical_details', ['product_id' => $selected->id]);
            $this->assertDatabaseMissing('product_images', ['product_id' => $selected->id]);
        }

        Storage::disk('public')->assertMissing($selectedPathA);
        Storage::disk('public')->assertMissing($selectedPathB);

        $this->assertSame($survivorAttributes, $survivor->fresh()->getAttributes());
        $this->assertSame($survivorVariants, $survivor->variants()->get()->map->getAttributes()->all());
        $this->assertSame($survivorDetails, $survivor->technicalDetails()->get()->map->getAttributes()->all());
        $this->assertSame($survivorImages, $survivor->images()->get()->map->getAttributes()->all());
        Storage::disk('public')->assertExists($survivorPath);
        $this->assertSame('survivor', Storage::disk('public')->get($survivorPath));
    }

    public function test_bulk_delete_rolls_back_database_and_file_deletions_on_failure(): void
    {
        Storage::fake('public');
        $this->signIn();

        $first = $this->product('BPC-157');
        $failing = $this->product('TB-500');
        $firstPath = $this->addImage($first, 'first-image');
        $failingPath = $this->addImage($failing, 'failing-image');

        Product::deleting(function (Product $product) use ($failing) {
            if ($product->is($failing)) {
                throw new RuntimeException('Forced failure during bulk delete.');
            }
        });

        $this->withoutExceptionHandling();

        try {
            $this->delete(route('admin.products.bulk-destroy'), [
                'ids' => [$first->id, $failing->id],
            ]);

            $this->fail('The forced bulk-delete failure was not thrown.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Forced failure during bulk delete.', $exception->getMessage());
        }

        foreach ([$first, $failing] as $product) {
            $this->assertDatabaseHas('products', ['id' => $product->id]);
            $this->assertDatabaseHas('product_variants', ['product_id' => $product->id]);
            $this->assertDatabaseHas('product_technical_details', ['product_id' => $product->id]);
            $this->assertDatabaseHas('product_images', ['product_id' => $product->id]);
        }

        Storage::disk('public')->assertExists($firstPath);
        Storage::disk('public')->assertExists($failingPath);
        $this->assertSame('first-image', Storage::disk('public')->get($firstPath));
        $this->assertSame('failing-image', Storage::disk('public')->get($failingPath));
    }

    public function test_bulk_archive_changes_only_status_on_selected_products(): void
    {
        $this->signIn();

        $selectedA = $this->product('BPC-157', 'active');
        $selectedB = $this->product('TB-500', 'draft');
        $untouched = $this->product('GHK-Cu', 'active');

        $expectedA = $selectedA->fresh()->getRawOriginal();
        $expectedA['status'] = 'archived';
        $expectedB = $selectedB->fresh()->getRawOriginal();
        $expectedB['status'] = 'archived';
        $untouchedAttributes = $untouched->fresh()->getRawOriginal();

        $this->post(route('admin.products.bulk-archive'), [
            'ids' => [$selectedA->id, $selectedB->id],
        ])
            ->assertRedirect()
            ->assertSessionHasNoErrors()
            ->assertSessionHas('inertia.flash_data.toast.message', '2 products archived.');

        $this->assertSame($expectedA, $selectedA->fresh()->getRawOriginal());
        $this->assertSame($expectedB, $selectedB->fresh()->getRawOriginal());
        $this->assertSame($untouchedAttributes, $untouched->fresh()->getRawOriginal());
    }

    public function test_bulk_routes_reject_missing_or_empty_ids(): void
    {
        $this->signIn();

        foreach ([
            ['post', 'admin.products.bulk-archive'],
            ['delete', 'admin.products.bulk-destroy'],
        ] as [$method, $route]) {
            $this->{$method}(route($route))->assertSessionHasErrors('ids');
            $this->{$method}(route($route), ['ids' => []])->assertSessionHasErrors('ids');
        }
    }

    public function test_bulk_routes_reject_an_unknown_product_id(): void
    {
        $this->signIn();

        foreach ([
            ['post', 'admin.products.bulk-archive'],
            ['delete', 'admin.products.bulk-destroy'],
        ] as [$method, $route]) {
            $this->{$method}(route($route), ['ids' => [999999]])
                ->assertSessionHasErrors('ids.0');
        }
    }

    public function test_bulk_routes_require_authentication(): void
    {
        $product = $this->product('BPC-157');

        $this->post(route('admin.products.bulk-archive'), ['ids' => [$product->id]])
            ->assertRedirect(route('login'));
        $this->delete(route('admin.products.bulk-destroy'), ['ids' => [$product->id]])
            ->assertRedirect(route('login'));

        $this->assertDatabaseHas('products', ['id' => $product->id]);
    }
}
