<?php

namespace App\Support;

use App\Models\Order;

/**
 * The five-stage customer-facing tracker.
 *
 * Deliberately derived from (payment_status, order_status) rather than stored:
 * a stored stage is a third piece of state to keep in sync with the two that
 * already decide it, and it drifts the first time an admin action forgets to
 * update it.
 *
 * Both the server-rendered pages and the TS mirror in
 * resources/js/pages/storefront/orderTracker.ts read from this shape, so the
 * labels live here once.
 */
class OrderTracker
{
    public const STAGES = [
        'Order Placed',
        'Payment Verified',
        'Preparing Order',
        'Shipped',
        'Delivered',
    ];

    /**
     * Returned instead of a stage index when an order is cancelled.
     *
     * Deliberately not a number: cancellation is a terminal state, not a point
     * on the happy path, and returning 0 for it would render a rejected order
     * identically to one that was just placed and is awaiting verification.
     */
    public const STAGE_CANCELLED = 'cancelled';

    /**
     * Index of the furthest stage reached, 0-based — or STAGE_CANCELLED.
     */
    public static function stage(Order $order): int|string
    {
        if ($order->order_status === 'cancelled') {
            return self::STAGE_CANCELLED;
        }

        $stage = 0;

        if ($order->payment_status === 'verified') {
            $stage = 1;
        }

        if (in_array($order->order_status, ['processing', 'shipped', 'completed'], true)) {
            $stage = 2;
        }

        if (in_array($order->order_status, ['shipped', 'completed'], true)) {
            $stage = 3;
        }

        if ($order->order_status === 'completed') {
            $stage = 4;
        }

        return $stage;
    }

    /**
     * Explicit map rather than ucfirst(): these labels are multi-word, so the
     * trick ProductController uses for "active"/"draft" would render
     * "Payment_verified".
     *
     * @return array<string, string>
     */
    public static function paymentLabels(): array
    {
        return [
            'unverified' => 'Awaiting Verification',
            'verified' => 'Payment Verified',
            'rejected' => 'Payment Rejected',
        ];
    }

    /** @return array<string, string> */
    public static function orderLabels(): array
    {
        return [
            'pending' => 'Pending',
            'processing' => 'Preparing Order',
            'shipped' => 'Shipped',
            'completed' => 'Completed',
            'cancelled' => 'Cancelled',
        ];
    }

    /**
     * Everything a storefront page needs to render the tracker.
     *
     * @return array<string, mixed>
     */
    public static function payload(Order $order): array
    {
        $cancelled = $order->order_status === 'cancelled';

        return [
            'stage' => static::stage($order),
            'stages' => static::STAGES,
            'cancelled' => $cancelled,
            // Written by Admin\OrderController's rejectPayment() and cancel(),
            // both of which take an optional reason. Null when the order is
            // live, or when it was cancelled without one being recorded.
            'cancellation_reason' => $cancelled ? $order->cancellation_reason : null,
            'payment_status' => $order->payment_status,
            'payment_label' => static::paymentLabels()[$order->payment_status] ?? $order->payment_status,
            'order_status' => $order->order_status,
            'order_label' => static::orderLabels()[$order->order_status] ?? $order->order_status,
        ];
    }
}
