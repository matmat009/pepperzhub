<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\ProductVariant;
use App\Support\OrderStatuses;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Admin Orders.
 *
 * Every state-changing action follows the same shape: open a transaction, lock
 * the order row, re-check the precondition against the row as locked, then
 * write. Checking against whatever was loaded when the page rendered would let
 * a double-click fire the same transition twice — the discipline Phase 1.1
 * applied to checkout's stock, applied here to the status machine.
 */
class OrderController extends Controller
{
    /**
     * Row shape for the index table.
     *
     * @return array<string, mixed>
     */
    private function toRow(Order $order): array
    {
        return [
            'id' => $order->id,
            'order_number' => $order->order_number,
            'name' => $order->name,
            'phone' => $order->phone,
            'total' => (float) $order->total,
            'item_count' => (int) $order->items_count,
            'payment_status' => $order->payment_status,
            'payment_label' => OrderStatuses::paymentLabel($order->payment_status),
            'order_status' => $order->order_status,
            'order_label' => OrderStatuses::orderLabel($order->order_status),
            'created_at' => $order->created_at?->toDateTimeString(),
        ];
    }

    /**
     * Full detail payload.
     *
     * The snapshot fields are read straight off the order — that is the whole
     * point of capturing them at checkout, so this page needs no live join to
     * payment_methods or shipping_couriers, and still reads correctly after
     * either row is renamed or deleted.
     *
     * @return array<string, mixed>
     */
    private function toPayload(Order $order): array
    {
        return [
            'id' => $order->id,
            'order_number' => $order->order_number,

            'name' => $order->name,
            'social_handle' => $order->social_handle,
            'phone' => $order->phone,
            'street' => $order->street,
            'barangay' => $order->barangay,
            'city' => $order->city,
            'province' => $order->province,
            'zip' => $order->zip,
            'notes' => $order->notes,

            'shipping_courier_name' => $order->shipping_courier_name,
            'shipping_region_label' => $order->shipping_region_label,
            'payment_method_name' => $order->payment_method_name,
            'payment_method_details' => $order->payment_method_details ?? [],

            'subtotal' => (float) $order->subtotal,
            'shipping_fee' => (float) $order->shipping_fee,
            'total' => (float) $order->total,

            'payment_status' => $order->payment_status,
            'payment_label' => OrderStatuses::paymentLabel($order->payment_status),
            'order_status' => $order->order_status,
            'order_label' => OrderStatuses::orderLabel($order->order_status),
            'cancellation_reason' => $order->cancellation_reason,
            'tracking_number' => $order->tracking_number,
            'shipped_via' => $order->shipped_via,

            'payment_verified_at' => $order->payment_verified_at?->toDateTimeString(),
            'processing_at' => $order->processing_at?->toDateTimeString(),
            'shipped_at' => $order->shipped_at?->toDateTimeString(),
            'completed_at' => $order->completed_at?->toDateTimeString(),
            'cancelled_at' => $order->cancelled_at?->toDateTimeString(),
            'created_at' => $order->created_at?->toDateTimeString(),

            // A boolean and an extension, never the path — the frontend has no
            // business knowing where on disk a customer's bank receipt lives.
            'has_payment_proof' => filled($order->payment_proof_path)
                && Storage::disk('local')->exists($order->payment_proof_path),
            'payment_proof_extension' => filled($order->payment_proof_path)
                ? strtolower(pathinfo($order->payment_proof_path, PATHINFO_EXTENSION))
                : null,

            'items' => $order->items
                ->map(fn ($item) => [
                    'id' => $item->id,
                    'product_name' => $item->product_name,
                    'variant_label' => $item->variant_label,
                    'unit_price' => (float) $item->unit_price,
                    'quantity' => (int) $item->quantity,
                    'line_total' => (float) $item->line_total,
                ])
                ->all(),
        ];
    }

    public function index(): Response
    {
        // Loaded whole and filtered client-side, matching the Products table.
        // See the deviation note in the handover about when that stops being
        // the right call.
        $orders = Order::query()
            ->withCount('items')
            ->latest('id')
            ->get()
            ->map(fn (Order $order) => $this->toRow($order))
            ->all();

        return Inertia::render('admin/orders/Index', [
            'orders' => $orders,
            'paymentStatuses' => OrderStatuses::payment(),
            'orderStatuses' => OrderStatuses::order(),
        ]);
    }

    public function show(Order $order): Response
    {
        $order->load('items');

        return Inertia::render('admin/orders/Show', [
            'order' => $this->toPayload($order),
        ]);
    }

    /**
     * Stream the payment proof for in-browser preview.
     *
     * The path is resolved strictly from the order — nothing from the request
     * reaches the filesystem, so there is no traversal surface. Access control
     * is the admin group's auth/verified middleware; there is no public route
     * to this disk at all.
     */
    public function paymentProof(Order $order): StreamedResponse
    {
        abort_unless(filled($order->payment_proof_path), 404);

        $disk = Storage::disk('local');

        abort_unless($disk->exists($order->payment_proof_path), 404);

        return $disk->response(
            $order->payment_proof_path,
            // A stable, readable filename if the admin does save it.
            'payment-proof-'.$order->order_number.'.'.pathinfo($order->payment_proof_path, PATHINFO_EXTENSION),
            [
                // inline, so an image or PDF previews rather than downloading.
                'Content-Disposition' => 'inline',
                /*
                 * no-store, not the adapter's default no-cache: this is a
                 * customer's bank receipt, and no-cache still permits a shared
                 * or office machine to write it to disk — it only requires
                 * revalidation on reuse.
                 */
                'Cache-Control' => 'private, no-store',
                /*
                 * User-uploaded content served inline is the classic sniffing
                 * target. The upload's mimes rule already narrows this to
                 * jpg/png/pdf; this makes the browser honour the Content-Type
                 * rather than leaving that restriction to carry it alone.
                 */
                'X-Content-Type-Options' => 'nosniff',
            ],
        );
    }

    /**
     * Why a payment decision is refused, or null when it is allowed.
     *
     * Both axes are checked, and the message names whichever one actually
     * blocked: an admin asking "why won't this let me reject" needs to know
     * whether the payment is already resolved or the order has been cancelled
     * out from under it. Collapsing the two into one message hides that.
     *
     * The order_status conjunct is what stops a cancelled order's payment from
     * being rejected a second time — rejectPayment restores stock, and cancel
     * leaves payment_status untouched at 'unverified', so without it the two
     * actions would each hand the same units back.
     */
    private function paymentDecisionBlocker(Order $locked, string $verb): ?string
    {
        if ($locked->payment_status !== 'unverified') {
            return sprintf(
                'Payment is already %s — only an unverified payment can be %s.',
                OrderStatuses::paymentLabel($locked->payment_status),
                $verb,
            );
        }

        if ($locked->order_status !== 'pending') {
            return sprintf(
                'This order is already %s — its payment can no longer be %s.',
                OrderStatuses::orderLabel($locked->order_status),
                $verb,
            );
        }

        return null;
    }

    public function verifyPayment(Order $order): RedirectResponse
    {
        return $this->transition(
            $order,
            fn (Order $locked): ?string => $this->paymentDecisionBlocker($locked, 'verified'),
            fn (Order $locked) => $locked->forceFill([
                'payment_status' => 'verified',
                'payment_verified_at' => now(),
            ])->save(),
            'Payment verified.',
        );
    }

    public function rejectPayment(Request $request, Order $order): RedirectResponse
    {
        $reason = $this->reason($request);

        return $this->transition(
            $order,
            fn (Order $locked): ?string => $this->paymentDecisionBlocker($locked, 'rejected'),
            function (Order $locked) use ($reason) {
                $locked->forceFill([
                    'payment_status' => 'rejected',
                    'order_status' => 'cancelled',
                    'cancellation_reason' => $reason,
                    'cancelled_at' => now(),
                ])->save();

                $this->restoreStock($locked);
            },
            'Payment rejected, order cancelled and stock restored.',
        );
    }

    public function markProcessing(Order $order): RedirectResponse
    {
        return $this->transition(
            $order,
            function (Order $locked): ?string {
                // Payment is only checked here. Once an order is past pending,
                // reaching that point already required a verified payment, so
                // the later steps only have to follow the prior order_status.
                if ($locked->payment_status !== 'verified') {
                    return 'Verify the payment before preparing this order.';
                }

                return $locked->order_status === 'pending'
                    ? null
                    : sprintf('This order is already %s.', OrderStatuses::orderLabel($locked->order_status));
            },
            fn (Order $locked) => $locked->forceFill([
                'order_status' => 'processing',
                'processing_at' => now(),
            ])->save(),
            'Order moved to preparing.',
        );
    }

    public function markShipped(Request $request, Order $order): RedirectResponse
    {
        $data = $request->validate([
            'tracking_number' => ['required', 'string', 'max:255'],
            'shipped_via' => ['nullable', 'string', 'max:255'],
        ]);

        return $this->transition(
            $order,
            function (Order $locked): ?string {
                if ($locked->order_status !== 'processing') {
                    return sprintf(
                        'Only an order being prepared can be shipped — this one is %s.',
                        OrderStatuses::orderLabel($locked->order_status),
                    );
                }

                // Not reachable through the other guards — nothing can reach
                // 'processing' without a verified payment. Stated anyway so
                // this file enforces the invariant everywhere rather than
                // relying on the reader to trace why it holds.
                return $locked->payment_status === 'verified'
                    ? null
                    : 'Verify the payment before shipping this order.';
            },
            fn (Order $locked) => $locked->forceFill([
                'order_status' => 'shipped',
                'shipped_at' => now(),
                'tracking_number' => $data['tracking_number'],
                // Falls back to the checkout-time courier when left blank.
                'shipped_via' => filled($data['shipped_via'] ?? null)
                    ? $data['shipped_via']
                    : $locked->shipping_courier_name,
            ])->save(),
            'Order marked shipped.',
        );
    }

    public function markCompleted(Order $order): RedirectResponse
    {
        return $this->transition(
            $order,
            function (Order $locked): ?string {
                if ($locked->order_status !== 'shipped') {
                    return sprintf(
                        'Only a shipped order can be completed — this one is %s.',
                        OrderStatuses::orderLabel($locked->order_status),
                    );
                }

                // Defensive, same reasoning as markShipped.
                return $locked->payment_status === 'verified'
                    ? null
                    : 'Verify the payment before completing this order.';
            },
            fn (Order $locked) => $locked->forceFill([
                'order_status' => 'completed',
                'completed_at' => now(),
            ])->save(),
            'Order completed.',
        );
    }

    /**
     * Admin-initiated cancellation, independent of payment rejection.
     *
     * Reachable from pending and processing only. Both still hold the
     * checkout-time stock deduction with the goods on the shelf, so the restore
     * is honest. A shipped order is deliberately excluded: the parcel is with
     * the courier, and putting those units back as available would let them be
     * sold twice. Recording a physical return is a separate workflow.
     *
     * It can only ever run once per order: cancelled is terminal and is not in
     * the allowed set, so there is no second path back in.
     */
    public function cancel(Request $request, Order $order): RedirectResponse
    {
        $reason = $this->reason($request);

        return $this->transition(
            $order,
            fn (Order $locked): ?string => match (true) {
                in_array($locked->order_status, ['pending', 'processing'], true) => null,
                $locked->order_status === 'shipped' => 'A shipped order cannot be cancelled — record a return once the parcel is back.',
                default => sprintf('A %s order cannot be cancelled.', OrderStatuses::orderLabel($locked->order_status)),
            },
            function (Order $locked) use ($reason) {
                $locked->forceFill([
                    'order_status' => 'cancelled',
                    'cancellation_reason' => $reason,
                    'cancelled_at' => now(),
                ])->save();

                $this->restoreStock($locked);
            },
            'Order cancelled and stock restored.',
        );
    }

    private function reason(Request $request): ?string
    {
        $data = $request->validate([
            'reason' => ['nullable', 'string', 'max:500'],
        ]);

        return filled($data['reason'] ?? null) ? $data['reason'] : null;
    }

    /**
     * The shape every action shares.
     *
     * `$guard` re-reads the precondition from the locked row and returns a
     * specific message when the transition is not allowed — never a generic
     * failure, so the admin is told what actually blocked it.
     *
     * @param  callable(Order): ?string  $guard
     * @param  callable(Order): void  $apply
     */
    private function transition(Order $order, callable $guard, callable $apply, string $success): RedirectResponse
    {
        $blocked = DB::transaction(function () use ($order, $guard, $apply): ?string {
            /** @var Order $locked */
            $locked = Order::query()->whereKey($order->id)->lockForUpdate()->firstOrFail();

            if ($message = $guard($locked)) {
                return $message;
            }

            $apply($locked);

            return null;
        });

        if ($blocked !== null) {
            $this->toast($blocked, 'error');

            return back();
        }

        $this->toast($success);

        return back();
    }

    /**
     * Put an order's stock back.
     *
     * Shared by reject-payment and cancel so the two cannot drift. Locks each
     * variant for the same reason checkout does: two admins, or one impatient
     * one, must not double-increment. Items whose variant has since been
     * deleted are skipped — product_variant_id is nullable by design.
     */
    private function restoreStock(Order $order): void
    {
        $order->loadMissing('items');

        $variantIds = $order->items
            ->pluck('product_variant_id')
            ->filter()
            ->all();

        if ($variantIds === []) {
            return;
        }

        $variants = ProductVariant::query()
            ->whereIn('id', $variantIds)
            ->lockForUpdate()
            ->get()
            ->keyBy('id');

        foreach ($order->items as $item) {
            $variant = $item->product_variant_id
                ? $variants->get($item->product_variant_id)
                : null;

            $variant?->increment('stock', (int) $item->quantity);
        }
    }
}
