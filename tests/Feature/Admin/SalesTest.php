<?php

namespace Tests\Feature\Admin;

use App\Models\Category;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use App\Support\SalesPeriod;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Sales: aggregate reporting over the same orders the Dashboard counts.
 *
 * The thing most worth pinning is that there is only one definition of
 * revenue. Two hand-written copies of "verified, not cancelled, dated by
 * payment_verified_at" would agree on the day they were written and disagree
 * the first time one of them was adjusted — and nothing would notice, because
 * both would still look plausible.
 */
class SalesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Mid-month, so "this month" has room on both sides of it and a test
        // run on the 1st or the 31st behaves like every other day.
        Carbon::setTestNow(Carbon::parse('2026-09-15 10:00:00'));

        $this->actingAs(User::factory()->create(['email_verified_at' => now()]));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    private function variant(string $product, float $price, string $slug): ProductVariant
    {
        $category = Category::firstOrCreate(['name' => 'Healing'], ['slug' => 'healing']);

        return Product::create([
            'category_id' => $category->id,
            'name' => $product,
            'slug' => $slug,
            'status' => 'active',
            'short_description' => 'Peptide.',
        ])->variants()->create([
            'label' => '5mg vial',
            'price' => $price,
            'stock' => 100,
            'is_kit' => false,
            'sort_order' => 0,
        ]);
    }

    /**
     * An order as the admin screens would leave it.
     *
     * @param  array<int, array{0: string, 1: float, 2: int, 3: ?ProductVariant}>  $lines
     */
    private function order(
        string $verifiedAt,
        array $lines,
        string $paymentStatus = 'verified',
        string $orderStatus = 'completed',
        string $name = 'Juan Dela Cruz',
    ): Order {
        $subtotal = array_sum(array_map(fn ($line) => $line[1] * $line[2], $lines));

        $order = Order::create([
            'confirmation_token' => Str::random(40),
            'name' => $name,
            'social_handle' => '',
            'phone' => '09171234567',
            'street' => '12 Mabini St',
            'barangay' => 'San Antonio',
            'city' => 'Makati',
            'province' => 'Metro Manila',
            'zip' => '1203',
            'shipping_courier_name' => 'J&T Express',
            'shipping_region_label' => 'Luzon & Visayas',
            'shipping_fee' => 150,
            'subtotal' => $subtotal,
            'total' => $subtotal + 150,
            'payment_method_name' => 'GOtyme Bank',
            'payment_method_details' => [],
            'payment_proof_path' => 'payment-proofs/'.Str::uuid().'.jpg',
            'payment_status' => $paymentStatus,
            'order_status' => $orderStatus,
            // Set even on unverified orders in a couple of tests, deliberately:
            // the date alone must never be enough to count something.
            'payment_verified_at' => $verifiedAt,
        ]);

        foreach ($lines as [$productName, $unitPrice, $quantity, $variant]) {
            $order->items()->create([
                'product_variant_id' => $variant?->id,
                'product_name' => $productName,
                'variant_label' => '5mg vial',
                'unit_price' => $unitPrice,
                'quantity' => $quantity,
                'line_total' => $unitPrice * $quantity,
            ]);
        }

        $order->forceFill(['order_number' => Order::referenceFor($order->id)])->save();

        return $order->refresh();
    }

    /**
     * @return array<string, mixed>
     */
    private function sales(array $query = []): array
    {
        return $this->get(route('admin.sales.index', $query))
            ->assertOk()
            ->inertiaProps();
    }

    // ----- one definition of revenue ----------------------------------------

    /**
     * The headline figure and the Dashboard tile are the same number.
     *
     * Not "close to" — identical, because both call Order::revenueQuery(). The
     * whole point of extracting that rule was that this assertion can never
     * pass by coincidence.
     */
    public function test_this_month_matches_the_dashboard_figure_exactly(): void
    {
        $this->order('2026-09-02 09:00:00', [['BPC-157', 2450, 2, null]]);
        $this->order('2026-09-14 16:30:00', [['TB-500', 1200, 1, null]]);
        // Last month, so only the Dashboard's month boundary decides this one.
        $this->order('2026-08-28 09:00:00', [['BPC-157', 2450, 5, null]]);

        $dashboard = $this->get(route('dashboard'))
            ->assertOk()
            ->inertiaProps()['stats']['revenue_this_month'];

        $this->assertSame(
            (float) $dashboard,
            (float) $this->sales(['range' => SalesPeriod::THIS_MONTH])['revenue'],
        );

        // And it is the figure the arithmetic says it should be: (4900 + 150)
        // plus (1200 + 150), with August left out.
        $this->assertSame(6400.0, (float) $dashboard);
    }

    /**
     * A custom range covering the current month reaches the same total by a
     * different route — the generalisation did not change the rule.
     */
    public function test_a_custom_range_over_the_same_month_matches_the_dashboard(): void
    {
        $this->order('2026-09-02 09:00:00', [['BPC-157', 2450, 2, null]]);
        // 15:50 UTC is 23:50 on September 30 in Manila.
        $this->order('2026-09-30 15:50:00', [['TB-500', 1200, 1, null]]);

        $dashboard = $this->get(route('dashboard'))
            ->assertOk()
            ->inertiaProps()['stats']['revenue_this_month'];

        $custom = $this->sales([
            'range' => SalesPeriod::CUSTOM,
            'start' => '2026-09-01',
            'end' => '2026-09-30',
        ]);

        $this->assertSame((float) $dashboard, (float) $custom['revenue']);
        // The 23:50 order proves the end bound covers the whole final day.
        $this->assertSame(6400.0, (float) $custom['revenue']);
    }

    // ----- what never counts -------------------------------------------------

    /**
     * @return array<int, array{0: string, 1: string}>
     */
    public static function excludedOrders(): array
    {
        return [
            'unverified payment' => ['unverified', 'pending'],
            'rejected payment' => ['rejected', 'cancelled'],
            // The one that is easy to miss: verified, then cancelled. The money
            // was refunded outside this system, so counting it overstates.
            'verified then cancelled' => ['verified', 'cancelled'],
        ];
    }

    #[DataProvider('excludedOrders')]
    public function test_an_excluded_order_reaches_neither_revenue_the_series_nor_the_export(
        string $paymentStatus,
        string $orderStatus,
    ): void {
        $this->order('2026-09-10 09:00:00', [['BPC-157', 2450, 1, null]]);
        $excluded = $this->order(
            '2026-09-11 09:00:00',
            [['TB-500', 9999, 1, null]],
            $paymentStatus,
            $orderStatus,
            'Excluded Customer',
        );

        $props = $this->sales();

        $this->assertSame(2600.0, (float) $props['revenue']);
        $this->assertSame(1, $props['orderCount']);

        // Nothing on the 11th, where the excluded order sits.
        $eleventh = collect($props['series'])->firstWhere('date', '2026-09-11');
        $this->assertSame(0.0, (float) $eleventh['revenue']);

        // Nor in the rankings.
        $this->assertSame(
            ['BPC-157'],
            array_column($props['topProducts']['by_revenue'], 'product_name'),
        );

        $csv = $this->csv();

        $this->assertStringNotContainsString($excluded->order_number, $csv);
        $this->assertStringNotContainsString('Excluded Customer', $csv);
    }

    // ----- the daily series --------------------------------------------------

    public function test_the_series_has_one_point_per_day_including_empty_ones(): void
    {
        $this->order('2026-09-03 09:00:00', [['BPC-157', 1000, 1, null]]);
        $this->order('2026-09-03 15:00:00', [['BPC-157', 500, 1, null]]);
        $this->order('2026-09-05 09:00:00', [['TB-500', 2000, 1, null]]);

        $props = $this->sales([
            'range' => SalesPeriod::CUSTOM,
            'start' => '2026-09-01',
            'end' => '2026-09-07',
        ]);

        $series = collect($props['series']);

        $this->assertCount(7, $series, 'a day with no sales was dropped');
        $this->assertSame('2026-09-01', $series->first()['date']);
        $this->assertSame('2026-09-07', $series->last()['date']);

        // Two orders on the 3rd sum into one point: 1150 + 650.
        $this->assertSame(1800.0, (float) $series->firstWhere('date', '2026-09-03')['revenue']);
        $this->assertSame(2150.0, (float) $series->firstWhere('date', '2026-09-05')['revenue']);
        $this->assertSame(0.0, (float) $series->firstWhere('date', '2026-09-04')['revenue']);

        // And the points add up to the headline figure above them.
        $this->assertSame(
            round((float) $props['revenue'], 2),
            round($series->sum(fn ($point) => (float) $point['revenue']), 2),
        );
    }

    public function test_a_utc_timestamp_is_bucketed_on_the_following_manila_date(): void
    {
        $this->order('2026-09-10 15:59:59', [['Before midnight', 100, 1, null]]);
        $this->order('2026-09-10 16:00:00', [['At midnight', 200, 1, null]]);

        $series = collect($this->sales([
            'range' => SalesPeriod::CUSTOM,
            'start' => '2026-09-10',
            'end' => '2026-09-11',
        ])['series']);

        $this->assertSame(250.0, (float) $series->firstWhere('date', '2026-09-10')['revenue']);
        $this->assertSame(350.0, (float) $series->firstWhere('date', '2026-09-11')['revenue']);
    }

    // ----- top products ------------------------------------------------------

    /**
     * The ranking is the order lines' own snapshot, not the catalogue.
     *
     * A product repriced after the sale, and one deleted outright, both have to
     * keep reporting what they actually sold for. A live join would rewrite the
     * first and silently drop the second — and the total would stop matching
     * the revenue figure sitting right above it.
     */
    public function test_top_products_survive_a_reprice_and_a_deletion(): void
    {
        $repriced = $this->variant('BPC-157', 2450, 'bpc-157');
        $deleted = $this->variant('GHK-Cu', 1000, 'ghk-cu');

        $this->order('2026-09-04 09:00:00', [['BPC-157', 2450, 2, $repriced]]);
        $this->order('2026-09-06 09:00:00', [['GHK-Cu', 1000, 3, $deleted]]);

        // The catalogue moves on after the sales were made.
        $repriced->update(['price' => 9999]);
        $deleted->product->delete();

        $byRevenue = $this->sales()['topProducts']['by_revenue'];

        $this->assertSame(['BPC-157', 'GHK-Cu'], array_column($byRevenue, 'product_name'));
        // 2 x 2450 at the price it sold for, not the 9999 it costs now.
        $this->assertSame(4900.0, (float) $byRevenue[0]['revenue']);
        // And the deleted product is still here, with its own numbers.
        $this->assertSame(3000.0, (float) $byRevenue[1]['revenue']);
        $this->assertSame(3, (int) $byRevenue[1]['units']);
    }

    /** The two rankings answer different questions and may disagree. */
    public function test_by_units_ranks_separately_from_by_revenue(): void
    {
        // One expensive kit against many cheap vials.
        $this->order('2026-09-04 09:00:00', [['Semaglutide kit', 5000, 1, null]]);
        $this->order('2026-09-05 09:00:00', [['BPC-157', 200, 12, null]]);

        $props = $this->sales();

        $this->assertSame(
            ['Semaglutide kit', 'BPC-157'],
            array_column($props['topProducts']['by_revenue'], 'product_name'),
        );
        $this->assertSame(
            ['BPC-157', 'Semaglutide kit'],
            array_column($props['topProducts']['by_units'], 'product_name'),
        );
    }

    /** Lines for the same product across orders collapse into one row. */
    public function test_the_same_product_across_orders_is_one_ranked_row(): void
    {
        $this->order('2026-09-04 09:00:00', [['BPC-157', 1000, 2, null]]);
        $this->order('2026-09-08 09:00:00', [['BPC-157', 1000, 3, null]]);

        $byRevenue = $this->sales()['topProducts']['by_revenue'];

        $this->assertCount(1, $byRevenue);
        $this->assertSame(5000.0, (float) $byRevenue[0]['revenue']);
        $this->assertSame(5, (int) $byRevenue[0]['units']);
    }

    // ----- comparison --------------------------------------------------------

    public function test_this_month_compares_against_the_previous_calendar_month(): void
    {
        $this->order('2026-09-10 09:00:00', [['BPC-157', 1000, 1, null]]);
        $this->order('2026-08-10 09:00:00', [['BPC-157', 500, 1, null]]);

        $props = $this->sales(['range' => SalesPeriod::THIS_MONTH]);

        $this->assertSame('2026-08-01', $props['comparison']['start']);
        $this->assertSame('2026-08-31', $props['comparison']['end']);
        $this->assertSame(650.0, (float) $props['comparison']['revenue']);
        $this->assertSame(1150.0, (float) $props['revenue']);
    }

    public function test_a_custom_range_compares_against_an_equal_window_before_it(): void
    {
        $this->order('2026-09-12 09:00:00', [['BPC-157', 1000, 1, null]]);
        $this->order('2026-09-08 09:00:00', [['BPC-157', 300, 1, null]]);

        $props = $this->sales([
            'range' => SalesPeriod::CUSTOM,
            'start' => '2026-09-11',
            'end' => '2026-09-13',
        ]);

        // Three days, so the comparison is the three days before the 11th.
        $this->assertSame('2026-09-08', $props['comparison']['start']);
        $this->assertSame('2026-09-10', $props['comparison']['end']);
        $this->assertSame(450.0, (float) $props['comparison']['revenue']);
    }

    public function test_comparison_periods_use_philippine_day_boundaries(): void
    {
        // These timestamps are one second apart in UTC but fall on consecutive
        // Philippine dates either side of midnight.
        $this->order('2026-09-10 15:59:59', [['Comparison', 100, 1, null]]);
        $this->order('2026-09-10 16:00:00', [['Current', 200, 1, null]]);

        $props = $this->sales([
            'range' => SalesPeriod::CUSTOM,
            'start' => '2026-09-11',
            'end' => '2026-09-11',
        ]);

        $this->assertSame('2026-09-10', $props['comparison']['start']);
        $this->assertSame('2026-09-10', $props['comparison']['end']);
        $this->assertSame(250.0, (float) $props['comparison']['revenue']);
        $this->assertSame(350.0, (float) $props['revenue']);
    }

    public function test_an_explicit_second_range_overrides_the_prior_period(): void
    {
        $this->order('2026-09-12 09:00:00', [['BPC-157', 1000, 1, null]]);
        $this->order('2026-07-04 09:00:00', [['BPC-157', 700, 1, null]]);

        $props = $this->sales([
            'range' => SalesPeriod::THIS_MONTH,
            'compare_start' => '2026-07-01',
            'compare_end' => '2026-07-31',
        ]);

        $this->assertSame('2026-07-01', $props['comparison']['start']);
        $this->assertSame(850.0, (float) $props['comparison']['revenue']);
    }

    // ----- range resolution --------------------------------------------------

    public function test_the_default_range_is_this_month(): void
    {
        $props = $this->sales();

        $this->assertSame(SalesPeriod::THIS_MONTH, $props['range']['key']);
        $this->assertSame('2026-09-01', $props['range']['start']);
        $this->assertSame('2026-09-30', $props['range']['end']);
        $this->assertSame('September 2026', $props['range']['label']);
    }

    public function test_month_presets_follow_the_philippine_calendar(): void
    {
        // Still August in UTC, already September in Manila.
        Carbon::setTestNow(Carbon::parse('2026-08-31 16:30:00', 'UTC'));

        $this->order('2026-08-31 15:59:59', [['August', 9000, 1, null]]);
        $this->order('2026-08-31 16:00:00', [['September', 1000, 1, null]]);

        $props = $this->sales(['range' => SalesPeriod::THIS_MONTH]);

        $this->assertSame('2026-09-01', $props['range']['start']);
        $this->assertSame('2026-09-30', $props['range']['end']);
        $this->assertSame('September 2026', $props['range']['label']);
        $this->assertSame(1150.0, (float) $props['revenue']);
    }

    public function test_last_month_resolves_to_the_previous_calendar_month(): void
    {
        $props = $this->sales(['range' => SalesPeriod::LAST_MONTH]);

        $this->assertSame('2026-08-01', $props['range']['start']);
        $this->assertSame('2026-08-31', $props['range']['end']);
        $this->assertSame('August 2026', $props['range']['label']);
    }

    /** A half-filled picker renders the page rather than erroring. */
    public function test_custom_without_both_dates_falls_back_to_this_month(): void
    {
        $props = $this->sales(['range' => SalesPeriod::CUSTOM, 'start' => '2026-09-01']);

        $this->assertSame(SalesPeriod::THIS_MONTH, $props['range']['key']);
        $this->assertSame('2026-09-01', $props['range']['start']);
    }

    public function test_reversed_custom_bounds_are_read_as_the_range_between_them(): void
    {
        $props = $this->sales([
            'range' => SalesPeriod::CUSTOM,
            'start' => '2026-09-20',
            'end' => '2026-09-10',
        ]);

        $this->assertSame('2026-09-10', $props['range']['start']);
        $this->assertSame('2026-09-20', $props['range']['end']);
    }

    /** A mistyped year is trimmed, not turned into a several-thousand-point series. */
    public function test_an_over_long_range_is_clamped(): void
    {
        $props = $this->sales([
            'range' => SalesPeriod::CUSTOM,
            'start' => '2016-09-01',
            'end' => '2026-09-30',
        ]);

        $this->assertSame(SalesPeriod::MAX_DAYS, $props['range']['days']);
        $this->assertSame('2026-09-30', $props['range']['end']);
        $this->assertCount(SalesPeriod::MAX_DAYS, $props['series']);
    }

    public function test_an_unknown_range_key_is_refused(): void
    {
        $this->get(route('admin.sales.index', ['range' => 'all_time']))
            ->assertSessionHasErrors('range');
    }

    // ----- export ------------------------------------------------------------

    private function csv(array $query = []): string
    {
        $response = $this->get(route('admin.sales.export', $query));

        $response->assertOk();
        $response->assertHeader('content-type', 'text/csv; charset=UTF-8');

        return $response->streamedContent();
    }

    /**
     * The export parsed back the way a spreadsheet would read it.
     *
     * Parsed rather than string-matched: fputcsv quotes any field containing a
     * space, so "Order Number" arrives quoted and a raw substring assertion
     * would really be testing PHP's quoting rules rather than the contents.
     *
     * @return array<int, array<int, string>>
     */
    private function csvRows(array $query = []): array
    {
        $body = ltrim($this->csv($query), "\xEF\xBB\xBF");

        return array_map(
            fn (string $line) => str_getcsv($line, escape: '\\'),
            array_values(array_filter(explode("\n", trim($body)), 'strlen')),
        );
    }

    public function test_the_export_contains_exactly_the_rows_in_the_range(): void
    {
        $inside = $this->order('2026-09-10 14:30:00', [['BPC-157', 2450, 1, null]], name: 'Ana Reyes');
        $alsoInside = $this->order('2026-09-12 08:00:00', [['TB-500', 1000, 2, null]], name: 'Ben Cruz');
        $before = $this->order('2026-08-31 15:59:00', [['BPC-157', 100, 1, null]], name: 'Too Early');
        $after = $this->order('2026-09-30 16:00:00', [['BPC-157', 100, 1, null]], name: 'Too Late');

        $rows = $this->csvRows(['range' => SalesPeriod::THIS_MONTH]);

        // Header plus exactly the two orders inside the window.
        $this->assertCount(3, $rows);
        $this->assertSame(
            ['Order Number', 'Date Verified', 'Customer', 'Total'],
            $rows[0],
        );

        // Oldest first, so the file reads chronologically.
        $this->assertSame(
            [$inside->order_number, '2026-09-10 22:30:00', 'Ana Reyes', '2600.00'],
            $rows[1],
        );
        $this->assertSame(
            [$alsoInside->order_number, '2026-09-12 16:00:00', 'Ben Cruz', '2150.00'],
            $rows[2],
        );

        $exported = array_column(array_slice($rows, 1), 0);
        $this->assertNotContains($before->order_number, $exported);
        $this->assertNotContains($after->order_number, $exported);
    }

    public function test_the_export_follows_a_custom_range(): void
    {
        $this->order('2026-09-05 09:00:00', [['BPC-157', 100, 1, null]], name: 'Outside');
        $inside = $this->order('2026-09-20 09:00:00', [['BPC-157', 100, 1, null]], name: 'Inside');

        $rows = $this->csvRows([
            'range' => SalesPeriod::CUSTOM,
            'start' => '2026-09-15',
            'end' => '2026-09-25',
        ]);

        $this->assertCount(2, $rows);
        $this->assertSame($inside->order_number, $rows[1][0]);
        $this->assertSame('Inside', $rows[1][2]);
    }

    public function test_a_custom_ranges_final_philippine_day_is_consistent_everywhere(): void
    {
        $inside = $this->order(
            '2026-09-25 15:59:59',
            [['Final-day product', 1000, 1, null]],
            name: 'Final Day',
        );
        $outside = $this->order(
            '2026-09-25 16:00:00',
            [['Next-day product', 9000, 1, null]],
            name: 'Next Day',
        );
        $query = [
            'range' => SalesPeriod::CUSTOM,
            'start' => '2026-09-20',
            'end' => '2026-09-25',
        ];

        $props = $this->sales($query);
        $rows = $this->csvRows($query);
        $orders = $this->get(route('admin.orders.index', [
            'payment_status' => 'verified',
            'exclude_cancelled' => 1,
            'verified_from' => '2026-09-20',
            'verified_to' => '2026-09-25',
        ]))->assertOk()->inertiaProps()['orders'];

        $this->assertSame(1150.0, (float) $props['revenue']);
        $this->assertSame(1, $props['orderCount']);
        $this->assertSame(
            ['Final-day product'],
            array_column($props['topProducts']['by_revenue'], 'product_name'),
        );
        $this->assertSame($inside->order_number, $rows[1][0]);
        $this->assertSame('2026-09-25 23:59:59', $rows[1][1]);
        $this->assertCount(2, $rows);
        $this->assertSame([$inside->order_number], array_column($orders, 'order_number'));
        $this->assertNotContains($outside->order_number, array_column($orders, 'order_number'));
    }

    /**
     * The export runs its own query, so an empty range is a header and nothing
     * else rather than a broken file.
     */
    public function test_an_empty_range_exports_only_the_header(): void
    {
        $this->assertCount(1, $this->csvRows());
    }

    /** Excel reads the file as UTF-8 only if the BOM is there. */
    public function test_the_export_opens_as_utf8(): void
    {
        $this->assertStringStartsWith("\xEF\xBB\xBF", $this->csv());
    }

    public function test_a_guest_can_reach_neither_the_page_nor_the_export(): void
    {
        auth()->logout();

        $this->get(route('admin.sales.index'))->assertRedirect(route('login'));
        $this->get(route('admin.sales.export'))->assertRedirect(route('login'));
    }

    // ----- the link back to Orders -------------------------------------------

    /**
     * Orders, reached from Sales, lands on exactly what Sales counted.
     *
     * All three conditions travel with the link. The date alone would list the
     * verified-then-cancelled order the figure deliberately left out, and the
     * admin would be looking at a list that does not add up to the number they
     * clicked.
     */
    public function test_orders_pre_filters_to_the_same_orders_sales_counted(): void
    {
        $counted = $this->order('2026-09-10 09:00:00', [['BPC-157', 1000, 1, null]]);
        $cancelled = $this->order('2026-09-11 09:00:00', [['BPC-157', 1000, 1, null]], 'verified', 'cancelled');
        $unverified = $this->order('2026-09-12 09:00:00', [['BPC-157', 1000, 1, null]], 'unverified', 'pending');
        $lastMonth = $this->order('2026-08-10 09:00:00', [['BPC-157', 1000, 1, null]]);

        $props = $this->get(route('admin.orders.index', [
            'payment_status' => 'verified',
            'exclude_cancelled' => 1,
            'verified_from' => '2026-09-01',
            'verified_to' => '2026-09-30',
        ]))->assertOk()->inertiaProps();

        $this->assertSame(
            [$counted->order_number],
            array_column($props['orders'], 'order_number'),
        );

        foreach ([$cancelled, $unverified, $lastMonth] as $excluded) {
            $this->assertNotContains(
                $excluded->order_number,
                array_column($props['orders'], 'order_number'),
            );
        }

        // And the count matches what Sales reported for the same window.
        $this->assertSame(
            $this->sales(['range' => SalesPeriod::THIS_MONTH])['orderCount'],
            count($props['orders']),
        );

        // The page is told what was applied, so it can say so.
        $this->assertSame('verified', $props['appliedFilters']['payment_status']);
        $this->assertTrue($props['appliedFilters']['exclude_cancelled']);
    }

    /** An ordinary visit to Orders is untouched by any of that. */
    public function test_an_unfiltered_orders_visit_still_lists_everything(): void
    {
        $this->order('2026-09-10 09:00:00', [['BPC-157', 1000, 1, null]]);
        $this->order('2026-09-11 09:00:00', [['BPC-157', 1000, 1, null]], 'unverified', 'pending');

        $props = $this->get(route('admin.orders.index'))->assertOk()->inertiaProps();

        $this->assertCount(2, $props['orders']);
        $this->assertNull($props['appliedFilters']['payment_status']);
        $this->assertNull($props['appliedFilters']['exclude_cancelled']);
        $this->assertNull($props['appliedFilters']['verified_from']);
    }

    // ----- wiring ------------------------------------------------------------

    /** Top-level, beside Orders — not a child of Products. */
    public function test_sales_is_a_top_level_sidebar_item(): void
    {
        $sidebar = file_get_contents(resource_path('js/components/AppSidebar.vue'));

        $this->assertStringContainsString("title: 'Sales',", $sidebar);
        $this->assertStringContainsString('url: salesIndex()', $sidebar);

        // Not inside the Products group, which is the nesting mistake this
        // would most plausibly be built with. Scoped to that group's own
        // children array rather than to a brace-counting guess.
        $this->assertStringNotContainsString('Sales', $this->productsChildren($sidebar));
    }

    /**
     * The `items: [...]` array under the sidebar's Products group.
     *
     * Bounded on both ends so a positive assertion cannot accidentally be
     * reading the whole file and passing on some other group's contents.
     */
    private function productsChildren(string $sidebar): string
    {
        return str($sidebar)
            ->after("title: 'Products',")
            ->after('items: [')
            ->before('],')
            ->value();
    }

    /** The page uses the chart primitives already vendored here. */
    public function test_the_chart_uses_the_existing_chart_components(): void
    {
        $chart = file_get_contents(
            resource_path('js/pages/admin/sales/partials/RevenueChart.vue'),
        );

        $this->assertStringContainsString("from '@/components/ui/chart'", $chart);
        $this->assertStringContainsString('ChartContainer', $chart);
    }

    /**
     * The summary cards are a second view of numbers the page already has.
     *
     * Asserted against source for the same reason the others here are: there
     * is no SSR bundle, so a request returns the Inertia shell. Two things
     * worth pinning — that no third revenue figure was invented server-side,
     * and that the average guards its divisor. A NaN reaching the operator
     * would look like a broken page rather than an empty month.
     */
    public function test_the_summary_cards_derive_from_the_existing_figures(): void
    {
        $props = $this->sales();

        // No new prop, and so no new query behind one: the cards are handed
        // the same revenue and orderCount everything else on the page reads.
        $this->assertArrayNotHasKey('averageOrderValue', $props);
        $this->assertArrayHasKey('revenue', $props);
        $this->assertArrayHasKey('orderCount', $props);

        $cards = file_get_contents(
            resource_path('js/pages/admin/sales/partials/SummaryCards.vue'),
        );

        $this->assertStringContainsString('props.orderCount > 0', $cards);
        $this->assertStringContainsString('props.revenue / props.orderCount', $cards);

        // Plain current-period numbers — the comparison lives beside the chart.
        foreach (['TrendingUp', 'TrendingDown', 'percentChange'] as $trend) {
            $this->assertStringNotContainsString(
                $trend,
                $cards,
                "the summary cards carry [{$trend}]; the comparison belongs beside the chart",
            );
        }

        $page = file_get_contents(resource_path('js/pages/admin/sales/Index.vue'));

        $this->assertStringContainsString(':revenue="revenue"', $page);
        $this->assertStringContainsString(':order-count="orderCount"', $page);
    }

    /** No shrunken copy of the Orders table on this page. */
    public function test_the_page_does_not_list_individual_orders(): void
    {
        $page = file_get_contents(resource_path('js/pages/admin/sales/Index.vue'));

        $this->assertStringNotContainsString('DataTable', $page);
        // It links to Orders instead.
        $this->assertStringContainsString('ordersIndex', $page);
    }
}
