/**
 * Order shapes for the admin screens.
 *
 * Mirrors OrderController's toRow()/toPayload(). Labels arrive from the server
 * (App\Support\OrderStatuses) rather than being mapped again here — several are
 * multi-word, so a bare ucfirst() on the client would print "Payment_verified".
 */
export type OrderPaymentStatus = 'unverified' | 'verified' | 'rejected';

export type AdminOrderStatus =
    'pending' | 'processing' | 'shipped' | 'completed' | 'cancelled';

export type OrderRow = {
    id: number;
    order_number: string;
    name: string;
    phone: string;
    total: number;
    item_count: number;
    payment_status: OrderPaymentStatus;
    payment_label: string;
    order_status: AdminOrderStatus;
    order_label: string;
    created_at: string | null;
};

export type OrderItem = {
    id: number;
    product_name: string;
    variant_label: string;
    unit_price: number;
    quantity: number;
    line_total: number;
};

export type OrderDetail = {
    id: number;
    order_number: string;

    name: string;
    social_handle: string;
    phone: string;
    street: string;
    barangay: string;
    city: string;
    province: string;
    zip: string;
    notes: string | null;

    /** Frozen at order time — read directly, never joined live. */
    shipping_courier_name: string;
    shipping_region_label: string;
    payment_method_name: string;
    payment_method_details: { label: string; value: string }[];

    subtotal: number;
    shipping_fee: number;
    total: number;

    payment_status: OrderPaymentStatus;
    payment_label: string;
    order_status: AdminOrderStatus;
    order_label: string;
    cancellation_reason: string | null;
    tracking_number: string | null;
    shipped_via: string | null;

    payment_verified_at: string | null;
    processing_at: string | null;
    shipped_at: string | null;
    completed_at: string | null;
    cancelled_at: string | null;
    created_at: string | null;

    /** A flag and an extension — the path itself never reaches the client. */
    has_payment_proof: boolean;
    payment_proof_extension: string | null;

    items: OrderItem[];
};

/** Tone for the payment badge. Kept beside the order tone map for symmetry. */
export const paymentTone: Record<OrderPaymentStatus, string> = {
    unverified: 'border-amber-200 bg-amber-50 text-amber-700',
    verified: 'border-emerald-200 bg-emerald-50 text-emerald-700',
    rejected: 'border-red-200 bg-red-50 text-red-700',
};

export const orderTone: Record<AdminOrderStatus, string> = {
    pending: 'border-neutral-200 bg-neutral-100 text-neutral-700',
    processing: 'border-blue-200 bg-blue-50 text-blue-700',
    shipped: 'border-indigo-200 bg-indigo-50 text-indigo-700',
    completed: 'border-emerald-200 bg-emerald-50 text-emerald-700',
    cancelled: 'border-red-200 bg-red-50 text-red-700',
};

/**
 * The state machine, client-side.
 *
 * One statement of the rules rather than a computed per button on Show.vue —
 * five independent computeds drift, and a button the server would refuse is
 * worse than no button at all. These mirror the guards in
 * App\Http\Controllers\Admin\OrderController exactly; change them together.
 *
 * Narrow inputs (not the whole OrderDetail) so the row shape can use them too.
 */
type OrderState = {
    payment_status: OrderPaymentStatus;
    order_status: AdminOrderStatus;
};

/** Both payment decisions need a still-pending order, not just an unresolved payment. */
export const canVerifyPayment = (order: OrderState): boolean =>
    order.payment_status === 'unverified' && order.order_status === 'pending';

export const canRejectPayment = (order: OrderState): boolean =>
    order.payment_status === 'unverified' && order.order_status === 'pending';

export const canMarkProcessing = (order: OrderState): boolean =>
    order.payment_status === 'verified' && order.order_status === 'pending';

export const canMarkShipped = (order: OrderState): boolean =>
    order.order_status === 'processing' && order.payment_status === 'verified';

export const canMarkCompleted = (order: OrderState): boolean =>
    order.order_status === 'shipped' && order.payment_status === 'verified';

/**
 * Shipped is deliberately absent: the parcel is already with the courier, so
 * returning its units to available stock would let them be sold twice.
 */
export const canCancel = (order: OrderState): boolean =>
    order.order_status === 'pending' || order.order_status === 'processing';

export const formatDateTime = (value: string | null): string =>
    value
        ? new Intl.DateTimeFormat('en-PH', {
              month: 'short',
              day: 'numeric',
              year: 'numeric',
              hour: 'numeric',
              minute: '2-digit',
          }).format(new Date(value.replace(' ', 'T')))
        : '—';
