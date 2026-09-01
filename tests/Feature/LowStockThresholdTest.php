<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The admin's low-stock tile and the storefront's "Only N left" badge must
 * agree on what "low" means.
 *
 * They did not: the Dashboard had copied 10 from the hidden Inventory page's
 * placeholder data while the storefront used 5, so the same product could be
 * counted as low for the operator and shown as comfortably in stock to the
 * customer at the same moment. The threshold now lives once on ProductVariant
 * and is published to the client as a shared Inertia prop.
 */
class LowStockThresholdTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @param  list<int>  $stocks  one variant per entry
     */
    private function productWithStock(array $stocks, string $slug = 'bpc-157'): Product
    {
        $category = Category::firstOrCreate(['name' => 'Healing'], ['slug' => 'healing']);

        $product = Product::create([
            'category_id' => $category->id,
            'name' => 'BPC-157 '.$slug,
            'slug' => $slug,
            'status' => 'active',
            'short_description' => 'Peptide.',
            'featured' => true,
        ]);

        foreach ($stocks as $index => $stock) {
            $product->variants()->create([
                'label' => "{$index}mg vial",
                'price' => 2450,
                'stock' => $stock,
                'is_kit' => false,
                'sort_order' => $index,
            ]);
        }

        return $product;
    }

    public function test_the_threshold_is_shared_with_every_inertia_response(): void
    {
        // Storefront, unauthenticated.
        $this->get(route('home'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('lowStockThreshold', ProductVariant::LOW_STOCK_THRESHOLD)
            );

        // Admin, authenticated. Same value, same source.
        $this->actingAs(User::factory()->create(['email_verified_at' => now()]));

        $this->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('lowStockThreshold', ProductVariant::LOW_STOCK_THRESHOLD)
            );
    }

    /**
     * The regression that matters: one product, both screens, same verdict.
     *
     * Stock is set to exactly the threshold — the boundary both sides treat as
     * low — so a screen using a larger threshold would still count it and a
     * screen using a smaller one would not.
     */
    public function test_the_dashboard_and_the_storefront_agree_on_the_same_product(): void
    {
        $threshold = ProductVariant::LOW_STOCK_THRESHOLD;

        $this->productWithStock([$threshold]);

        // Storefront: the card has the stock and the threshold it needs to
        // decide, and by that threshold this product is low.
        $home = $this->get(route('home'))->assertOk();
        $props = $home->inertiaProps();

        $shared = (int) $props['lowStockThreshold'];
        $variants = $props['featured'][0]['variants'];
        $storefrontStock = array_sum(array_column($variants, 'stock'));

        $this->assertSame($threshold, $shared);
        $this->assertTrue(
            $storefrontStock > 0 && $storefrontStock <= $shared,
            'the storefront would not badge this product as low stock',
        );

        // Admin: the same product is inside the dashboard's low-stock count.
        $this->actingAs(User::factory()->create(['email_verified_at' => now()]));

        $dashboard = $this->get(route('dashboard'))->assertOk()->inertiaProps();

        $this->assertSame(1, $dashboard['stats']['low_stock']);
        $this->assertSame(
            $shared,
            (int) $dashboard['lowStockThreshold'],
            'the dashboard is using a different threshold from the storefront',
        );
    }

    /**
     * One above the threshold must be low on neither screen.
     *
     * Without this, a dashboard threshold larger than the storefront's would
     * still pass the test above.
     */
    public function test_a_product_just_above_the_threshold_is_low_on_neither_screen(): void
    {
        $threshold = ProductVariant::LOW_STOCK_THRESHOLD;

        $this->productWithStock([$threshold + 1]);

        $props = $this->get(route('home'))->assertOk()->inertiaProps();
        $variants = $props['featured'][0]['variants'];
        $storefrontStock = array_sum(array_column($variants, 'stock'));

        $this->assertGreaterThan((int) $props['lowStockThreshold'], $storefrontStock);

        $this->actingAs(User::factory()->create(['email_verified_at' => now()]));

        $this->assertSame(
            0,
            $this->get(route('dashboard'))->assertOk()->inertiaProps()['stats']['low_stock'],
            'the dashboard counted a product the storefront calls in stock',
        );
    }

    /**
     * The hidden Inventory page keeps its own placeholder 10, deliberately.
     *
     * Asserted so a future consolidation pass does not quietly pull that fake
     * number back into the real one, which is the direction this bug ran the
     * first time.
     */
    public function test_the_real_threshold_is_not_the_hidden_inventory_placeholder(): void
    {
        $inventoryTypes = file_get_contents(
            resource_path('js/pages/admin/products/inventory/types.ts'),
        );

        $this->assertStringContainsString(
            'export const LOW_STOCK_THRESHOLD = 10;',
            $inventoryTypes,
            "Inventory's placeholder threshold was changed; it is out of scope.",
        );

        $this->assertNotSame(10, ProductVariant::LOW_STOCK_THRESHOLD);
        $this->assertSame(5, ProductVariant::LOW_STOCK_THRESHOLD);
    }
}
