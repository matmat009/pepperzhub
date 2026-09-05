import type { ColumnDef } from '@tanstack/vue-table';
import { createColumnHelper } from '@tanstack/vue-table';
import { h } from 'vue';
import { ChevronRight } from '@lucide/vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import type { Features } from '@/components/features';
import ProductCell from './partials/ProductCell.vue';
import RowActions from './partials/RowActions.vue';
import StatusBadge from './partials/StatusBadge.vue';
import { formatDate, primaryPurity, priceRange, totalStock } from './types';
import type { Product } from './types';

const columnHelper = createColumnHelper<Features, Product>();

/** Cells the row-click handler must ignore, so they stay independently clickable. */
const NO_ROW_CLICK = { noRowClick: true };

/**
 * Lower-priority columns, dropped between `md` and `lg` so the tablet layout
 * keeps the seven that carry a decision: product, formats, status, stock,
 * price, plus the checkbox and row menu. Hidden in CSS rather than through
 * TanStack visibility, so the Columns toggle keeps owning that state and the
 * two mechanisms do not fight over the same column.
 */
const DESKTOP_ONLY = {
    headerClass: 'hidden lg:table-cell',
    cellClass: 'hidden lg:table-cell',
};

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
        // No sub-rows to infer from, so expandability comes from the page via
        // DataTable's `canExpandRow`. Rows with one format have nothing to show.
        columnHelper.display({
            id: 'expander',
            meta: { ...NO_ROW_CLICK, headerClass: 'w-8' },
            header: () => null,
            cell: ({ row }) =>
                row.getCanExpand()
                    ? h(
                          Button,
                          {
                              variant: 'ghost',
                              size: 'icon',
                              class: 'size-7 text-muted-foreground',
                              // Distinct from the row-actions trigger, which
                              // also carries aria-expanded.
                              'data-slot': 'row-expander',
                              'aria-expanded': row.getIsExpanded(),
                              onClick: (event: MouseEvent) => {
                                  event.stopPropagation();
                                  row.toggleExpanded();
                              },
                          },
                          () => [
                              h(ChevronRight, {
                                  class: [
                                      'size-4 transition-transform duration-200 ease-in-out motion-reduce:transition-none',
                                      row.getIsExpanded() ? 'rotate-90' : '',
                                  ],
                              }),
                              h(
                                  'span',
                                  { class: 'sr-only' },
                                  `${row.getIsExpanded() ? 'Hide' : 'Show'} formats for ${row.original.name}`,
                              ),
                          ],
                      )
                    : null,
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
        // Replaces the old flat Kit/Vial "Type" column: a product now carries a
        // list of purchasable formats rather than being one of two kinds.
        columnHelper.accessor((row) => row.variants.length, {
            id: 'formats',
            header: 'Formats',
            cell: ({ row }) => {
                const count = row.original.variants.length;

                return h(
                    Badge,
                    {
                        variant: 'secondary',
                        class: 'rounded-md font-normal text-muted-foreground',
                    },
                    () => `${count} format${count === 1 ? '' : 's'}`,
                );
            },
        }),
        columnHelper.accessor('category', {
            header: 'Category',
            meta: DESKTOP_ONLY,
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
        // One entry reads as the figure itself; several collapse to a count,
        // because no single value would represent the set honestly. The full
        // label/value breakdown lives on the product's Technical Details.
        columnHelper.accessor((row) => primaryPurity(row.purity_entries), {
            id: 'purity',
            header: 'Purity',
            meta: {
                ...DESKTOP_ONLY,
                headerClass: `text-right ${DESKTOP_ONLY.headerClass}`,
            },
            cell: ({ row }) => {
                const entries = row.original.purity_entries;

                if (entries.length > 1) {
                    return h(
                        'div',
                        { class: 'flex justify-end' },
                        h(
                            Badge,
                            {
                                variant: 'secondary',
                                class: 'rounded-md font-normal text-muted-foreground',
                            },
                            () => `${entries.length} entries`,
                        ),
                    );
                }

                return h(
                    'div',
                    { class: 'text-right whitespace-nowrap tabular-nums' },
                    primaryPurity(entries),
                );
            },
        }),
        columnHelper.accessor('status', {
            header: 'Status',
            filterFn: (row, columnId, filterValue) =>
                !filterValue || row.getValue(columnId) === filterValue,
            cell: ({ row }) => h(StatusBadge, { status: row.original.status }),
        }),
        // Stock and price are now aggregates across the product's formats.
        columnHelper.accessor((row) => totalStock(row.variants), {
            id: 'stock',
            header: 'Stock',
            meta: { headerClass: 'text-right' },
            cell: ({ row }) => {
                const stock = totalStock(row.original.variants);

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
        columnHelper.accessor(
            (row) => Math.min(...row.variants.map((v) => v.price)),
            {
                id: 'price',
                header: 'Price',
                meta: { headerClass: 'text-right' },
                cell: ({ row }) =>
                    h(
                        'div',
                        {
                            class: 'text-right font-medium whitespace-nowrap tabular-nums',
                        },
                        priceRange(row.original.variants),
                    ),
            },
        ),
        columnHelper.accessor('created_at', {
            id: 'created',
            header: 'Created',
            meta: DESKTOP_ONLY,
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
            meta: { ...NO_ROW_CLICK, headerClass: 'text-right' },
            header: 'Action',
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
