/**
 * Inventory shapes for the stock screen.
 *
 * Mirrors App\Http\Controllers\Admin\InventoryController, which serialises one
 * row per product_variant — never per product. A product with two formats is
 * two rows here, because stock is a property of the format and a summed total
 * is exactly what hides a format about to run out.
 */

/** Chosen by the admin in the Adjust dialog. */
export type ManualStockReason = 'Restock' | 'Damaged' | 'Correction';

/**
 * Written by the system, never offered in the dialog.
 *
 * Checkout writes Order Fulfilled when it takes stock; a cancellation or a
 * rejected payment writes Order Cancelled when it hands it back. Picking one
 * of these by hand would assert an order event that never happened.
 */
export type AutomaticStockReason = 'Order Fulfilled' | 'Order Cancelled';

export type StockReason = ManualStockReason | AutomaticStockReason;

export type StockMovement = {
    id: number;
    date: string;
    /** Signed: negative took stock out, positive put it back. */
    delta: number;
    reason: StockReason;
    /** What the variant held immediately after this movement. */
    resulting_stock: number;
    note: string | null;
};

export type StockStatus = 'In Stock' | 'Low Stock' | 'Out of Stock';

export type InventoryItem = {
    /** The variant's id — what the adjust endpoint is keyed by. */
    id: number;
    product_name: string;
    variant_label: string;
    type: 'Kit' | 'Vial';
    category: string;
    thumbnail: string | null;
    stock: number;
    /**
     * Classified server-side from ProductVariant::LOW_STOCK_THRESHOLD.
     *
     * Deliberately not recomputed here. This file used to declare a threshold
     * of its own, which drifted from the real one the moment that changed —
     * the screen showed placeholder data, so nobody noticed. There is now one
     * number, and it never leaves the server.
     */
    status: StockStatus;
    /** When the stock last moved, not when the row was last written. */
    updated_at: string;
    /** Oldest first; the history dialog reverses it for display. */
    history: StockMovement[];
};

export const MANUAL_STOCK_REASONS: ManualStockReason[] = [
    'Restock',
    'Damaged',
    'Correction',
];

/** Low Stock and Out of Stock together are what the dashboard tile counts. */
export const isLowStock = (status: StockStatus): boolean =>
    status !== 'In Stock';

export const formatDate = (value: string): string =>
    new Intl.DateTimeFormat('en-US', {
        month: 'short',
        day: 'numeric',
        year: 'numeric',
    }).format(new Date(value));

export const formatDelta = (delta: number): string =>
    `${delta > 0 ? '+' : ''}${delta}`;
