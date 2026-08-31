<?php

namespace App\Support;

/**
 * Admin-facing labels for the raw enum values.
 *
 * Deliberately not OrderTracker: that collapses both axes into one
 * customer-facing stage, which is right for Track Order and wrong here — the
 * admin needs payment and fulfillment visible and actionable as the two
 * independent things they are.
 *
 * Explicit maps rather than ucfirst(): several values are multi-word once
 * humanised, so the trick that works for "active"/"draft" would print
 * "Payment_verified".
 */
class OrderStatuses
{
    /** @return array<string, string> */
    public static function payment(): array
    {
        return [
            'unverified' => 'Awaiting Verification',
            'verified' => 'Payment Verified',
            'rejected' => 'Payment Rejected',
        ];
    }

    /** @return array<string, string> */
    public static function order(): array
    {
        return [
            'pending' => 'Pending',
            'processing' => 'Preparing Order',
            'shipped' => 'Shipped',
            'completed' => 'Completed',
            'cancelled' => 'Cancelled',
        ];
    }

    public static function paymentLabel(string $status): string
    {
        return static::payment()[$status] ?? $status;
    }

    public static function orderLabel(string $status): string
    {
        return static::order()[$status] ?? $status;
    }
}
