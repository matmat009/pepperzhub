<script setup lang="ts">
import { ref, watch } from 'vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import type { RangeKey, SalesRange } from '../types';

/**
 * The range the whole page is reporting on.
 *
 * Emits rather than navigating itself: the page owns the visit, so the export
 * link and the Orders link are built from the same one set of parameters that
 * were requested — three components each assembling their own query string is
 * how an export ends up covering a different window than the chart above it.
 */
const props = defineProps<{
    range: SalesRange;
    maxRangeDays: number;
}>();

const emit = defineEmits<{
    select: [payload: { key: RangeKey; start?: string; end?: string }];
}>();

const start = ref(props.range.start);
const end = ref(props.range.end);

// Reseeded from the server's answer, which may have clamped an over-long
// range — the boxes have to show the window actually reported on.
watch(
    () => props.range,
    (next) => {
        start.value = next.start;
        end.value = next.end;
    },
);

const shortcuts: { key: RangeKey; label: string }[] = [
    { key: 'this_month', label: 'This month' },
    { key: 'last_month', label: 'Last month' },
];

const applyCustom = () => {
    if (!start.value || !end.value) {
        return;
    }

    emit('select', { key: 'custom', start: start.value, end: end.value });
};
</script>

<template>
    <div class="flex flex-wrap items-end gap-x-3 gap-y-4">
        <div class="flex gap-2">
            <Button
                v-for="shortcut in shortcuts"
                :key="shortcut.key"
                :variant="range.key === shortcut.key ? 'default' : 'outline'"
                size="sm"
                @click="emit('select', { key: shortcut.key })"
            >
                {{ shortcut.label }}
            </Button>
        </div>

        <div class="flex flex-wrap items-end gap-2">
            <div class="grid gap-1.5">
                <Label for="sales-start" class="text-xs">From</Label>
                <Input
                    id="sales-start"
                    v-model="start"
                    type="date"
                    class="h-8 w-40"
                />
            </div>
            <div class="grid gap-1.5">
                <Label for="sales-end" class="text-xs">To</Label>
                <Input
                    id="sales-end"
                    v-model="end"
                    type="date"
                    class="h-8 w-40"
                />
            </div>
            <Button
                :variant="range.key === 'custom' ? 'default' : 'outline'"
                size="sm"
                :disabled="!start || !end"
                @click="applyCustom"
            >
                Apply
            </Button>
        </div>

        <p class="w-full text-xs text-muted-foreground">
            Showing {{ range.label }} · {{ range.days }}
            {{ range.days === 1 ? 'day' : 'days' }}. Custom ranges cover up to
            {{ maxRangeDays }} days.
        </p>
    </div>
</template>
