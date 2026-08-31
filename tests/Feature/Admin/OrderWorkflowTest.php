<?php

namespace Tests\Feature\Admin;

use App\Models\Category;
use App\Models\Order;
use App\Models\PaymentMethod;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\ShippingCourier;
use App\Models\ShippingRegion;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class OrderWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private ProductVariant $variant;

    protected function setUp(): void
    {
        parent::setUp();

        $user = User::factory()->create(['email_verified_at' => now()]);
        $this->actingAs($user);

        $category = Category::create(['name' => 'Healing', 'slug' => 'healing']);

        $product = Product::create([
            'category_id' => $category->id,
            'name' => 'BPC-157',
            'slug' => 'bpc-157',
            'status' => 'active',
            'short_description' => 'Peptide.',
        ]);

        $this->variant = $product->variants()->create([
            'label' => '5mg vial',
            'price' => 2450,
            'stock' => 10,
            'is_kit' => false,
            'sort_order' => 0,
        ]);
    }

    /**
     * An order as checkout would leave it: stock already deducted, payment
     * unverified, fulfillment pending.
     */
    private function order(array $overrides = [], int $quantity = 3): Order
    {
        $method = PaymentMethod::firstOrCreate(
            ['name' => 'GOtyme Bank'],
            ['details' => [['label' => 'Bank', 'value' => 'GOtyme Bank']], 'is_active' => true],
        );

        $courier = ShippingCourier::firstOrCreate(['name' => 'J&T Express'], ['is_active' => true]);

        /** @var ShippingRegion $region */
        $region = $courier->regions()->firstOrCreate(
            ['name' => 'Luzon & Visayas'],
            ['rate' => 150, 'is_active' => true],
        );

        $order = Order::create(array_merge([
            'confirmation_token' => Str::random(40),
            'name' => 'Juan Dela Cruz',
            'social_handle' => 'fb.com/juandc',
            'phone' => '0917 123 4567',
            'street' => '12 Mabini St',
            'barangay' => 'San Antonio',
            'city' => 'Makati',
            'province' => 'Metro Manila',
            'zip' => '1203',
            'shipping_courier_id' => $courier->id,
            'shipping_region_id' => $region->id,
            'shipping_courier_name' => 'J&T Express',
            'shipping_region_label' => 'Luzon & Visayas',
            'shipping_fee' => 150,
            'subtotal' => 2450 * $quantity,
            'total' => 2450 * $quantity + 150,
            'payment_method_id' => $method->id,
            'payment_method_name' => 'GOtyme Bank',
            'payment_method_details' => [['label' => 'Bank', 'value' => 'GOtyme Bank']],
            'payment_proof_path' => 'payment-proofs/'.Str::uuid().'.jpg',
        ], $overrides));

        $order->items()->create([
            'product_variant_id' => $this->variant->id,
            'product_name' => 'BPC-157',
            'variant_label' => '5mg vial',
            'unit_price' => 2450,
            'quantity' => $quantity,
            'line_total' => 2450 * $quantity,
        ]);

        // Checkout already took the stock.
        $this->variant->decrement('stock', $quantity);
        $order->forceFill(['order_number' => Order::referenceFor($order->id)])->save();

        return $order->refresh();
    }

    // ----- happy path -------------------------------------------------------

    public function test_the_full_fulfillment_path_stamps_every_timestamp(): void
    {
        $order = $this->order();

        $this->post(route('admin.orders.verify-payment', $order))->assertRedirect();
        $this->post(route('admin.orders.processing', $order))->assertRedirect();
        $this->post(route('admin.orders.ship', $order), [
            'tracking_number' => 'JT2260041188PH',
        ])->assertRedirect();
        $this->post(route('admin.orders.complete', $order))->assertRedirect();

        $order->refresh();

        $this->assertSame('verified', $order->payment_status);
        $this->assertSame('completed', $order->order_status);
        $this->assertNotNull($order->payment_verified_at);
        $this->assertNotNull($order->processing_at);
        $this->assertNotNull($order->shipped_at);
        $this->assertNotNull($order->completed_at);
        $this->assertSame('JT2260041188PH', $order->tracking_number);
        // Blank shipped_via falls back to the checkout-time courier.
        $this->assertSame('J&T Express', $order->shipped_via);
    }

    public function test_shipped_via_can_override_the_checkout_courier(): void
    {
        $order = $this->order(['payment_status' => 'verified', 'order_status' => 'processing']);

        $this->post(route('admin.orders.ship', $order), [
            'tracking_number' => 'X1',
            'shipped_via' => 'LBC Express',
        ]);

        $this->assertSame('LBC Express', $order->fresh()->shipped_via);
    }

    // ----- guards -----------------------------------------------------------

    public function test_a_pending_order_cannot_be_shipped(): void
    {
        $order = $this->order();

        $this->post(route('admin.orders.ship', $order), ['tracking_number' => 'X1']);

        $this->assertSame('pending', $order->fresh()->order_status);
    }

    public function test_processing_requires_a_verified_payment(): void
    {
        $order = $this->order();

        $this->post(route('admin.orders.processing', $order));

        $this->assertSame('pending', $order->fresh()->order_status);
    }

    public function test_a_processing_order_cannot_be_completed(): void
    {
        $order = $this->order(['payment_status' => 'verified', 'order_status' => 'processing']);

        $this->post(route('admin.orders.complete', $order));

        $this->assertSame('processing', $order->fresh()->order_status);
    }

    public function test_an_already_rejected_payment_cannot_be_verified(): void
    {
        $order = $this->order(['payment_status' => 'rejected', 'order_status' => 'cancelled']);

        $this->post(route('admin.orders.verify-payment', $order));

        $this->assertSame('rejected', $order->fresh()->payment_status);
    }

    public function test_an_already_verified_payment_cannot_be_rejected(): void
    {
        $order = $this->order(['payment_status' => 'verified']);
        $stock = (int) $this->variant->fresh()->stock;

        $this->post(route('admin.orders.reject-payment', $order), ['reason' => 'nope']);

        $this->assertSame('verified', $order->fresh()->payment_status);
        $this->assertSame($stock, (int) $this->variant->fresh()->stock, 'a refused reject moved stock');
    }

    public function test_a_completed_order_cannot_be_cancelled(): void
    {
        $order = $this->order(['payment_status' => 'verified', 'order_status' => 'completed']);
        $stock = (int) $this->variant->fresh()->stock;

        $this->post(route('admin.orders.cancel', $order));

        $this->assertSame('completed', $order->fresh()->order_status);
        $this->assertSame($stock, (int) $this->variant->fresh()->stock);
    }

    /** A blocked action must say why, not fail silently. */
    public function test_a_blocked_action_flashes_a_specific_message(): void
    {
        $order = $this->order(['payment_status' => 'verified']);

        $this->post(route('admin.orders.verify-payment', $order));

        $this->assertStringContainsString(
            'already',
            session('inertia.flash_data.toast.message', ''),
        );
    }

    // ----- stock restoration ------------------------------------------------

    public function test_rejecting_payment_restores_stock_exactly_once(): void
    {
        $order = $this->order(quantity: 3);
        $this->assertSame(7, (int) $this->variant->fresh()->stock);

        $this->post(route('admin.orders.reject-payment', $order), ['reason' => 'Transfer never landed.']);

        $order->refresh();

        $this->assertSame('rejected', $order->payment_status);
        $this->assertSame('cancelled', $order->order_status);
        $this->assertSame('Transfer never landed.', $order->cancellation_reason);
        $this->assertNotNull($order->cancelled_at);
        $this->assertSame(10, (int) $this->variant->fresh()->stock, 'stock was not restored');

        // A second attempt is refused by the guard, so the restore cannot run twice.
        $this->post(route('admin.orders.reject-payment', $order), ['reason' => 'again']);
        $this->assertSame(10, (int) $this->variant->fresh()->stock, 'stock was restored twice');
    }

    public function test_cancelling_restores_stock_exactly_once(): void
    {
        $order = $this->order(['payment_status' => 'verified'], quantity: 4);
        $this->assertSame(6, (int) $this->variant->fresh()->stock);

        $this->post(route('admin.orders.cancel', $order), ['reason' => 'Customer changed their mind.']);

        $this->assertSame(10, (int) $this->variant->fresh()->stock);
        $this->assertSame('cancelled', $order->fresh()->order_status);

        // Cancelled is terminal, so the shared restore can only ever run once.
        $this->post(route('admin.orders.cancel', $order));
        $this->assertSame(10, (int) $this->variant->fresh()->stock, 'stock was restored twice');
    }

    public function test_cancelling_a_shipped_order_still_restores_stock(): void
    {
        $order = $this->order(['payment_status' => 'verified', 'order_status' => 'shipped'], quantity: 2);
        $this->assertSame(8, (int) $this->variant->fresh()->stock);

        $this->post(route('admin.orders.cancel', $order));

        $this->assertSame(10, (int) $this->variant->fresh()->stock);
    }

    public function test_restoring_skips_an_item_whose_variant_was_deleted(): void
    {
        $order = $this->order(quantity: 3);
        $this->variant->delete();

        $this->post(route('admin.orders.reject-payment', $order))->assertRedirect();

        // The order still cancels; the orphaned line simply has nothing to
        // give back. product_variant_id is nullable precisely for this.
        $this->assertSame('cancelled', $order->fresh()->order_status);
        $this->assertNull($order->items()->first()->product_variant_id);
    }

    // ----- payment proof ----------------------------------------------------

    public function test_the_payment_proof_streams_inline_when_present(): void
    {
        Storage::fake('local');
        $order = $this->order();

        $path = $order->payment_proof_path;
        Storage::disk('local')->put($path, UploadedFile::fake()->image('receipt.jpg')->getContent());

        $response = $this->get(route('admin.orders.payment-proof', $order));

        $response->assertOk();
        $this->assertStringContainsString('inline', $response->headers->get('content-disposition'));
    }

    public function test_the_payment_proof_404s_when_the_file_is_missing(): void
    {
        Storage::fake('local');
        $order = $this->order();

        $this->get(route('admin.orders.payment-proof', $order))->assertNotFound();
    }

    public function test_the_payment_proof_404s_when_the_order_has_no_path(): void
    {
        Storage::fake('local');
        $order = $this->order(['payment_proof_path' => '']);

        $this->get(route('admin.orders.payment-proof', $order))->assertNotFound();
    }

    // ----- pages ------------------------------------------------------------

    public function test_the_orders_index_renders(): void
    {
        $this->order();

        $this->get(route('admin.orders.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('admin/orders/Index')
                ->has('orders', 1)
                ->has('paymentStatuses')
                ->has('orderStatuses')
            );
    }

    public function test_the_order_detail_renders_snapshots_without_leaking_the_proof_path(): void
    {
        Storage::fake('local');
        $order = $this->order();
        Storage::disk('local')->put($order->payment_proof_path, 'x');

        $this->get(route('admin.orders.show', $order))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('admin/orders/Show')
                ->where('order.payment_method_name', 'GOtyme Bank')
                ->where('order.shipping_courier_name', 'J&T Express')
                ->where('order.has_payment_proof', true)
                ->where('order.payment_proof_extension', 'jpg')
                ->missing('order.payment_proof_path')
            );
    }

    public function test_admin_order_routes_require_authentication(): void
    {
        $order = $this->order();

        auth()->logout();

        $this->get(route('admin.orders.index'))->assertRedirect(route('login'));
        $this->get(route('admin.orders.show', $order))->assertRedirect(route('login'));
        $this->get(route('admin.orders.payment-proof', $order))->assertRedirect(route('login'));
        $this->post(route('admin.orders.verify-payment', $order))->assertRedirect(route('login'));
    }
}
