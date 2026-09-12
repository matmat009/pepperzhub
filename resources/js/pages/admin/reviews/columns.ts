import type { ColumnDef } from '@tanstack/vue-table';
import { createColumnHelper } from '@tanstack/vue-table';
import { ImageIcon } from '@lucide/vue';
import { h } from 'vue';
import { Badge } from '@/components/ui/badge';
import type { Features } from '@/components/features';
import RowActions from './partials/RowActions.vue';
import { availabilityKey, availabilityLabels, availabilityTone } from './types';
import type { Review } from './types';

const columnHelper = createColumnHelper<Features, Review>();

export type ReviewColumnActions = {
    onEdit: (review: Review) => void;
    onDelete: (review: Review) => void;
};

/**
 * Factory, matching the payment-methods pattern, so the row actions can call
 * back into the page to open the edit and delete dialogs.
 */
export const createReviewColumns = (
    actions: ReviewColumnActions,
): ColumnDef<Features, Review, any>[] =>
    columnHelper.columns([
        columnHelper.accessor('image_url', {
            id: 'photo',
            header: 'Photo',
            cell: ({ row }) =>
                h(
                    'div',
                    {
                        class: 'flex size-12 shrink-0 items-center justify-center overflow-hidden rounded-lg border bg-muted/60',
                    },
                    row.original.image_url
                        ? h('img', {
                              src: row.original.image_url,
                              alt: row.original.title,
                              class: 'size-full object-cover',
                          })
                        : // Same fallback the products list uses, so a missing
                          // image looks the same everywhere in the admin.
                          h(ImageIcon, {
                              class: 'size-4 text-muted-foreground',
                          }),
                ),
        }),
        columnHelper.accessor('title', {
            header: 'Review',
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
                h('div', { class: 'min-w-0' }, [
                    h(
                        'div',
                        { class: 'truncate text-sm font-medium' },
                        row.original.title,
                    ),
                    h(
                        'div',
                        { class: 'truncate text-sm text-muted-foreground' },
                        row.original.customer_name ?? 'Anonymous',
                    ),
                ]),
            enableHiding: false,
        }),
        columnHelper.accessor('product_name', {
            id: 'product',
            header: 'Product',
            cell: ({ row }) =>
                h(
                    'div',
                    {
                        class: row.original.product_name
                            ? 'text-sm'
                            : 'text-sm text-muted-foreground',
                    },
                    // An untagged review, and one whose product was deleted,
                    // both read the same way here.
                    row.original.product_name ?? '—',
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
                    review: row.original,
                    onEdit: actions.onEdit,
                    onRemove: actions.onDelete,
                }),
            enableHiding: false,
        }),
    ]);
