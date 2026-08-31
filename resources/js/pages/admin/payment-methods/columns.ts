import type { ColumnDef } from '@tanstack/vue-table';
import { createColumnHelper } from '@tanstack/vue-table';
import { h } from 'vue';
import { Badge } from '@/components/ui/badge';
import type { Features } from '@/components/features';
import RowActions from './partials/RowActions.vue';
import { availabilityKey, availabilityLabels, availabilityTone } from './types';
import type { PaymentMethod } from './types';

const columnHelper = createColumnHelper<Features, PaymentMethod>();

export type PaymentMethodColumnActions = {
    onEdit: (method: PaymentMethod) => void;
    onDelete: (method: PaymentMethod) => void;
};

/**
 * Factory, matching the categories pattern, so the row menu can call back into
 * the page to open the edit and delete dialogs.
 */
export const createPaymentMethodColumns = (
    actions: PaymentMethodColumnActions,
): ColumnDef<Features, PaymentMethod, any>[] =>
    columnHelper.columns([
        columnHelper.accessor('name', {
            header: 'Method',
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
            cell: ({ row }) =>
                h('div', { class: 'text-sm font-medium' }, row.original.name),
            enableHiding: false,
        }),
        columnHelper.accessor('details', {
            header: 'Details',
            cell: ({ row }) =>
                h(
                    'div',
                    { class: 'grid gap-0.5 text-sm text-muted-foreground' },
                    row.original.details.map((detail) =>
                        h('div', { class: 'truncate' }, [
                            h(
                                'span',
                                { class: 'text-foreground' },
                                `${detail.label}: `,
                            ),
                            detail.value,
                        ]),
                    ),
                ),
        }),
        columnHelper.accessor('qr_code_url', {
            id: 'qr',
            header: 'QR',
            cell: ({ row }) =>
                row.original.qr_code_url
                    ? h('img', {
                          src: row.original.qr_code_url,
                          alt: `${row.original.name} QR code`,
                          class: 'size-10 rounded border object-contain',
                      })
                    : h(
                          'span',
                          { class: 'text-sm text-muted-foreground' },
                          '—',
                      ),
        }),
        columnHelper.accessor('sort_order', {
            header: 'Order',
            meta: { headerClass: 'text-right' },
            cell: ({ row }) =>
                h(
                    'div',
                    { class: 'text-right text-sm tabular-nums' },
                    String(row.original.sort_order),
                ),
        }),
        columnHelper.accessor('is_active', {
            id: 'status',
            header: 'Status',
            cell: ({ row }) => {
                const key = availabilityKey(row.original.is_active);

                return h(
                    Badge,
                    {
                        variant: 'outline',
                        class: [
                            'rounded-md font-normal',
                            availabilityTone[key],
                        ],
                    },
                    () => availabilityLabels[key],
                );
            },
        }),
        columnHelper.display({
            id: 'actions',
            cell: ({ row }) =>
                h(RowActions, {
                    method: row.original,
                    onEdit: actions.onEdit,
                    onRemove: actions.onDelete,
                }),
            enableHiding: false,
        }),
    ]);
