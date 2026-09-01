/**
 * Dashboard shapes.
 *
 * Mirrors App\Http\Controllers\Admin\DashboardController::index(). Every figure
 * is computed server-side — this page does no arithmetic of its own, so the
 * definition of "revenue" lives in exactly one place.
 */
export type DashboardStats = {
    pending_verification: number;
    orders_today: number;
    revenue_this_month: number;
    low_stock: number;
};

export type PendingPayment = {
    id: number;
    order_number: string;
    name: string;
    total: number;
    /** Already rendered as a duration ("2 days"), not a timestamp. */
    waiting_for: string | null;
};
