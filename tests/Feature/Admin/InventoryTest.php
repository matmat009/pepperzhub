<?php

namespace Tests\Feature\Admin;

use App\Models\Category;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\StockMovement;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The Inventory screen, now that it reads and writes real stock.
 *
 * It used to render five hardcoded products and its adjust action returned a
 * toast without touching anything, which is why it was kept out of the
 * sidebar. These pin the two things that were missing: that the rows come off
 * product_variants, and that an adjustment is still there after the page
 * reloads.
 */
class InventoryTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs(User::factory()->create(['email_verified_at' => now()]));
    }

    private function product(string $name, string $category = 'Healing'): Product
    {
        $row = Category::firstOrCreate(
            ['name' => $category],
            ['slug' => strtolower($category)],
        );

        return Product::create([
            'category_id' => $row->id,
            'name' => $name,
            'slug' => str($name)->slug()->value(),
            'status' => 'active',
            'short_description' => 'Peptide.',
        ]);
    }

    private function variant(
        Product $product,
        string $label,
        int $stock,
        bool $isKit = false,
        int $sortOrder = 0,
    ): ProductVariant {
        return $product->variants()->create([
            'label' => $label,
            'price' => 2450,
            'stock' => $stock,
            'is_kit' => $isKit,
            'sort_order' => $sortOrder,
        ]);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function items(): array
    {
        return $this->get(route('admin.products.inventory.index'))
            ->assertOk()
            ->inertiaProps()['items'];
    }

    // ----- shape ------------------------------------------------------------

    /**
     * One row per format, never per product.
     *
     * The whole point of the screen: a product whose 5mg vial is down to two
     * units while its 10mg sibling holds forty has a problem, and a single
     * summed row of forty-two would report none.
     */
    public function test_each_format_gets_its_own_row(): void
    {
        $product = $this->product('BPC-157');
        $this->variant($product, '5mg vial', 2, sortOrder: 0);
        $this->variant($product, '10mg vial', 40, sortOrder: 1);

        $items = $this->items();

        $this->assertCount(2, $items);
        $this->assertSame(['BPC-157', 'BPC-157'], array_column($items, 'product_name'));
        $this->assertSame(['5mg vial', '10mg vial'], array_column($items, 'variant_label'));
        $this->assertSame([2, 40], array_column($items, 'stock'));

        // And the low one is visible as such rather than averaged away.
        $this->assertSame(
            [ProductVariant::STATUS_LOW_STOCK, ProductVariant::STATUS_IN_STOCK],
            array_column($items, 'status'),
        );
    }

    /** The row is keyed by variant, because that is what adjust() acts on. */
    public function test_the_row_id_is_the_variant_id(): void
    {
        $variant = $this->variant($this->product('BPC-157'), '5mg vial', 12);

        $this->assertSame($variant->id, $this->items()[0]['id']);
    }

    public function test_rows_carry_the_type_category_and_thumbnail_the_table_renders(): void
    {
        $product = $this->product('TB-500', category: 'Recovery');
        $this->variant($product, 'Starter kit', 8, isKit: true, sortOrder: 0);
        $this->variant($product, '5mg vial', 8, sortOrder: 1);

        $items = $this->items();

        $this->assertSame(['Kit', 'Vial'], array_column($items, 'type'));
        $this->assertSame(['Recovery', 'Recovery'], array_column($items, 'category'));
        // Nullable, and null is the expected state for a product with no image
        // — the cell falls back to its flask glyph.
        $this->assertNull($items[0]['thumbnail']);

        $product->images()->create(['path' => 'products/tb500.jpg', 'sort_order' => 0]);

        $this->assertStringContainsString('tb500.jpg', $this->items()[0]['thumbnail']);
    }

    public function test_rows_are_ordered_by_product_then_by_format_order(): void
    {
        $second = $this->product('TB-500');
        $this->variant($second, 'Kit', 5, sortOrder: 1);
        $this->variant($second, 'Vial', 5, sortOrder: 0);

        $first = $this->product('BPC-157');
        $this->variant($first, '5mg vial', 5, sortOrder: 0);

        $this->assertSame(
            ['BPC-157', 'TB-500', 'TB-500'],
            array_column($this->items(), 'product_name'),
        );
        $this->assertSame(
            ['5mg vial', 'Vial', 'Kit'],
            array_column($this->items(), 'variant_label'),
        );
    }

    // ----- status classification --------------------------------------------

    /**
     * The same threshold every other screen reads, not a local one.
     *
     * The page shipped with its own 10 while the real number was 5. That is
     * the bug LowStockThresholdTest exists for, and this is it checked at the
     * boundary the Inventory payload crosses.
     */
    public function test_status_uses_the_shared_threshold_at_its_boundaries(): void
    {
        $threshold = ProductVariant::LOW_STOCK_THRESHOLD;
        $product = $this->product('BPC-157');

        foreach ([0, 1, $threshold, $threshold + 1] as $index => $stock) {
            $this->variant($product, "format {$index}", $stock, sortOrder: $index);
        }

        $this->assertSame(
            [
                ProductVariant::STATUS_OUT_OF_STOCK,
                ProductVariant::STATUS_LOW_STOCK,
                ProductVariant::STATUS_LOW_STOCK,
                ProductVariant::STATUS_IN_STOCK,
            ],
            array_column($this->items(), 'status'),
        );
    }

    // ----- history ----------------------------------------------------------

    public function test_history_arrives_oldest_first_with_its_running_totals(): void
    {
        $variant = $this->variant($this->product('BPC-157'), '5mg vial', 0);

        $variant->forceFill(['stock' => 30])->save();
        StockMovement::record($variant, 30, StockMovement::REASON_RESTOCK, 'Lot A-1140.');

        $variant->forceFill(['stock' => 27])->save();
        StockMovement::record($variant, -3, StockMovement::REASON_DAMAGED);

        $history = $this->items()[0]['history'];

        $this->assertCount(2, $history);
        $this->assertSame([30, -3], array_column($history, 'delta'));
        $this->assertSame([30, 27], array_column($history, 'resulting_stock'));
        $this->assertSame(['Restock', 'Damaged'], array_column($history, 'reason'));
        $this->assertSame(['Lot A-1140.', null], array_column($history, 'note'));
    }

    /**
     * "Last Updated" means the stock, not the row.
     *
     * A price edit touches updated_at without a unit moving, and a column
     * headed Last Updated that jumped on a price change would be answering a
     * question nobody asked of this screen.
     */
    public function test_last_updated_tracks_the_newest_movement(): void
    {
        $variant = $this->variant($this->product('BPC-157'), '5mg vial', 30);

        // No movements yet: the variant's own timestamp is the only answer.
        $this->assertSame(
            $variant->updated_at->toDateString(),
            $this->items()[0]['updated_at'],
        );

        $movement = StockMovement::record($variant, 5, StockMovement::REASON_RESTOCK);
        $movement->forceFill(['created_at' => now()->subDays(3)])->save();

        $this->assertSame(
            now()->subDays(3)->toDateString(),
            $this->items()[0]['updated_at'],
        );
    }

    // ----- adjusting --------------------------------------------------------

    public function test_an_adjustment_persists_and_shows_up_in_the_history(): void
    {
        $variant = $this->variant($this->product('BPC-157'), '5mg vial', 20);

        $this->post(route('admin.products.inventory.adjust', $variant), [
            'delta' => 12,
            'reason' => 'Restock',
            'note' => 'Lot A-1188.',
        ])->assertRedirect();

        // Really saved, not just toasted.
        $this->assertSame(32, (int) $variant->fresh()->stock);

        $item = $this->items()[0];

        $this->assertSame(32, $item['stock']);
        $this->assertCount(1, $item['history']);
        $this->assertSame(12, $item['history'][0]['delta']);
        $this->assertSame('Restock', $item['history'][0]['reason']);
        $this->assertSame(32, $item['history'][0]['resulting_stock']);
        $this->assertSame('Lot A-1188.', $item['history'][0]['note']);
    }

    public function test_a_negative_adjustment_takes_stock_away(): void
    {
        $variant = $this->variant($this->product('BPC-157'), '5mg vial', 20);

        $this->post(route('admin.products.inventory.adjust', $variant), [
            'delta' => -4,
            'reason' => 'Damaged',
            'note' => 'Seal failure in transit.',
        ])->assertRedirect();

        $this->assertSame(16, (int) $variant->fresh()->stock);
        $this->assertSame(-4, $variant->movements()->sole()->delta);
    }

    public function test_an_empty_note_is_stored_as_null_rather_than_an_empty_string(): void
    {
        $variant = $this->variant($this->product('BPC-157'), '5mg vial', 20);

        $this->post(route('admin.products.inventory.adjust', $variant), [
            'delta' => 3,
            'reason' => 'Correction',
            'note' => '',
        ])->assertRedirect();

        $this->assertNull($variant->movements()->sole()->note);
    }

    /**
     * An over-subtraction floors at zero, and the log says so.
     *
     * The column is unsigned, and the dialog previewed a floored total before
     * the click. What matters is that the recorded delta is the one actually
     * applied — otherwise resulting_stock stops reconciling with the entry
     * above it and the history quietly becomes fiction.
     */
    public function test_subtracting_past_zero_floors_and_logs_the_applied_delta(): void
    {
        $variant = $this->variant($this->product('BPC-157'), '5mg vial', 3);

        $this->post(route('admin.products.inventory.adjust', $variant), [
            'delta' => -10,
            'reason' => 'Damaged',
        ])->assertRedirect();

        $this->assertSame(0, (int) $variant->fresh()->stock);

        $movement = $variant->movements()->sole();

        $this->assertSame(-3, $movement->delta, 'the log recorded units that were never there');
        $this->assertSame(0, $movement->resulting_stock);
    }

    /** The automatic reasons are the system's to write, not an admin's to claim. */
    public function test_an_automatic_reason_is_rejected(): void
    {
        $variant = $this->variant($this->product('BPC-157'), '5mg vial', 20);

        foreach (StockMovement::AUTOMATIC_REASONS as $reason) {
            $this->post(route('admin.products.inventory.adjust', $variant), [
                'delta' => 5,
                'reason' => $reason,
            ])->assertSessionHasErrors('reason');
        }

        $this->assertSame(20, (int) $variant->fresh()->stock);
        $this->assertSame(0, StockMovement::count());
    }

    public function test_a_zero_delta_is_rejected(): void
    {
        $variant = $this->variant($this->product('BPC-157'), '5mg vial', 20);

        $this->post(route('admin.products.inventory.adjust', $variant), [
            'delta' => 0,
            'reason' => 'Correction',
        ])->assertSessionHasErrors('delta');

        $this->assertSame(0, StockMovement::count());
    }

    public function test_a_guest_cannot_adjust_stock(): void
    {
        auth()->logout();

        $variant = $this->variant($this->product('BPC-157'), '5mg vial', 20);

        $this->post(route('admin.products.inventory.adjust', $variant), [
            'delta' => 99,
            'reason' => 'Restock',
        ])->assertRedirect(route('login'));

        $this->assertSame(20, (int) $variant->fresh()->stock);
        $this->assertSame(0, StockMovement::count());
    }

    // ----- what the client-side filters read --------------------------------

    /**
     * The table's three controls all filter client-side, and there is no Vue
     * test runner here — so this pins the contract between them and the
     * payload instead: every field those filters read is present, populated,
     * and named as the column expects.
     *
     * Search reads `product_name`, the category dropdown reads `category`, and
     * the low-stock switch reads `status`. A rename on either side is what
     * would silently break them, and that is what this catches.
     */
    public function test_the_payload_carries_every_field_the_filters_read(): void
    {
        $healing = $this->product('BPC-157', category: 'Healing');
        $this->variant($healing, '5mg vial', 2, sortOrder: 0);
        $this->variant($healing, '10mg vial', 40, sortOrder: 1);

        $recovery = $this->product('TB-500', category: 'Recovery');
        $this->variant($recovery, 'Starter kit', 0, isKit: true);

        $items = $this->items();

        foreach ($items as $item) {
            $this->assertArrayHasKey('product_name', $item);
            $this->assertArrayHasKey('category', $item);
            $this->assertArrayHasKey('status', $item);
            $this->assertNotSame('', $item['product_name']);
            $this->assertNotSame('', $item['category']);
        }

        // Search: matching on product name spans that product's formats.
        $named = array_values(array_filter(
            $items,
            fn (array $item) => str_contains(strtolower($item['product_name']), 'bpc'),
        ));
        $this->assertCount(2, $named);

        // Category: both of BPC-157's formats sit under Healing.
        $this->assertSame(
            ['Healing', 'Healing', 'Recovery'],
            array_column($items, 'category'),
        );

        // Low stock only: the 2-unit vial and the sold-out kit, not the 40.
        $low = array_values(array_filter(
            $items,
            fn (array $item) => $item['status'] !== ProductVariant::STATUS_IN_STOCK,
        ));
        $this->assertSame([2, 0], array_column($low, 'stock'));
    }

    /**
     * The table components read those field names. Asserted against source for
     * the same reason SiteSettingsTest reads the footer: there is no SSR
     * bundle, so a request returns the Inertia shell and nothing renders
     * server-side.
     */
    public function test_the_table_still_wires_all_three_filters_to_the_real_fields(): void
    {
        $columns = file_get_contents(
            resource_path('js/pages/admin/products/inventory/columns.ts'),
        );

        // Search, on the product name, under the column id the toolbar uses.
        $this->assertStringContainsString("columnHelper.accessor('product_name'", $columns);
        $this->assertStringContainsString("id: 'product'", $columns);

        // Category filter, and the low-stock switch reading the server verdict.
        $this->assertStringContainsString("columnHelper.accessor('category'", $columns);
        $this->assertStringContainsString('isLowStock(row.original.status)', $columns);

        // The Type badge and the thumbnail cell both survived the rewrite.
        $this->assertStringContainsString("columnHelper.accessor('type'", $columns);
        $this->assertStringContainsString('ProductCell', $columns);

        $cell = file_get_contents(
            resource_path('js/pages/admin/products/inventory/partials/ProductCell.vue'),
        );

        $this->assertStringContainsString('item.thumbnail', $cell);
        // The Product column names the format as well as the product, since a
        // product now spans several rows.
        $this->assertStringContainsString('item.product_name', $cell);
        $this->assertStringContainsString('item.variant_label', $cell);

        $index = file_get_contents(
            resource_path('js/pages/admin/products/inventory/Index.vue'),
        );

        $this->assertStringContainsString("getColumn('product')", $index);
        $this->assertStringContainsString("getColumn('category')", $index);
        $this->assertStringContainsString("getColumn('stock')", $index);
    }

    /** The dialog offers the manual set only. */
    public function test_the_adjust_dialog_offers_only_manual_reasons(): void
    {
        $dialog = file_get_contents(
            resource_path('js/pages/admin/products/inventory/partials/StockAdjustDialog.vue'),
        );

        $this->assertStringContainsString('MANUAL_STOCK_REASONS', $dialog);

        foreach (StockMovement::AUTOMATIC_REASONS as $reason) {
            $this->assertStringNotContainsString(
                "'{$reason}'",
                $dialog,
                "the dialog offers [{$reason}], which only the system may write",
            );
        }

        $types = file_get_contents(
            resource_path('js/pages/admin/products/inventory/types.ts'),
        );

        $this->assertStringContainsString(
            "export const MANUAL_STOCK_REASONS: ManualStockReason[] = [\n    'Restock',\n    'Damaged',\n    'Correction',\n];",
            $types,
            'the manual reason list drifted from StockMovement::MANUAL_REASONS',
        );
    }

    /** The screen is reachable again now that its numbers are real. */
    public function test_inventory_is_listed_in_the_sidebar_under_products(): void
    {
        $sidebar = file_get_contents(resource_path('js/components/AppSidebar.vue'));

        $this->assertStringContainsString(
            "{ title: 'Inventory', icon: Boxes, url: inventoryIndex() },",
            $sidebar,
            'Inventory is still commented out of the sidebar',
        );

        /*
         * Nested under Products, alongside the other three — not a top-level
         * item. Bounded to that group's own children array: `before` on a
         * brace guess silently returned the whole file, which made this pass
         * wherever Inventory happened to sit.
         */
        $children = str($sidebar)
            ->after("title: 'Products',")
            ->after('items: [')
            ->before('],')
            ->value();

        foreach (['All Products', 'Add Product', 'Categories', 'Inventory'] as $child) {
            $this->assertStringContainsString($child, $children);
        }
    }
}
