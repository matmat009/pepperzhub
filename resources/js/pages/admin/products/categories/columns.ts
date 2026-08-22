import type { ColumnDef } from '@tanstack/vue-table';
import { createColumnHelper } from '@tanstack/vue-table';
import { h } from 'vue';
import { Badge } from '@/components/ui/badge';
import type { Features } from '@/components/features';
import RowActions from './partials/RowActions.vue';
import { formatDate } from './types';
import type { Category } from './types';

const columnHelper = createColumnHelper<Features, Category>();

export type CategoryColumnActions = {
    onEdit: (category: Category) => void;
    onDelete: (category: Category) => void;
};

/**
 * Factory, matching the all-products pattern, so the row menu can call back
 * into the page to open the edit and delete dialogs.
 */
export const createCategoryColumns = (
    actions: CategoryColumnActions,
): ColumnDef<Features, Category, any>[] =>
    columnHelper.columns([
        columnHelper.accessor('name', {
            header: 'Name',
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
        columnHelper.accessor('description', {
            header: 'Description',
            cell: ({ row }) =>
                h(
                    'div',
                    {
                        class: 'max-w-md truncate text-sm text-muted-foreground',
                    },
                    row.original.description,
                ),
        }),
        columnHelper.accessor('product_count', {
            id: 'products',
            header: 'Products',
            meta: { headerClass: 'text-right' },
            cell: ({ row }) =>
                h(
                    'div',
                    { class: 'flex justify-end' },
                    h(
                        Badge,
                        {
                            variant: 'secondary',
                            class: [
                                'rounded-md font-normal tabular-nums',
                                row.original.product_count === 0
                                    ? 'text-muted-foreground'
                                    : '',
                            ],
                        },
                        () => String(row.original.product_count),
                    ),
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
            meta: { noRowClick: true, headerClass: 'w-10' },
            header: () => null,
            cell: ({ row }) =>
                h(RowActions, {
                    category: row.original,
                    onEdit: () => actions.onEdit(row.original),
                    onRemove: () => actions.onDelete(row.original),
                }),
            enableHiding: false,
        }),
    ]);
