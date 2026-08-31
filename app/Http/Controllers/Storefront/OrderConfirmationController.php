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
            ->with(['items', 'courier', 'paymentMethod'])
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
                // Snapshots, so this still reads correctly after the courier or
                // method is renamed or deleted.
                'courier' => $order->shipping_courier_name ?: $order->courier?->name,
                'payment_method' => $order->payment_method_name ?: $order->paymentMethod?->name,
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
