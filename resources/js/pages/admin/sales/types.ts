/**
 * Sales shapes.
 *
 * Mirrors App\Http\Controllers\Admin\SalesController. Every figure here is the
 * server's — nothing on this page recomputes revenue, because what counts as
 * revenue is decided once, in Order::revenueQuery(), and a second opinion in
 * TypeScript is exactly the drift that rule exists to prevent.
 */
export type RangeKey = 'this_month' | 'last_month' | 'custom';

export type SalesRange = {
    key: RangeKey;
    /** Y-m-d, inclusive. */
    start: string;
    end: string;
    /** Rendered server-side — "September 2026", or "3 – 17 Sep 2026". */
    label: string;
    days: number;
};

export type SalesComparison = SalesRange & {
    revenue: number;
};

export type SalesPoint = {
    /** Y-m-d. One per day in the range, zeros included. */
    date: string;
    revenue: number;
};

export type TopProduct = {
    /** order_items' own snapshot, not a live product name. */
    product_name: string;
    revenue: number;
    units: number;
};

export type TopProducts = {
    by_revenue: TopProduct[];
    by_units: TopProduct[];
};

/**
 * Percentage change against the comparison period.
 *
 * Null when the comparison took nothing: dividing by zero gives Infinity, and
 * "+∞%" against a month with no sales is noise where "no prior sales" is the
 * honest answer.
 */
export const percentChange = (
    current: number,
    previous: number,
): number | null =>
    previous === 0 ? null : ((current - previous) / previous) * 100;

export const formatPercent = (value: number): string =>
    `${value > 0 ? '+' : ''}${value.toFixed(1)}%`;

/** Axis and tooltip labels: "3 Sep", short enough to sit under a tick. */
export const formatDay = (date: string): string =>
    new Intl.DateTimeFormat('en-PH', {
        day: 'numeric',
        month: 'short',
    }).format(new Date(`${date}T00:00:00`));

export const formatFullDay = (date: string): string =>
    new Intl.DateTimeFormat('en-PH', {
        day: 'numeric',
        month: 'short',
        year: 'numeric',
    }).format(new Date(`${date}T00:00:00`));
