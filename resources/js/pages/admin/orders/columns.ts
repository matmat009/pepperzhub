import type { ColumnDef } from '@tanstack/vue-table';
import { createColumnHelper } from '@tanstack/vue-table';
import { h } from 'vue';
import { Badge } from '@/components/ui/badge';
import type { Features } from '@/components/features';
import { formatPrice } from '@/pages/admin/products/all-products/types';
import { formatDateTime, orderTone, paymentTone } from './types';
import type { OrderRow } from './types';

const columnHelper = createColumnHelper<Features, OrderRow>();

/**
 * Same v9 conventions as the Products table: a column only participates in
 * filtering if it declares its own filterFn — there is no built-in fallback.
 */
export const createOrderColumns = (): ColumnDef<Features, OrderRow, any>[] =>
    columnHelper.columns([
        columnHelper.accessor('order_number', {
            header: 'Order',
            // Search spans reference, customer and phone, so one box covers the
            // three things an admin has to hand when a customer gets in touch.
            filterFn: (row, columnId, filterValue) => {
                const query = String(filterValue ?? '')
                    .trim()
                    .toLowerCase();

                if (!query) {
                    return true;
                }

                const order = row.original;

                return [order.order_number, order.name, order.phone]
                    .join(' ')
                    .toLowerCase()
                    .includes(query);
            },
            cell: ({ row }) =>
                h(
                    'span',
                    { class: 'font-medium tabular-nums' },
                    row.original.order_number,
                ),
            enableHiding: false,
        }),
        columnHelper.accessor('name', {
            header: 'Customer',
            cell: ({ row }) =>
                h('div', { class: 'min-w-0' }, [
                    h(
                        'div',
                        { class: 'truncate font-medium' },
                        row.original.name,
                    ),
                    h(
                        'div',
                        { class: 'truncate text-xs text-muted-foreground' },
                        row.original.phone,
                    ),
                ]),
        }),
        columnHelper.accessor('item_count', {
            header: 'Items',
            cell: ({ row }) => {
                const count = row.original.item_count;

                return h(
                    Badge,
                    {
                        variant: 'secondary',
                        class: 'rounded-md font-normal text-muted-foreground',
                    },
                    () => `${count} item${count === 1 ? '' : 's'}`,
                );
            },
        }),
        columnHelper.accessor('total', {
            header: 'Total',
            cell: ({ row }) =>
                h(
                    'span',
                    { class: 'font-medium tabular-nums' },
                    formatPrice(row.original.total),
                ),
        }),
        columnHelper.accessor('payment_status', {
            header: 'Payment',
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
                    {
                        variant: 'outline',
                        class: [
                            'rounded-md font-normal',
                            paymentTone[row.original.payment_status],
                        ],
                    },
                    () => row.original.payment_label,
                ),
        }),
        columnHelper.accessor('order_status', {
            header: 'Fulfillment',
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
                    {
                        variant: 'outline',
                        class: [
                            'rounded-md font-normal',
                            orderTone[row.original.order_status],
                        ],
                    },
                    () => row.original.order_label,
                ),
        }),
        columnHelper.accessor('created_at', {
            header: 'Placed',
            cell: ({ row }) =>
                h(
                    'span',
                    { class: 'text-muted-foreground' },
                    formatDateTime(row.original.created_at),
                ),
        }),
    ]);
