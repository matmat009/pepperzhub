<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\ProductVariant;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The operator's landing screen.
 *
 * Deliberately five numbers and one short list, not a reporting suite. At one
 * order at a time and one admin, anything more is decoration: the Orders screen
 * already lists orders better than a shrunken copy here would, so this page
 * answers only "what needs me right now" and "how is the month going".
 */
class DashboardController extends Controller
{
    /** How many pending orders the widget lists before deferring to Orders. */
    private const PENDING_LIMIT = 5;

    public function index(): Response
    {
        return Inertia::render('admin/dashboard/Index', [
            'stats' => [
                'pending_verification' => $this->pendingQuery()->count(),
                'orders_today' => Order::query()
                    ->whereDate('created_at', Carbon::today())
                    ->count(),
                'revenue_this_month' => $this->revenueThisMonth(),
                /*
                 * The threshold is the model's, not this screen's. It used to
                 * be a local 10 copied from the hidden Inventory page, which
                 * meant this tile could disagree with the badge a customer was
                 * looking at on the same product. HandleInertiaRequests shares
                 * the same constant with the frontend, so no page prop for it
                 * is needed here.
                 */
                'low_stock' => ProductVariant::query()
                    ->where('stock', '<=', ProductVariant::LOW_STOCK_THRESHOLD)
                    ->count(),
            ],
            'pendingPayments' => $this->pendingPayments(),
        ]);
    }

    /**
     * Orders whose payment can actually still be acted on.
     *
     * Mirrors verifyPayment()'s guard exactly rather than filtering on the
     * payment axis alone. Cancelling a pending order leaves payment_status at
     * 'unverified' while order_status becomes 'cancelled', and that payment can
     * never be verified afterwards — listing it would put a row in front of the
     * admin that no action can clear.
     */
    private function pendingQuery(): Builder
    {
        return Order::query()
            ->where('payment_status', 'unverified')
            ->where('order_status', 'pending');
    }

    /**
     * Money actually taken this calendar month.
     *
     * Two conditions, and the second is the one that is easy to miss: a
     * verified payment is not enough. cancel()'s guard only looks at
     * order_status, so an order can be verified, moved to processing, and then
     * cancelled — at which point the money was almost certainly refunded
     * outside this system. Counting it would overstate revenue with no record
     * anywhere of the correction.
     *
     * Scoped on payment_verified_at rather than created_at: revenue belongs to
     * the month the money was confirmed, not the month the order was placed.
     */
    private function revenueThisMonth(): float
    {
        return (float) Order::query()
            ->where('payment_status', 'verified')
            ->where('order_status', '!=', 'cancelled')
            ->whereBetween('payment_verified_at', [
                Carbon::now()->startOfMonth(),
                Carbon::now()->endOfMonth(),
            ])
            ->sum('total');
    }

    /**
     * The oldest waiting payments, oldest first.
     *
     * Oldest rather than newest on purpose: the customer who has been waiting
     * longest is the one at risk, and a newest-first list would bury them.
     *
     * @return array<int, array<string, mixed>>
     */
    private function pendingPayments(): array
    {
        return $this->pendingQuery()
            ->oldest('created_at')
            ->limit(self::PENDING_LIMIT)
            ->get()
            ->map(fn (Order $order) => [
                'id' => $order->id,
                'order_number' => $order->order_number,
                'name' => $order->name,
                'total' => (float) $order->total,
                // Rendered here, not on the client: a raw timestamp makes the
                // reader do the subtraction. One part only, so a two-day-old
                // order reads "2 days" rather than "2 days 3 hours 9 minutes".
                'waiting_for' => $order->created_at?->diffForHumans(
                    syntax: CarbonInterface::DIFF_ABSOLUTE,
                    parts: 1,
                ),
            ])
            ->all();
    }
}
