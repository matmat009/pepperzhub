<?php

namespace Tests\Feature\Admin;

use App\Models\Category;
use App\Models\Order;
use App\Models\PaymentMethod;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\ShippingCourier;
use App\Models\ShippingRegion;
use App\Models\StockMovement;
use App\Models\User;
use App\Support\SessionCart;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Stock does not move without saying why.
 *
 * Three places already change product_variants.stock, and each one is now
 * paired with a movement. What these pin is the pairing itself: an entry with
 * the right sign, the right reason, and a resulting_stock that agrees with
 * what the variant actually holds afterwards. A log that drifts from the
 * column it describes is worse than no log, because it is believed.
 */
class StockMovementLoggingTest extends TestCase
{
    use RefreshDatabase;

    private ShippingRegion $region;

    private PaymentMethod $paymentMethod;

    protected function setUp(): void
    {
        parent::setUp();

        $this->paymentMethod = PaymentMethod::create([
            'name' => 'GOtyme Bank',
            'details' => [['label' => 'Bank', 'value' => 'GOtyme Bank']],
            'is_active' => true,
        ]);

        $courier = ShippingCourier::create(['name' => 'J&T Express', 'is_active' => true]);

        $this->region = $courier->regions()->create([
            'name' => 'Luzon & Visayas',
            'rate' => 150,
            'is_active' => true,
            'sort_order' => 0,
        ]);
    }

    private function product(string $name = 'BPC-157', string $slug = 'bpc-157'): Product
    {
        $category = Category::firstOrCreate(['name' => 'Healing'], ['slug' => 'healing']);

        return Product::create([
            'category_id' => $category->id,
            'name' => $name,
            'slug' => $slug,
            'status' => 'active',
            'short_description' => 'Peptide.',
        ]);
    }

    private function variant(int $stock = 10, ?Product $product = null, string $label = '5mg vial'): ProductVariant
    {
        return ($product ?? $this->product())->variants()->create([
            'label' => $label,
            'price' => 2450,
            'stock' => $stock,
            'is_kit' => false,
            'sort_order' => 0,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function checkoutPayload(): array
    {
        return [
            'name' => 'Juan Dela Cruz',
            'social_handle' => 'fb.com/juandc',
            'phone' => '09171234567',
            'street' => '12 Mabini St',
            'barangay' => 'San Antonio',
            'city' => 'Makati',
            'province' => 'Metro Manila',
            'zip' => '1203',
            'notes' => null,
            'shipping_region_id' => $this->region->id,
            'payment_method_id' => $this->paymentMethod->id,
            'payment_proof' => UploadedFile::fake()->image('receipt.jpg'),
        ];
    }

    // ----- checkout ---------------------------------------------------------

    public function test_placing_an_order_logs_the_decrement_it_made(): void
    {
        Storage::fake('local');
        $variant = $this->variant(stock: 10);

        $this->withSession([SessionCart::SESSION_KEY => [$variant->id => 3]])
            ->post(route('storefront.checkout.store'), $this->checkoutPayload())
            ->assertRedirect();

        $this->assertSame(7, (int) $variant->fresh()->stock);

        $movements = $variant->movements()->get();

        $this->assertCount(1, $movements, 'the decrement went unlogged');

        $movement = $movements->first();

        $this->assertSame(-3, $movement->delta);
        $this->assertSame(StockMovement::REASON_ORDER_FULFILLED, $movement->reason);
        // The log has to agree with the column, not merely move in step with it.
        $this->assertSame(7, $movement->resulting_stock);
        $this->assertSame('Order '.Order::firstOrFail()->order_number, $movement->note);
    }

    /** One entry per format, because stock is taken per format. */
    public function test_an_order_spanning_two_formats_logs_each_one_separately(): void
    {
        Storage::fake('local');

        $product = $this->product();
        $small = $this->variant(stock: 10, product: $product, label: '5mg vial');
        $large = $this->variant(stock: 4, product: $product, label: '10mg vial');

        $this->withSession([SessionCart::SESSION_KEY => [$small->id => 2, $large->id => 1]])
            ->post(route('storefront.checkout.store'), $this->checkoutPayload())
            ->assertRedirect();

        $this->assertSame(-2, $small->movements()->sole()->delta);
        $this->assertSame(8, $small->movements()->sole()->resulting_stock);

        $this->assertSame(-1, $large->movements()->sole()->delta);
        $this->assertSame(3, $large->movements()->sole()->resulting_stock);
    }

    /**
     * A refused checkout leaves no trace.
     *
     * The movement is written inside the same transaction as the decrement, so
     * a rollback has to take both. A log entry for stock that was never
     * actually taken is the failure mode this arrangement exists to prevent.
     */
    public function test_a_checkout_that_fails_on_stock_logs_nothing(): void
    {
        Storage::fake('local');
        $variant = $this->variant(stock: 1);

        $this->withSession([SessionCart::SESSION_KEY => [$variant->id => 5]])
            ->post(route('storefront.checkout.store'), $this->checkoutPayload());

        $this->assertSame(0, Order::count(), 'the order should not have been placed');
        $this->assertSame(1, (int) $variant->fresh()->stock);
        $this->assertSame(0, StockMovement::count(), 'stock that never moved was logged');
    }

    // ----- cancellation and rejection ---------------------------------------

    /**
     * @return array{0: Order, 1: ProductVariant}
     */
    private function placedOrder(int $stock = 10, int $quantity = 3): array
    {
        Storage::fake('local');
        $variant = $this->variant(stock: $stock);

        $this->withSession([SessionCart::SESSION_KEY => [$variant->id => $quantity]])
            ->post(route('storefront.checkout.store'), $this->checkoutPayload())
            ->assertRedirect();

        // The checkout's own movement is not what these tests are about.
        $this->actingAs(User::factory()->create(['email_verified_at' => now()]));

        return [Order::firstOrFail(), $variant];
    }

    public function test_cancelling_an_order_logs_the_restore_it_made(): void
    {
        [$order, $variant] = $this->placedOrder();

        $this->post(route('admin.orders.cancel', $order), ['reason' => 'Buyer changed their mind'])
            ->assertRedirect();

        $this->assertSame(10, (int) $variant->fresh()->stock);

        $movements = $variant->movements()->get();

        $this->assertCount(2, $movements, 'the restore went unlogged');

        $restore = $movements->last();

        $this->assertSame(3, $restore->delta);
        $this->assertSame(StockMovement::REASON_ORDER_CANCELLED, $restore->reason);
        $this->assertSame(10, $restore->resulting_stock);
        $this->assertSame('Order '.$order->order_number, $restore->note);
    }

    public function test_rejecting_a_payment_logs_the_restore_it_made(): void
    {
        [$order, $variant] = $this->placedOrder();

        $this->post(route('admin.orders.reject-payment', $order), ['reason' => 'Proof unreadable'])
            ->assertRedirect();

        $this->assertSame(10, (int) $variant->fresh()->stock);

        $restore = $variant->movements()->get()->last();

        $this->assertSame(3, $restore->delta);
        $this->assertSame(StockMovement::REASON_ORDER_CANCELLED, $restore->reason);
        $this->assertSame(10, $restore->resulting_stock);
    }

    /**
     * The state machine already refuses to restore twice; the log must not
     * record a second hand-back either.
     */
    public function test_a_second_cancellation_neither_restores_nor_logs_again(): void
    {
        [$order, $variant] = $this->placedOrder();

        $this->post(route('admin.orders.cancel', $order), ['reason' => 'First']);
        $this->post(route('admin.orders.cancel', $order), ['reason' => 'Second']);

        $this->assertSame(10, (int) $variant->fresh()->stock);
        $this->assertSame(
            1,
            $variant->movements()->where('reason', StockMovement::REASON_ORDER_CANCELLED)->count(),
            'the same units were handed back twice in the log',
        );
    }

    // ----- the product form's Format dialog ---------------------------------

    /**
     * @return array<string, mixed>
     */
    private function productPayload(Product $product, array $variantOverrides = []): array
    {
        return [
            'name' => $product->name,
            'category_id' => $product->category_id,
            'status' => $product->status,
            'featured' => false,
            'short_description' => $product->short_description,
            'full_description' => 'Long description.',
            'variants' => $product->variants
                ->map(fn (ProductVariant $variant) => array_merge([
                    'id' => $variant->id,
                    'label' => $variant->label,
                    'price' => (float) $variant->price,
                    'stock' => (int) $variant->stock,
                    'is_kit' => $variant->is_kit,
                    'kit_inclusions' => $variant->kit_inclusions ?? [],
                ], $variantOverrides[$variant->id] ?? []))
                ->values()
                ->all(),
        ];
    }

    public function test_editing_stock_on_the_product_form_logs_a_correction(): void
    {
        $this->actingAs(User::factory()->create(['email_verified_at' => now()]));

        $product = $this->product();
        $variant = $this->variant(stock: 40, product: $product);
        $product->load('variants');

        $this->put(
            route('admin.products.update', $product),
            $this->productPayload($product, [$variant->id => ['stock' => 46]]),
        )->assertSessionHasNoErrors();

        $this->assertSame(46, (int) $variant->fresh()->stock);

        $movement = $variant->movements()->sole();

        // The difference between old and new, not the new value.
        $this->assertSame(6, $movement->delta);
        $this->assertSame(StockMovement::REASON_CORRECTION, $movement->reason);
        $this->assertSame(46, $movement->resulting_stock);
    }

    public function test_a_downward_edit_logs_a_negative_correction(): void
    {
        $this->actingAs(User::factory()->create(['email_verified_at' => now()]));

        $product = $this->product();
        $variant = $this->variant(stock: 40, product: $product);
        $product->load('variants');

        $this->put(
            route('admin.products.update', $product),
            $this->productPayload($product, [$variant->id => ['stock' => 37]]),
        )->assertSessionHasNoErrors();

        $this->assertSame(-3, $variant->movements()->sole()->delta);
        $this->assertSame(37, $variant->movements()->sole()->resulting_stock);
    }

    /**
     * Saving the form without touching stock must not fill the history with
     * zero-delta entries — a log nobody can skim is a log nobody reads.
     */
    public function test_saving_the_form_without_changing_stock_logs_nothing(): void
    {
        $this->actingAs(User::factory()->create(['email_verified_at' => now()]));

        $product = $this->product();
        $variant = $this->variant(stock: 40, product: $product);
        $product->load('variants');

        $this->put(
            route('admin.products.update', $product),
            $this->productPayload($product, [$variant->id => ['price' => 2600.0]]),
        )->assertSessionHasNoErrors();

        $this->assertSame('2600.00', $variant->fresh()->price);
        $this->assertSame(0, $variant->movements()->count());
    }

    // ----- the log itself ---------------------------------------------------

    /** Deleting a variant takes its history with it; it describes nothing else. */
    public function test_movements_are_removed_with_their_variant(): void
    {
        $variant = $this->variant(stock: 10);

        StockMovement::record($variant, 5, StockMovement::REASON_RESTOCK);

        $this->assertSame(1, StockMovement::count());

        $variant->delete();

        $this->assertSame(0, StockMovement::count());
    }

    /** The two sets never overlap — one is offered, the other is written. */
    public function test_manual_and_automatic_reasons_stay_distinct(): void
    {
        $this->assertSame(
            [],
            array_intersect(StockMovement::MANUAL_REASONS, StockMovement::AUTOMATIC_REASONS),
        );

        $this->assertSame(
            ['Restock', 'Damaged', 'Correction'],
            StockMovement::MANUAL_REASONS,
        );
        $this->assertSame(
            ['Order Fulfilled', 'Order Cancelled'],
            StockMovement::AUTOMATIC_REASONS,
        );
    }
}
