import type { ColumnDef } from '@tanstack/vue-table';
import { createColumnHelper } from '@tanstack/vue-table';
import { h } from 'vue';
import { Badge } from '@/components/ui/badge';
import type { Features } from '@/components/features';
import { formatPrice } from '@/pages/admin/products/all-products/types';
import RowActions from './partials/RowActions.vue';
import { availabilityKey, availabilityLabels, availabilityTone } from './types';
import type { ShippingCourier } from './types';

const columnHelper = createColumnHelper<Features, ShippingCourier>();

export type CourierColumnActions = {
    onEdit: (courier: ShippingCourier) => void;
    onDelete: (courier: ShippingCourier) => void;
};

export const createCourierColumns = (
    actions: CourierColumnActions,
): ColumnDef<Features, ShippingCourier, any>[] =>
    columnHelper.columns([
        columnHelper.accessor('name', {
            header: 'Courier',
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
        columnHelper.accessor('regions', {
            header: 'Delivery Options',
            cell: ({ row }) =>
                row.original.regions.length
                    ? h(
                          'div',
                          { class: 'grid gap-0.5 text-sm' },
                          row.original.regions.map((region) =>
                              h(
                                  'div',
                                  {
                                      class: [
                                          'flex flex-wrap items-baseline gap-x-2',
                                          region.is_active
                                              ? ''
                                              : 'text-muted-foreground line-through',
                                      ],
                                  },
                                  [
                                      h('span', region.name),
                                      region.note
                                          ? h(
                                                'span',
                                                {
                                                    class: 'text-xs text-muted-foreground',
                                                },
                                                region.note,
                                            )
                                          : null,
                                      h(
                                          'span',
                                          { class: 'tabular-nums' },
                                          formatPrice(region.rate),
                                      ),
                                  ],
                              ),
                          ),
                      )
                    : h(
                          'span',
                          { class: 'text-sm text-muted-foreground' },
                          'No regions yet',
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
            // Clicking Edit or Delete must not also open the row's view dialog.
            meta: { noRowClick: true, headerClass: 'text-right' },
            header: 'Action',
            cell: ({ row }) =>
                h(RowActions, {
                    courier: row.original,
                    onEdit: actions.onEdit,
                    onRemove: actions.onDelete,
                }),
            enableHiding: false,
        }),
    ]);
