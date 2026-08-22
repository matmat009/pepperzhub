/**
 * Inventory shapes for the stock screen.
 *
 * Mirrors App\Http\Controllers\Admin\InventoryController. `history` becomes a
 * StockMovement relation once the models exist.
 */
export type StockReason =
    'Restock' | 'Damaged' | 'Correction' | 'Order Fulfilled';

export type StockMovement = {
    id: number;
    date: string;
    delta: number;
    reason: StockReason;
    resulting_stock: number;
    note: string | null;
};

export type InventoryItem = {
    id: number;
    name: string;
    type: 'Kit' | 'Vial';
    category: string;
    thumbnail: string | null;
    stock: number;
    updated_at: string;
    history: StockMovement[];
};

export type StockStatus = 'In Stock' | 'Low Stock' | 'Out of Stock';

export const STOCK_REASONS: StockReason[] = [
    'Restock',
    'Damaged',
    'Correction',
    'Order Fulfilled',
];

/** Anything at or below this (but above zero) reads as Low Stock. */
export const LOW_STOCK_THRESHOLD = 10;

export const stockStatus = (stock: number): StockStatus => {
    if (stock <= 0) {
        return 'Out of Stock';
    }

    return stock <= LOW_STOCK_THRESHOLD ? 'Low Stock' : 'In Stock';
};

export const isLowStock = (stock: number): boolean =>
    stockStatus(stock) !== 'In Stock';

export const formatDate = (value: string): string =>
    new Intl.DateTimeFormat('en-US', {
        month: 'short',
        day: 'numeric',
        year: 'numeric',
    }).format(new Date(value));

export const formatDelta = (delta: number): string =>
    `${delta > 0 ? '+' : ''}${delta}`;

/**
 * Deep copy of the server payload.
 *
 * `structuredClone` throws on Inertia's reactive proxies, and the payload is
 * plain JSON, so a JSON round-trip is both safe and sufficient.
 */
export const cloneItems = (items: InventoryItem[]): InventoryItem[] =>
    JSON.parse(JSON.stringify(items)) as InventoryItem[];

/** Today in the ISO form the dummy history entries use. */
export const today = (): string => new Date().toISOString().slice(0, 10);
