import type { ColumnDef } from '@tanstack/vue-table';
import { createColumnHelper } from '@tanstack/vue-table';
import { h } from 'vue';
import { Badge } from '@/components/ui/badge';
import { Checkbox } from '@/components/ui/checkbox';
import type { Features } from '@/components/features';
import ProductCell from './partials/ProductCell.vue';
import RowActions from './partials/RowActions.vue';
import StatusBadge from './partials/StatusBadge.vue';
import { formatDate, formatPrice, formatPurity } from './types';
import type { Product } from './types';

const columnHelper = createColumnHelper<Features, Product>();

/** Cells the row-click handler must ignore, so they stay independently clickable. */
const NO_ROW_CLICK = { noRowClick: true };

export type ProductColumnActions = {
    onView: (product: Product) => void;
    onEdit: (product: Product) => void;
    onDuplicate: (product: Product) => void;
    onDelete: (product: Product) => void;
};

/**
 * Built as a factory rather than a bare constant so the row menu can call back
 * into the page (open the delete dialog, navigate) without reaching for a store.
 */
export const createProductColumns = (
    actions: ProductColumnActions,
): ColumnDef<Features, Product, any>[] =>
    columnHelper.columns([
        columnHelper.display({
            id: 'select',
            meta: { ...NO_ROW_CLICK, headerClass: 'w-10' },
            header: ({ table }) =>
                h(Checkbox, {
                    modelValue:
                        table.getIsAllPageRowsSelected() ||
                        (table.getIsSomePageRowsSelected() && 'indeterminate'),
                    'onUpdate:modelValue': (value: unknown) =>
                        table.toggleAllPageRowsSelected(!!value),
                    'aria-label': 'Select all',
                }),
            cell: ({ row }) =>
                h(Checkbox, {
                    modelValue: row.getIsSelected(),
                    'onUpdate:modelValue': (value: unknown) =>
                        row.toggleSelected(!!value),
                    'aria-label': `Select ${row.original.name}`,
                }),
            enableSorting: false,
            enableHiding: false,
        }),
        columnHelper.accessor('name', {
            id: 'product',
            header: 'Product',
            // v9 does not fall back to a built-in filter: a column only
            // participates in filtering if it declares its own filterFn.
            filterFn: (row, columnId, filterValue) => {
                const query = String(filterValue ?? '')
                    .trim()
                    .toLowerCase();

                return (
                    !query ||
                    String(row.getValue(columnId)).toLowerCase().includes(query)
                );
            },
            cell: ({ row }) => h(ProductCell, { product: row.original }),
            enableHiding: false,
        }),
        columnHelper.accessor('type', {
            header: 'Type',
            filterFn: (row, columnId, filterValue) =>
                !filterValue || row.getValue(columnId) === filterValue,
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
        columnHelper.accessor('purity', {
            header: 'Purity',
            meta: { headerClass: 'text-right' },
            cell: ({ row }) =>
                h(
                    'div',
                    { class: 'text-right tabular-nums' },
                    formatPurity(row.original.purity),
                ),
        }),
        columnHelper.accessor('status', {
            header: 'Status',
            filterFn: (row, columnId, filterValue) =>
                !filterValue || row.getValue(columnId) === filterValue,
            cell: ({ row }) => h(StatusBadge, { status: row.original.status }),
        }),
        columnHelper.accessor('stock', {
            header: 'Stock',
            meta: { headerClass: 'text-right' },
            cell: ({ row }) => {
                const stock = row.original.stock;

                return h(
                    'div',
                    {
                        class: [
                            'text-right tabular-nums',
                            stock === 0
                                ? 'font-medium text-red-600 dark:text-red-400'
                                : stock < 10
                                  ? 'font-medium text-amber-600 dark:text-amber-400'
                                  : '',
                        ],
                    },
                    stock === 0 ? 'Out of stock' : String(stock),
                );
            },
        }),
        columnHelper.accessor('price', {
            header: 'Price',
            meta: { headerClass: 'text-right' },
            cell: ({ row }) =>
                h(
                    'div',
                    { class: 'text-right font-medium tabular-nums' },
                    formatPrice(row.original.price),
                ),
        }),
        columnHelper.accessor('created_at', {
            id: 'created',
            header: 'Created',
            cell: ({ row }) =>
                h(
                    'div',
                    {
                        class: 'text-sm whitespace-nowrap text-muted-foreground',
                    },
                    formatDate(row.original.created_at),
                ),
        }),
        columnHelper.display({
            id: 'actions',
            meta: { ...NO_ROW_CLICK, headerClass: 'w-10' },
            header: () => null,
            cell: ({ row }) =>
                h(RowActions, {
                    product: row.original,
                    onView: () => actions.onView(row.original),
                    onEdit: () => actions.onEdit(row.original),
                    onDuplicate: () => actions.onDuplicate(row.original),
                    onRemove: () => actions.onDelete(row.original),
                }),
            enableHiding: false,
        }),
    ]);
