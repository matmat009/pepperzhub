/**
 * Mirror of App\Support\OrderTracker.
 *
 * The stage is derived server-side and arrives as a prop — this file exists for
 * the types and for turning that payload into rows, not to recompute it. Two
 * implementations of the same rule would be two things to keep in step.
 */
export type OrderPaymentStatus = 'unverified' | 'verified' | 'rejected';

export type OrderStatus =
    'pending' | 'processing' | 'shipped' | 'completed' | 'cancelled';

/**
 * Cancellation is terminal, not a point on the happy path, so it is its own
 * value rather than a stage index. Rendering it as stage 0 would make a
 * rejected order look identical to one that was just placed.
 */
export const STAGE_CANCELLED = 'cancelled';

export type OrderStage = number | typeof STAGE_CANCELLED;

export type OrderTracker = {
    /** 0-based index of the furthest stage reached, or 'cancelled'. */
    stage: OrderStage;
    stages: string[];
    cancelled: boolean;
    /**
     * The reason recorded when the order was rejected or cancelled. Null while
     * the order is live, and also when it was cancelled without one given.
     */
    cancellation_reason: string | null;
    payment_status: OrderPaymentStatus;
    payment_label: string;
    order_status: OrderStatus;
    order_label: string;
};

export type OrderItemLine = {
    product_name: string;
    variant_label: string;
    unit_price: number;
    quantity: number;
    line_total: number;
};

export type TrackerStep = {
    label: string;
    done: boolean;
    current: boolean;
};

export const isCancelled = (tracker: OrderTracker): boolean =>
    tracker.stage === STAGE_CANCELLED || tracker.cancelled;

/** Shown when an order is cancelled without a recorded reason. */
export const DEFAULT_CANCELLATION_MESSAGE =
    'This order was cancelled. If you have already paid, contact us and we will sort out a refund.';

export const cancellationMessage = (tracker: OrderTracker): string =>
    tracker.cancellation_reason?.trim() || DEFAULT_CANCELLATION_MESSAGE;

/**
 * Rows for the timeline, from the server-derived stage. Empty for a cancelled
 * order — the caller renders the terminal state instead of a step list.
 */
export const trackerSteps = (tracker: OrderTracker): TrackerStep[] => {
    if (isCancelled(tracker)) {
        return [];
    }

    const stage = typeof tracker.stage === 'number' ? tracker.stage : 0;

    return tracker.stages.map((label, index) => ({
        label,
        done: index <= stage,
        current: index === stage,
    }));
};
