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
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    private ProductVariant $variant;

    protected function setUp(): void
    {
        parent::setUp();

        /*
         * Frozen mid-month and mid-day, because every figure on this page is
         * date-bounded. Run live, "two hours ago" falls into yesterday when the
         * suite happens to run just after UTC midnight, and the month scoping
         * would drift on the 1st — the tests would pass almost always and fail
         * on a schedule nobody would connect to the change that broke them.
         */
        Carbon::setTestNow(Carbon::parse('2026-06-15 12:00:00'));

        $this->actingAs(User::factory()->create(['email_verified_at' => now()]));

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
            'stock' => 100,
            'is_kit' => false,
            'sort_order' => 0,
        ]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    /**
     * An order as checkout would leave it, with hooks for the fields each test
     * actually cares about.
     *
     * @param  array<string, mixed>  $overrides
     */
    private function order(array $overrides = [], float $total = 1000): Order
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
            'subtotal' => $total,
            'total' => $total,
            'payment_method_id' => $method->id,
            'payment_method_name' => 'GOtyme Bank',
            'payment_method_details' => [['label' => 'Bank', 'value' => 'GOtyme Bank']],
            'payment_proof_path' => 'payment-proofs/'.Str::uuid().'.jpg',
        ], $overrides));

        $order->items()->create([
            'product_variant_id' => $this->variant->id,
            'product_name' => 'BPC-157',
            'variant_label' => '5mg vial',
            'unit_price' => $total,
            'quantity' => 1,
            'line_total' => $total,
        ]);

        $stamp = ['order_number' => Order::referenceFor($order->id)];

        // created_at is not fillable — Eloquent stamps it on insert and drops
        // the override — so the date-based figures need it forced afterwards.
        if (isset($overrides['created_at'])) {
            $stamp['created_at'] = $overrides['created_at'];
        }

        $order->forceFill($stamp)->save();

        return $order->refresh();
    }

    /** Money arrives as a JSON number: 5000.0 encodes as 5000. */
    private function revenue(): float
    {
        return (float) $this->props()['stats']['revenue_this_month'];
    }

    /**
     * @return array<string, mixed>
     */
    private function props(): array
    {
        $response = $this->get(route('dashboard'));

        $response->assertOk();

        return $response->inertiaProps();
    }

    // ----- access -----------------------------------------------------------

    public function test_the_dashboard_requires_authentication(): void
    {
        auth()->logout();

        $this->get(route('dashboard'))->assertRedirect(route('login'));
    }

    public function test_the_dashboard_renders_the_admin_page(): void
    {
        $this->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('admin/dashboard/Index')
                ->has('stats')
                ->has('pendingPayments')
                // Shared by HandleInertiaRequests rather than passed by this
                // controller, but present on the page either way.
                ->has('lowStockThreshold')
            );
    }

    // ----- revenue ----------------------------------------------------------

    /**
     * The case this whole widget turns on.
     *
     * cancel()'s guard only inspects order_status, so a verified payment can
     * still end up cancelled by going verified -> processing -> cancelled. That
     * money was almost certainly refunded outside the system, and counting it
     * would overstate the month with nothing anywhere recording the correction.
     *
     * Driven through the real admin routes rather than by setting columns, so
     * it is the actual reachable sequence being asserted.
     */
    public function test_revenue_excludes_a_verified_then_cancelled_order(): void
    {
        $order = $this->order(total: 5000);

        $this->post(route('admin.orders.verify-payment', $order))->assertRedirect();
        $this->post(route('admin.orders.processing', $order))->assertRedirect();
        $this->post(route('admin.orders.cancel', $order))->assertRedirect();

        $order->refresh();

        // The sequence really did happen: verified payment, cancelled order.
        $this->assertSame('verified', $order->payment_status);
        $this->assertSame('cancelled', $order->order_status);
        $this->assertNotNull($order->payment_verified_at);

        $this->assertSame(0.0, $this->revenue());
    }

    public function test_revenue_counts_a_verified_order_that_is_still_live(): void
    {
        $order = $this->order(total: 5000);

        $this->post(route('admin.orders.verify-payment', $order))->assertRedirect();

        $this->assertSame(5000.0, $this->revenue());
    }

    public function test_revenue_excludes_unverified_orders(): void
    {
        $this->order(total: 5000);

        $this->assertSame(0.0, $this->revenue());
    }

    public function test_revenue_excludes_rejected_orders(): void
    {
        $order = $this->order(total: 5000);

        $this->post(route('admin.orders.reject-payment', $order), ['reason' => 'no proof'])
            ->assertRedirect();

        $this->assertSame(0.0, $this->revenue());
    }

    public function test_revenue_is_scoped_to_the_current_calendar_month(): void
    {
        // Verified last month: real money, but not this month's figure.
        $this->order([
            'payment_status' => 'verified',
            'payment_verified_at' => Carbon::now()->subMonthNoOverflow()->startOfMonth()->addDay(),
        ], total: 9000);

        $this->order([
            'payment_status' => 'verified',
            'payment_verified_at' => Carbon::now()->startOfMonth()->addDay(),
        ], total: 1500);

        $this->assertSame(1500.0, $this->revenue());
    }

    /** Revenue belongs to the month the money was confirmed, not ordered. */
    public function test_revenue_is_dated_by_verification_not_by_order_date(): void
    {
        $this->order([
            'created_at' => Carbon::now()->subMonthNoOverflow(),
            'payment_status' => 'verified',
            'payment_verified_at' => Carbon::now()->startOfMonth()->addDay(),
        ], total: 2000);

        $this->assertSame(2000.0, $this->revenue());
    }

    // ----- pending payments -------------------------------------------------

    public function test_the_pending_list_excludes_verified_and_rejected_orders(): void
    {
        $waiting = $this->order();
        $verified = $this->order();
        $rejected = $this->order();

        $this->post(route('admin.orders.verify-payment', $verified));
        $this->post(route('admin.orders.reject-payment', $rejected), ['reason' => 'no proof']);

        $props = $this->props();
        $numbers = array_column($props['pendingPayments'], 'order_number');

        $this->assertSame([$waiting->order_number], $numbers);
        $this->assertSame(1, $props['stats']['pending_verification']);
    }

    /**
     * Cancelling a pending order leaves payment_status at 'unverified', but
     * verifyPayment can never run on it again — so it is not actionable and
     * must not sit in the list.
     */
    public function test_the_pending_list_excludes_a_cancelled_order_whose_payment_is_still_unverified(): void
    {
        $order = $this->order();

        $this->post(route('admin.orders.cancel', $order))->assertRedirect();

        $order->refresh();
        $this->assertSame('unverified', $order->payment_status);
        $this->assertSame('cancelled', $order->order_status);

        $props = $this->props();

        $this->assertSame([], $props['pendingPayments']);
        $this->assertSame(0, $props['stats']['pending_verification']);
    }

    public function test_the_pending_list_is_oldest_first_and_caps_at_five(): void
    {
        // Created newest-first, so a list that simply echoed insertion order
        // would fail this.
        foreach (range(0, 6) as $index) {
            $this->order(['created_at' => Carbon::now()->subDays($index)]);
        }

        $props = $this->props();
        $pending = $props['pendingPayments'];

        $this->assertCount(5, $pending, 'the list must cap at five');
        $this->assertSame(7, $props['stats']['pending_verification'], 'the count is not capped');

        $waited = array_column($pending, 'waiting_for');
        $this->assertSame('6 days', $waited[0], 'the oldest order must lead');
        $this->assertSame('2 days', $waited[4]);
    }

    public function test_each_pending_row_carries_what_the_widget_renders(): void
    {
        $order = $this->order(['created_at' => Carbon::now()->subDays(3)], total: 2450);

        $row = $this->props()['pendingPayments'][0];

        $this->assertSame($order->id, $row['id']);
        $this->assertSame($order->order_number, $row['order_number']);
        $this->assertSame('Juan Dela Cruz', $row['name']);
        $this->assertSame(2450.0, (float) $row['total']);
        // A rendered duration, not a timestamp for the reader to subtract.
        $this->assertSame('3 days', $row['waiting_for']);
    }

    // ----- orders today -----------------------------------------------------

    public function test_orders_today_counts_only_todays_orders(): void
    {
        $this->order(['created_at' => Carbon::now()]);
        $this->order(['created_at' => Carbon::now()->subHours(2)]);
        $this->order(['created_at' => Carbon::now()->subDays(1)]);

        $this->assertSame(2, $this->props()['stats']['orders_today']);
    }

    // ----- low stock --------------------------------------------------------

    public function test_low_stock_uses_the_shared_threshold_and_includes_zero(): void
    {
        $threshold = ProductVariant::LOW_STOCK_THRESHOLD;

        $product = Product::sole();

        // At the threshold counts, one above does not, and out of stock does.
        $this->variant->update(['stock' => $threshold]);
        $product->variants()->create(['label' => 'b', 'price' => 1, 'stock' => $threshold + 1, 'is_kit' => false, 'sort_order' => 1]);
        $product->variants()->create(['label' => 'c', 'price' => 1, 'stock' => 0, 'is_kit' => false, 'sort_order' => 2]);
        $product->variants()->create(['label' => 'd', 'price' => 1, 'stock' => $threshold - 1, 'is_kit' => false, 'sort_order' => 3]);

        $props = $this->props();

        $this->assertSame(3, $props['stats']['low_stock']);
        // The page is told the threshold, so it never restates the number.
        // Shared from the model, so the page never restates the number and
        // cannot disagree with the storefront badge.
        $this->assertSame($threshold, (int) $props['lowStockThreshold']);
    }
}
