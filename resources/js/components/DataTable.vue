<script setup lang="ts" generic="TData extends { id: number | string }">
import type {
    Cell,
    ColumnDef,
    ExpandedState,
    RowSelectionState,
} from '@tanstack/vue-table';
import { RestrictToVerticalAxis } from '@dnd-kit/abstract/modifiers';
import { ArrowDown, ArrowUp, ChevronsUpDown } from '@lucide/vue';
import {
    IconChevronLeft,
    IconChevronRight,
    IconChevronsLeft,
    IconChevronsRight,
} from '@tabler/icons-vue';
import { FlexRender, useTable } from '@tanstack/vue-table';
import { DragDropProvider } from 'dnd-kit-vue';
import { computed, ref } from 'vue';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import DraggableRow from './DraggableRow.vue';
import { features } from './features';
import type { Features } from './features';

/**
 * Data-agnostic table shell.
 *
 * Owns the TanStack table instance, the sorting affordance on every sortable
 * header, the row grid and the pagination footer. Everything data-specific —
 * columns, toolbar, bulk actions — comes from the caller via props and slots,
 * so one table implementation backs every table in the app.
 *
 * Column `meta` options:
 *  - `noRowClick` opts a cell out of the row-click target (checkbox / actions)
 *  - `headerClass` aligns or sizes a header cell
 *
 * `rowClickable` makes each row activate on click, Enter or Space, emitting
 * `rowClick`. It is off by default, so a table only becomes interactive by
 * opting in.
 */
type ColumnMeta = {
    noRowClick?: boolean;
    headerClass?: string;
};

const props = withDefaults(
    defineProps<{
        data: TData[];
        columns: ColumnDef<Features, TData, any>[];
        draggable?: boolean;
        rowClickable?: boolean;
        emptyMessage?: string;
        /**
         * Rows with detail worth revealing. Omit it and no row expands — these
         * rows have no sub-rows, so TanStack cannot infer expandability.
         */
        canExpandRow?: (row: TData) => boolean;
    }>(),
    {
        draggable: false,
        rowClickable: false,
        emptyMessage: 'No results.',
    },
);

const emit = defineEmits<{
    rowClick: [row: TData];
}>();

const rowSelection = defineModel<RowSelectionState>('rowSelection', {
    default: () => ({}),
});

/** Keyed by row id, so several rows stay open at once. */
const expanded = ref<ExpandedState>({});

const table = useTable({
    features,
    get data() {
        return props.data;
    },
    get columns() {
        return props.columns;
    },
    state: {
        get rowSelection() {
            return rowSelection.value;
        },
        get expanded() {
            return expanded.value;
        },
    },
    getRowCanExpand: (row) => props.canExpandRow?.(row.original) ?? false,
    onRowSelectionChange: (updater) => {
        rowSelection.value =
            typeof updater === 'function'
                ? updater(rowSelection.value)
                : updater;
    },
    onExpandedChange: (updater) => {
        expanded.value =
            typeof updater === 'function' ? updater(expanded.value) : updater;
    },
});

const metaOf = (columnDef: { meta?: unknown }): ColumnMeta =>
    (columnDef.meta as ColumnMeta | undefined) ?? {};

const onCellClick = (cell: Cell<Features, TData, unknown>, row: TData) => {
    if (!props.rowClickable || metaOf(cell.column.columnDef).noRowClick) {
        return;
    }

    emit('rowClick', row);
};

/**
 * Keyboard equivalent of the row click.
 *
 * The click handler is per-cell, because `noRowClick` opts individual cells
 * out. Focus cannot work that way — one tab stop per cell would make a
 * five-column table five identical stops per row — so the keyboard affordance
 * sits on the row instead, and the target check below does the job `noRowClick`
 * does for the mouse: when focus is on a control inside a cell (the Edit or
 * Delete button in the actions column), Enter belongs to that button and must
 * not also open the row.
 *
 * No-ops unless `rowClickable`, so tables that have not opted in are unchanged.
 */
const onRowKeydown = (event: KeyboardEvent, row: TData) => {
    if (!props.rowClickable || (event.key !== 'Enter' && event.key !== ' ')) {
        return;
    }

    if (event.target !== event.currentTarget) {
        return;
    }

    // Space would otherwise scroll the page.
    event.preventDefault();

    emit('rowClick', row);
};

const selectedRows = computed(() =>
    table.getFilteredSelectedRowModel().rows.map((row) => row.original),
);

const range = computed(() => {
    const total = table.getFilteredRowModel().rows.length;
    const { pageIndex, pageSize } = table.atoms.pagination.get();
    const from = total === 0 ? 0 : pageIndex * pageSize + 1;

    return { from, to: Math.min(from + pageSize - 1, total), total };
});

defineExpose({ table });
</script>

<template>
    <div class="flex w-full flex-col gap-4">
        <slot name="toolbar" :table="table" />
        <slot name="bulk" :table="table" :selected="selectedRows" />

        <div class="overflow-hidden rounded-xl border bg-card">
            <component
                :is="draggable ? DragDropProvider : 'div'"
                :modifiers="draggable ? [RestrictToVerticalAxis] : undefined"
            >
                <Table>
                    <TableHeader class="bg-muted/40">
                        <TableRow
                            v-for="headerGroup in table.getHeaderGroups()"
                            :key="headerGroup.id"
                            class="hover:bg-transparent"
                        >
                            <TableHead
                                v-for="header in headerGroup.headers"
                                :key="header.id"
                                :colspan="header.colSpan"
                                :class="[
                                    'h-11 text-xs font-medium tracking-wide text-muted-foreground',
                                    metaOf(header.column.columnDef).headerClass,
                                ]"
                            >
                                <button
                                    v-if="
                                        !header.isPlaceholder &&
                                        header.column.getCanSort()
                                    "
                                    type="button"
                                    class="group/sort -mx-2 inline-flex items-center gap-1.5 rounded-md px-2 py-1 transition-colors hover:text-foreground focus-visible:ring-[3px] focus-visible:ring-ring/50 focus-visible:outline-none"
                                    @click="header.column.toggleSorting()"
                                >
                                    <FlexRender :header="header" />
                                    <ArrowUp
                                        v-if="
                                            header.column.getIsSorted() ===
                                            'asc'
                                        "
                                        class="size-3.5"
                                    />
                                    <ArrowDown
                                        v-else-if="
                                            header.column.getIsSorted() ===
                                            'desc'
                                        "
                                        class="size-3.5"
                                    />
                                    <ChevronsUpDown
                                        v-else
                                        class="size-3.5 opacity-0 transition-opacity group-hover/sort:opacity-60"
                                    />
                                </button>
                                <FlexRender
                                    v-else-if="!header.isPlaceholder"
                                    :header="header"
                                />
                            </TableHead>
                        </TableRow>
                    </TableHeader>

                    <TableBody>
                        <template v-if="table.getRowModel().rows.length">
                            <template v-if="draggable">
                                <DraggableRow
                                    v-for="row in table.getRowModel().rows"
                                    :key="row.id"
                                    :row="row"
                                    :index="row.index"
                                />
                            </template>
                            <template
                                v-for="row in table.getRowModel().rows"
                                v-else
                                :key="row.id"
                            >
                                <TableRow
                                    :data-state="
                                        row.getIsSelected() && 'selected'
                                    "
                                    :role="rowClickable ? 'button' : undefined"
                                    :tabindex="rowClickable ? 0 : undefined"
                                    :class="[
                                        'transition-colors',
                                        rowClickable &&
                                            'cursor-pointer focus-visible:bg-muted/50 focus-visible:outline-2 focus-visible:-outline-offset-2 focus-visible:outline-ring',
                                    ]"
                                    @keydown="
                                        onRowKeydown($event, row.original)
                                    "
                                >
                                    <TableCell
                                        v-for="cell in row.getVisibleCells()"
                                        :key="cell.id"
                                        class="py-3"
                                        @click="onCellClick(cell, row.original)"
                                    >
                                        <FlexRender :cell="cell" />
                                    </TableCell>
                                </TableRow>

                                <!--
                                    Detail row. The row itself appears at once —
                                    a <tr> cannot animate its height reliably —
                                    while its content settles in on transform
                                    and opacity only.
                                -->
                                <TableRow
                                    v-if="row.getIsExpanded()"
                                    :key="`${row.id}-detail`"
                                    class="bg-muted/30 hover:bg-muted/30"
                                >
                                    <TableCell
                                        :colspan="row.getVisibleCells().length"
                                        class="p-0"
                                    >
                                        <div
                                            class="motion-safe:animate-in motion-safe:duration-200 motion-safe:ease-out motion-safe:fade-in-0 motion-safe:slide-in-from-top-1"
                                        >
                                            <slot
                                                name="expanded"
                                                :row="row.original"
                                            />
                                        </div>
                                    </TableCell>
                                </TableRow>
                            </template>
                        </template>
                        <TableRow v-else class="hover:bg-transparent">
                            <TableCell
                                :colspan="table.getVisibleLeafColumns().length"
                                class="h-32 text-center text-sm text-muted-foreground"
                            >
                                <slot name="empty">{{ emptyMessage }}</slot>
                            </TableCell>
                        </TableRow>
                    </TableBody>
                </Table>
            </component>
        </div>

        <div class="flex items-center justify-between gap-4">
            <p class="hidden flex-1 text-sm text-muted-foreground sm:block">
                <template v-if="selectedRows.length">
                    {{ selectedRows.length }} of
                    {{ table.getFilteredRowModel().rows.length }} row(s)
                    selected.
                </template>
                <template v-else>
                    Showing {{ range.from }}-{{ range.to }} of
                    {{ range.total }} results
                </template>
            </p>
            <div class="flex w-full items-center gap-6 sm:w-fit">
                <div class="hidden items-center gap-2 lg:flex">
                    <Label for="rows-per-page" class="text-sm font-medium">
                        Rows per page
                    </Label>
                    <Select
                        :model-value="`${table.atoms.pagination.get().pageSize}`"
                        @update:model-value="
                            (value) => table.setPageSize(Number(value))
                        "
                    >
                        <SelectTrigger
                            id="rows-per-page"
                            size="sm"
                            class="w-18"
                        >
                            <SelectValue />
                        </SelectTrigger>
                        <SelectContent side="top">
                            <SelectItem
                                v-for="size in [10, 20, 30, 40, 50]"
                                :key="size"
                                :value="`${size}`"
                            >
                                {{ size }}
                            </SelectItem>
                        </SelectContent>
                    </Select>
                </div>
                <div class="text-sm font-medium">
                    Page {{ table.atoms.pagination.get().pageIndex + 1 }} of
                    {{ Math.max(table.getPageCount(), 1) }}
                </div>
                <div class="ml-auto flex items-center gap-2 lg:ml-0">
                    <Button
                        variant="outline"
                        size="icon"
                        class="hidden size-8 lg:flex"
                        :disabled="!table.getCanPreviousPage()"
                        @click="table.setPageIndex(0)"
                    >
                        <span class="sr-only">Go to first page</span>
                        <IconChevronsLeft />
                    </Button>
                    <Button
                        variant="outline"
                        size="icon"
                        class="size-8"
                        :disabled="!table.getCanPreviousPage()"
                        @click="table.previousPage()"
                    >
                        <span class="sr-only">Go to previous page</span>
                        <IconChevronLeft />
                    </Button>
                    <Button
                        variant="outline"
                        size="icon"
                        class="size-8"
                        :disabled="!table.getCanNextPage()"
                        @click="table.nextPage()"
                    >
                        <span class="sr-only">Go to next page</span>
                        <IconChevronRight />
                    </Button>
                    <Button
                        variant="outline"
                        size="icon"
                        class="hidden size-8 lg:flex"
                        :disabled="!table.getCanNextPage()"
                        @click="table.setPageIndex(table.getPageCount() - 1)"
                    >
                        <span class="sr-only">Go to last page</span>
                        <IconChevronsRight />
                    </Button>
                </div>
            </div>
        </div>
    </div>
</template>
