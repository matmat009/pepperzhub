<?php

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Support\OrderTracker;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Reached by a random per-order token in the URL.
 *
 * The token replaced a session flash, which died on the first refresh and sent
 * a customer who had just paid to the homepage with no explanation. It is
 * Str::random(40) and unique, so it is not derivable from the order id or the
 * PZH- reference — one order's URL tells you nothing about another's.
 */
class OrderConfirmationController extends Controller
{
    /**
     * Looked up by hand rather than by route-model binding: a miss should send
     * someone home, not raise a bare 404, and it must not hint at whether some
     * other order exists.
     */
    public function show(string $token): Response|RedirectResponse
    {
        $order = Order::query()
            ->with('items')
            ->where('confirmation_token', $token)
            ->first();

        if (! $order) {
            return to_route('home');
        }

        return Inertia::render('storefront/OrderConfirmation', [
            'order' => [
                'order_number' => $order->order_number,
                'name' => $order->name,
                'phone' => $order->phone,
                'social_handle' => $order->social_handle,
                'subtotal' => (float) $order->subtotal,
                'shipping_fee' => (float) $order->shipping_fee,
                'total' => (float) $order->total,
                'shipping_region_label' => $order->shipping_region_label,
                /*
                 * Snapshots only — no fallback to the live relation, which
                 * would reintroduce exactly the drift the snapshot exists to
                 * prevent once a courier or method is renamed or retired.
                 *
                 * shipped_via is checked for symmetry with Track Order; on this
                 * page it is always null, since nothing has shipped yet.
                 */
                'courier' => filled($order->shipped_via)
                    ? $order->shipped_via
                    : $order->shipping_courier_name,
                'payment_method' => $order->payment_method_name,
                'items' => $order->items
                    ->map(fn ($item) => [
                        'product_name' => $item->product_name,
                        'variant_label' => $item->variant_label,
                        'unit_price' => (float) $item->unit_price,
                        'quantity' => $item->quantity,
                        'line_total' => (float) $item->line_total,
                    ])
                    ->all(),
            ],
            'tracker' => OrderTracker::payload($order),
        ]);
    }
}
