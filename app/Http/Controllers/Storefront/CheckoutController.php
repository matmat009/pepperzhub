<?php

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Http\Requests\Storefront\StoreCheckoutRequest;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\PaymentMethod;
use App\Models\ProductVariant;
use App\Models\ShippingCourier;
use App\Models\ShippingRegion;
use App\Support\SessionCart;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

class CheckoutController extends Controller
{
    public function show(): Response|RedirectResponse
    {
        $cart = SessionCart::hydrate();

        if ($cart['lines'] === []) {
            $this->toast('Your cart is empty — add a peptide before checking out.', 'error');

            return to_route('storefront.cart');
        }

        return Inertia::render('storefront/Checkout', [
            'lines' => $cart['lines'],
            'subtotal' => $cart['subtotal'],
            'paymentMethods' => PaymentMethod::query()
                ->where('is_active', true)
                ->orderBy('sort_order')
                ->get()
                ->map(fn (PaymentMethod $method) => [
                    'id' => $method->id,
                    'name' => $method->name,
                    'details' => $method->details ?? [],
                    'qr_code_url' => $method->qr_code_path
                        ? Storage::disk('public')->url($method->qr_code_path)
                        : null,
                ])
                ->all(),
            'couriers' => ShippingCourier::query()
                ->where('is_active', true)
                ->with('activeRegions')
                ->orderBy('sort_order')
                ->get()
                ->map(fn (ShippingCourier $courier) => [
                    'id' => $courier->id,
                    'name' => $courier->name,
                    'regions' => $courier->activeRegions
                        ->map(fn (ShippingRegion $region) => [
                            'id' => $region->id,
                            'name' => $region->name,
                            'note' => $region->note,
                            'rate' => (float) $region->rate,
                        ])
                        ->values()
                        ->all(),
                ])
                ->all(),
        ]);
    }

    /**
     * Place the order.
     *
     * Everything that decides money is recomputed here from the database:
     * unit prices from product_variants, shipping from shipping_regions. The
     * request carries no figures at all, so there is nothing to tamper with.
     *
     * Stock is deducted now, not at payment verification — an unverified order
     * still holds its stock, and rejecting it later puts the stock back.
     */
    public function store(StoreCheckoutRequest $request): RedirectResponse
    {
        /*
         * One checkout per session at a time. A double-click, an impatient
         * retry or a back-button resubmit would otherwise create two orders and
         * decrement stock twice. Non-blocking: a second request is turned away
         * immediately rather than queued behind the first.
         */
        $lock = Cache::lock('checkout:'.session()->getId(), 20);

        if (! $lock->get()) {
            $this->toast(
                'Your order may already be submitting - check Track Order in a moment before trying again.',
                'error',
            );

            return to_route('storefront.cart');
        }

        try {
            return $this->placeOrder($request);
        } finally {
            $lock->release();
        }
    }

    /**
     * The checkout itself, run under the session lock.
     *
     * Everything that decides money is recomputed here from the database: unit
     * prices from product_variants, shipping from shipping_regions. The request
     * carries no figures at all, so there is nothing to tamper with.
     *
     * Stock is deducted now, not at payment verification - an unverified order
     * still holds its stock, and rejecting it later puts the stock back.
     */
    private function placeOrder(StoreCheckoutRequest $request): RedirectResponse
    {
        $cart = SessionCart::raw();

        if ($cart === []) {
            $this->toast('Your cart is empty - add a peptide before checking out.', 'error');

            return to_route('storefront.cart');
        }

        $validated = $request->validated();
        $shortfalls = [];

        /*
         * The proof is stored before the transaction opens, on a UUID path that
         * does not depend on the order existing yet. That removes the old
         * two-step write, where the order was inserted with an empty path and
         * updated afterwards - a window in which a failure left a real order
         * with no proof attached.
         *
         * If this throws, nothing else has happened and there is nothing to
         * clean up. If the transaction below throws, the catch deletes the file
         * rather than orphaning it on disk.
         */
        $file = $request->file('payment_proof');
        $proofPath = $file->storeAs(
            'payment-proofs',
            Str::uuid()->toString().'.'.$file->extension(),
            'local',
        );

        try {
            $order = DB::transaction(function () use ($cart, $validated, $proofPath, &$shortfalls): ?Order {
                // Re-read inside the transaction: another checkout may have
                // taken the last unit between rendering the page and submitting.
                $variants = ProductVariant::query()
                    ->whereIn('id', array_keys($cart))
                    ->whereHas('product', fn ($query) => $query->where('status', 'active'))
                    ->with('product')
                    ->lockForUpdate()
                    ->get()
                    ->keyBy('id');

                $lines = [];
                $subtotal = 0.0;

                foreach ($cart as $variantId => $quantity) {
                    $variant = $variants->get($variantId);

                    if (! $variant) {
                        $shortfalls[] = 'An item in your cart is no longer available.';

                        continue;
                    }

                    if ($quantity > (int) $variant->stock) {
                        $shortfalls[] = sprintf(
                            '%s (%s): only %d left, you asked for %d.',
                            $variant->product->name,
                            $variant->label,
                            (int) $variant->stock,
                            $quantity,
                        );

                        continue;
                    }

                    $unitPrice = (float) $variant->price;
                    $lineTotal = round($unitPrice * $quantity, 2);
                    $subtotal += $lineTotal;

                    $lines[] = [
                        'variant' => $variant,
                        'unit_price' => $unitPrice,
                        'quantity' => $quantity,
                        'line_total' => $lineTotal,
                    ];
                }

                if ($shortfalls !== [] || $lines === []) {
                    return null;
                }

                // Live rate and live names, never a client-sent amount.
                $region = ShippingRegion::query()
                    ->whereKey($validated['shipping_region_id'])
                    ->with('courier')
                    ->firstOrFail();

                $paymentMethod = PaymentMethod::query()
                    ->whereKey($validated['payment_method_id'])
                    ->firstOrFail();

                $shippingFee = (float) $region->rate;
                $subtotal = round($subtotal, 2);

                $order = Order::create([
                    'confirmation_token' => Str::random(40),
                    'name' => $validated['name'],
                    'social_handle' => $validated['social_handle'],
                    'phone' => $validated['phone'],
                    'street' => $validated['street'],
                    'barangay' => $validated['barangay'],
                    'city' => $validated['city'],
                    'province' => $validated['province'],
                    'zip' => $validated['zip'],
                    'notes' => $validated['notes'] ?? null,
                    'shipping_courier_id' => $region->shipping_courier_id,
                    'shipping_region_id' => $region->id,
                    // Snapshots, alongside shipping_region_label: the order must
                    // still read correctly after any of these rows is renamed,
                    // retired or deleted.
                    'shipping_courier_name' => $region->courier?->name ?? '',
                    'shipping_region_label' => $region->snapshotLabel(),
                    'shipping_fee' => $shippingFee,
                    'subtotal' => $subtotal,
                    'total' => round($subtotal + $shippingFee, 2),
                    'payment_method_id' => $paymentMethod->id,
                    'payment_method_name' => $paymentMethod->name,
                    'payment_method_details' => $paymentMethod->details ?? [],
                    // Known before the insert now, so there is no follow-up
                    // update and no window with an empty path.
                    'payment_proof_path' => $proofPath,
                    'payment_status' => 'unverified',
                    'order_status' => 'pending',
                ]);

                foreach ($lines as $line) {
                    /** @var ProductVariant $variant */
                    $variant = $line['variant'];

                    OrderItem::create([
                        'order_id' => $order->id,
                        'product_variant_id' => $variant->id,
                        'product_name' => $variant->product->name,
                        'variant_label' => $variant->label,
                        'unit_price' => $line['unit_price'],
                        'quantity' => $line['quantity'],
                        'line_total' => $line['line_total'],
                    ]);

                    $variant->decrement('stock', $line['quantity']);
                }

                $order->forceFill(['order_number' => Order::referenceFor($order->id)])->save();

                return $order;
            });
        } catch (Throwable $exception) {
            // The file landed but the order did not - remove it rather than
            // leaving a receipt on disk that nothing points at.
            Storage::disk('local')->delete($proofPath);

            throw $exception;
        }

        if (! $order) {
            // Same reasoning as the catch above: no order, so no proof to keep.
            Storage::disk('local')->delete($proofPath);

            $this->toast(
                'Some items are no longer available: '.implode(' ', $shortfalls),
                'error',
            );

            // Back to the cart, not checkout: the quantities need fixing before
            // the form is worth filling in again.
            return to_route('storefront.cart');
        }

        SessionCart::forget();

        // A token in the URL rather than a session flash, so the page survives a
        // refresh or a later revisit. Random and unique, so one order's URL
        // reveals nothing about another's.
        return to_route('storefront.confirmation', ['token' => $order->confirmation_token]);
    }
}
