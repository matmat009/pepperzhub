<script setup lang="ts" generic="TData extends { id: number | string }">
import type { Row } from '@tanstack/vue-table';
import { FlexRender } from '@tanstack/vue-table';
import { useSortable } from 'dnd-kit-vue';
import { TableCell, TableRow } from '@/components/ui/table';
import type { Features } from './features';

const props = defineProps<{
    row: Row<Features, TData>;
    index: number;
}>();

const { elementRef, isDragging } = useSortable({
    id: props.row.original.id,
    index: props.index,
});
</script>

<template>
    <TableRow
        :ref="elementRef"
        :data-state="row.getIsSelected() && 'selected'"
        :data-dragging="isDragging"
        class="relative z-0 data-[dragging=true]:z-10 data-[dragging=true]:opacity-80"
    >
        <TableCell v-for="cell in row.getVisibleCells()" :key="cell.id">
            <FlexRender :cell="cell" />
        </TableCell>
    </TableRow>
</template>
