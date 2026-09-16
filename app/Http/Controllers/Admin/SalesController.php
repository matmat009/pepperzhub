<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\OrderItem;
use App\Support\ReportingTime;
use App\Support\SalesPeriod;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Sales.
 *
 * Aggregate only. There is deliberately no list of individual orders here —
 * Orders already does that, with the actions attached — so the page ends in a
 * link back to it, pre-filtered to the same window and the same verified,
 * not-cancelled orders this screen counted. A shrunken copy of that table
 * would be a second place to look for the same rows and a second place for
 * them to disagree.
 *
 * Every figure comes off Order::revenueQuery(), which is also what the
 * Dashboard tile reads. There is one definition of revenue in this
 * application, and it is not in this file.
 */
class SalesController extends Controller
{
    /** How many products the rankings show before the tail stops mattering. */
    private const TOP_PRODUCTS_LIMIT = 8;

    public function index(Request $request): Response
    {
        $this->validateRange($request);

        $period = $this->period($request);
        $comparison = $this->comparison($request, $period);

        return Inertia::render('admin/sales/Index', [
            'range' => $period->toArray(),
            'revenue' => $this->revenue($period),
            'orderCount' => $this->orders($period)->count(),
            'comparison' => [
                ...$comparison->toArray(),
                'revenue' => $this->revenue($comparison),
            ],
            'series' => $this->dailySeries($period),
            'topProducts' => $this->topProducts($period),
            'rangeKeys' => SalesPeriod::keys(),
            'maxRangeDays' => SalesPeriod::MAX_DAYS,
        ]);
    }

    /**
     * The verified sales in the range, as a CSV.
     *
     * Runs its own query rather than reading anything the page assembled: an
     * export that depended on an on-screen table would silently start
     * exporting whatever that table happened to show, and there is no table
     * here at all.
     *
     * Streamed because the row count is unbounded — a year's range on a busy
     * shop should not be built in memory first.
     */
    public function export(Request $request): StreamedResponse
    {
        $this->validateRange($request);

        $period = $this->period($request);

        $filename = 'pepperzhub-sales-'.$period->start->toDateString()
            .'-to-'.$period->end->toDateString().'.csv';

        return response()->streamDownload(function () use ($period): void {
            $handle = fopen('php://output', 'wb');

            /*
             * A UTF-8 BOM, for Excel's benefit. Without it Excel reads the file
             * in the system codepage and the peso sign in a customer's name or
             * a non-ASCII character mangles on open — the one thing most likely
             * to make the operator distrust the export.
             */
            fwrite($handle, "\xEF\xBB\xBF");

            fputcsv($handle, ['Order Number', 'Date Verified', 'Customer', 'Total']);

            $this->orders($period)
                ->orderBy('payment_verified_at')
                ->orderBy('id')
                // Chunked for the same reason it is streamed.
                ->chunk(500, function ($orders) use ($handle): void {
                    foreach ($orders as $order) {
                        fputcsv($handle, [
                            $order->order_number,
                            $order->payment_verified_at
                                ? ReportingTime::local($order->payment_verified_at)->toDateTimeString()
                                : null,
                            $order->name,
                            // Unformatted: this is a number a spreadsheet has
                            // to be able to sum, not a label to read.
                            number_format((float) $order->total, 2, '.', ''),
                        ]);
                    }
                });

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Cache-Control' => 'private, no-store',
        ]);
    }

    private function validateRange(Request $request): void
    {
        $request->validate([
            'range' => ['nullable', 'string', 'in:'.implode(',', SalesPeriod::keys())],
            'start' => ['nullable', 'date'],
            'end' => ['nullable', 'date'],
            'compare_start' => ['nullable', 'date'],
            'compare_end' => ['nullable', 'date'],
        ]);
    }

    private function period(Request $request): SalesPeriod
    {
        return $this->clamp(SalesPeriod::resolve(
            $request->string('range')->value() ?: null,
            $request->string('start')->value() ?: null,
            $request->string('end')->value() ?: null,
        ));
    }

    /**
     * What the headline figure is measured against.
     *
     * An explicit second range wins when the admin has picked one; otherwise
     * the period supplies its own equivalent prior window.
     */
    private function comparison(Request $request, SalesPeriod $period): SalesPeriod
    {
        $start = $request->string('compare_start')->value();
        $end = $request->string('compare_end')->value();

        if (filled($start) && filled($end)) {
            return $this->clamp(SalesPeriod::custom(
                ReportingTime::date($start),
                ReportingTime::date($end),
            ));
        }

        return $period->previous();
    }

    /**
     * Keeps a mistyped year from asking for a series with thousands of points.
     *
     * Trimmed rather than rejected: the admin gets a page covering the most
     * recent MAX_DAYS of what they asked for, which is far more useful than a
     * validation error on a date they can see is wrong.
     */
    private function clamp(SalesPeriod $period): SalesPeriod
    {
        if ($period->dayCount() <= SalesPeriod::MAX_DAYS) {
            return $period;
        }

        return SalesPeriod::custom(
            $period->end->subDays(SalesPeriod::MAX_DAYS - 1),
            $period->end,
        );
    }

    private function revenue(SalesPeriod $period): float
    {
        return (float) $this->orders($period)->sum('total');
    }

    /** @return Builder<Order> */
    private function orders(SalesPeriod $period): Builder
    {
        return Order::revenueQuery(
            $period->queryStart(),
            $period->queryEndExclusive(),
        );
    }

    /**
     * One point per day, zeros included.
     *
     * Bucketed in PHP rather than grouped by a SQL date expression: the date
     * functions differ between MySQL, which this runs on, and the SQLite the
     * test suite uses, and the range is capped at a year of rows for a
     * single-operator shop. Correct on both beats marginally faster on one.
     *
     * @return array<int, array<string, mixed>>
     */
    private function dailySeries(SalesPeriod $period): array
    {
        $totals = array_fill_keys($period->days(), 0.0);

        $this->orders($period)
            ->get(['payment_verified_at', 'total'])
            ->each(function (Order $order) use (&$totals): void {
                $day = $order->payment_verified_at
                    ? ReportingTime::local($order->payment_verified_at)->toDateString()
                    : null;

                if ($day !== null && array_key_exists($day, $totals)) {
                    $totals[$day] += (float) $order->total;
                }
            });

        return collect($totals)
            ->map(fn (float $revenue, string $date) => [
                'date' => $date,
                'revenue' => round($revenue, 2),
            ])
            ->values()
            ->all();
    }

    /**
     * Best sellers in the range, from the order lines' own snapshots.
     *
     * Grouped by order_items.product_name, never by a join to products. The
     * snapshot is what was actually sold under that name at that price, so the
     * ranking stays truthful after a product is renamed, repriced or deleted
     * outright — which a live join would quietly rewrite or drop.
     *
     * Revenue sums line_total, the stored figure the customer was charged;
     * checkout writes it as unit_price × quantity rounded once, so re-deriving
     * the product here could disagree with the order's own total by a cent.
     *
     * @return array<string, array<int, array<string, mixed>>>
     */
    private function topProducts(SalesPeriod $period): array
    {
        $rows = OrderItem::query()
            ->whereIn(
                'order_id',
                $this->orders($period)->select('id'),
            )
            ->groupBy('product_name')
            ->select('product_name')
            ->selectRaw('SUM(line_total) as revenue')
            ->selectRaw('SUM(quantity) as units')
            ->get();

        // Name as the tiebreaker, so two products on equal footing keep a stable
        // order between one request and the next.
        $rank = fn (string $column) => $rows
            ->sortBy(fn ($row) => [-(float) $row->{$column}, $row->product_name])
            ->take(self::TOP_PRODUCTS_LIMIT)
            ->map(fn ($row) => [
                'product_name' => $row->product_name,
                'revenue' => round((float) $row->revenue, 2),
                'units' => (int) $row->units,
            ])
            ->values()
            ->all();

        return [
            'by_revenue' => $rank('revenue'),
            'by_units' => $rank('units'),
        ];
    }
}
