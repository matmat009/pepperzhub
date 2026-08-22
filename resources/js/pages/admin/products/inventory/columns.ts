import type { ColumnDef } from '@tanstack/vue-table';
import { createColumnHelper } from '@tanstack/vue-table';
import { h } from 'vue';
import { Badge } from '@/components/ui/badge';
import type { Features } from '@/components/features';
import ProductCell from './partials/ProductCell.vue';
import RowActions from './partials/RowActions.vue';
import StockStatusBadge from './partials/StockStatusBadge.vue';
import { formatDate, isLowStock, stockStatus } from './types';
import type { InventoryItem } from './types';

const columnHelper = createColumnHelper<Features, InventoryItem>();

export type InventoryColumnActions = {
    onAdjust: (item: InventoryItem) => void;
    onHistory: (item: InventoryItem) => void;
};

/**
 * Factory, matching the all-products pattern, so the row menu can open the
 * adjust and history dialogs owned by the page.
 */
export const createInventoryColumns = (
    actions: InventoryColumnActions,
): ColumnDef<Features, InventoryItem, any>[] =>
    columnHelper.columns([
        columnHelper.accessor('name', {
            id: 'product',
            header: 'Product',
            // v9 has no built-in filter fallback: a column only participates in
            // filtering if it declares its own filterFn.
            filterFn: (row, columnId, filterValue) => {
                const query = String(filterValue ?? '')
                    .trim()
                    .toLowerCase();

                return (
                    !query ||
                    String(row.getValue(columnId)).toLowerCase().includes(query)
                );
            },
            cell: ({ row }) => h(ProductCell, { item: row.original }),
            enableHiding: false,
        }),
        columnHelper.accessor('type', {
            header: 'Type',
            cell: ({ row }) =>
                h(
                    Badge,
                    {
                        variant: 'secondary',
                        class: 'rounded-md font-normal text-muted-foreground',
                    },
                    () => row.original.type,
                ),
        }),
        columnHelper.accessor('category', {
            header: 'Category',
            filterFn: (row, columnId, filterValue) => {
                const selected = filterValue as string[] | undefined;

                return (
                    !selected?.length ||
                    selected.includes(row.getValue(columnId) as string)
                );
            },
            cell: ({ row }) =>
                h(
                    Badge,
                    { variant: 'outline', class: 'rounded-md font-normal' },
                    () => row.original.category,
                ),
        }),
        columnHelper.accessor('stock', {
            header: 'Current Stock',
            meta: { headerClass: 'text-right' },
            // Drives the "Low stock only" switch: the filter value is a boolean.
            filterFn: (row, columnId, filterValue) =>
                !filterValue || isLowStock(row.getValue(columnId) as number),
            cell: ({ row }) => {
                const stock = row.original.stock;

                return h(
                    'div',
                    {
                        class: [
                            'text-right font-medium tabular-nums',
                            stockStatus(stock) === 'Out of Stock'
                                ? 'text-red-600 dark:text-red-400'
                                : stockStatus(stock) === 'Low Stock'
                                  ? 'text-amber-600 dark:text-amber-400'
                                  : '',
                        ],
                    },
                    String(stock),
                );
            },
        }),
        columnHelper.accessor('stock', {
            id: 'status',
            header: 'Status',
            enableColumnFilter: false,
            cell: ({ row }) =>
                h(StockStatusBadge, { stock: row.original.stock }),
        }),
        columnHelper.accessor('updated_at', {
            id: 'updated',
            header: 'Last Updated',
            cell: ({ row }) =>
                h(
                    'div',
                    {
                        class: 'text-sm whitespace-nowrap text-muted-foreground',
                    },
                    formatDate(row.original.updated_at),
                ),
        }),
        columnHelper.display({
            id: 'actions',
            meta: { noRowClick: true, headerClass: 'w-10' },
            header: () => null,
            cell: ({ row }) =>
                h(RowActions, {
                    item: row.original,
                    onAdjust: () => actions.onAdjust(row.original),
                    onHistory: () => actions.onHistory(row.original),
                }),
            enableHiding: false,
        }),
    ]);
