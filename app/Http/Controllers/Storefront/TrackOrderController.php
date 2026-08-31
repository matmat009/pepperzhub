<?php

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Support\OrderTracker;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Public order lookup.
 *
 * This is an unauthenticated two-field guess-and-check surface, so it is
 * rate-limited at the route and answers uniformly: one generic message for
 * every failure, whether the order number is unknown or the phone simply does
 * not match. Distinguishing them would turn it into an order-number oracle.
 */
class TrackOrderController extends Controller
{
    public function show(): Response
    {
        return Inertia::render('storefront/TrackOrder');
    }

    public function lookup(Request $request): Response
    {
        $data = $request->validate([
            'order_number' => ['required', 'string', 'max:32'],
            'phone' => ['required', 'string', 'max:32'],
        ]);

        $reference = Order::normalizeReference($data['order_number']);
        $phone = Order::normalizePhone($data['phone']);

        $order = null;

        // Normalising both sides in PHP rather than SQL keeps the comparison
        // identical to the client's, across whatever collation is in play.
        if ($reference !== '' && $phone !== '') {
            $order = Order::query()
                ->with('items')
                ->where('id', (int) $reference)
                ->get()
                ->first(fn (Order $candidate): bool => Order::normalizePhone($candidate->phone) === $phone);
        }

        if (! $order) {
            return Inertia::render('storefront/TrackOrder', [
                'result' => null,
                'notFound' => true,
            ]);
        }

        return Inertia::render('storefront/TrackOrder', [
            'notFound' => false,
            'result' => [
                'order_number' => $order->order_number,
                'name' => $order->name,
                'placed_at' => $order->created_at?->toDateString(),
                'subtotal' => (float) $order->subtotal,
                'shipping_fee' => (float) $order->shipping_fee,
                'total' => (float) $order->total,
                'shipping_region_label' => $order->shipping_region_label,
                /*
                 * Snapshot only, never the live courier relation. The FK is
                 * nullOnDelete, so a join would blank the courier on every
                 * historical order the day one is retired, and renaming one
                 * would silently rewrite what the customer was told.
                 *
                 * shipped_via wins once it is set: the courier chosen at
                 * checkout was a rate quote, and what actually carried the
                 * parcel is what the customer needs when chasing it.
                 */
                'courier' => filled($order->shipped_via)
                    ? $order->shipped_via
                    : $order->shipping_courier_name,
                'tracking_number' => $order->tracking_number,
                'items' => $order->items
                    ->map(fn ($item) => [
                        'product_name' => $item->product_name,
                        'variant_label' => $item->variant_label,
                        'unit_price' => (float) $item->unit_price,
                        'quantity' => $item->quantity,
                        'line_total' => (float) $item->line_total,
                    ])
                    ->all(),
                'tracker' => OrderTracker::payload($order),
            ],
        ]);
    }
}
