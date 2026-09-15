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
    <div class="flex flex-wrap items-end gap-x-4 gap-y-5">
        <div class="flex w-full flex-wrap gap-2 sm:w-auto">
            <Button
                v-for="shortcut in shortcuts"
                :key="shortcut.key"
                variant="outline"
                class="h-12 flex-1 rounded-xl px-5 text-sm shadow-none sm:flex-none"
                :class="
                    range.key === shortcut.key
                        ? 'border-sf-primary bg-sf-primary text-white hover:border-sf-primary-hover hover:bg-sf-primary-hover'
                        : 'border-transparent bg-muted/55 text-foreground hover:border-sf-serenity-blue/30 hover:bg-sf-serenity-blue/10'
                "
                @click="emit('select', { key: shortcut.key })"
            >
                {{ shortcut.label }}
            </Button>
        </div>

        <div class="flex w-full flex-wrap items-end gap-3 lg:w-auto">
            <div class="grid min-w-0 flex-1 gap-1.5 sm:flex-none">
                <Label for="sales-start" class="text-sm">From</Label>
                <Input
                    id="sales-start"
                    v-model="start"
                    type="date"
                    class="h-12 w-full rounded-xl border-sf-serenity-blue/30 bg-background px-4 shadow-none sm:w-56"
                />
            </div>
            <div class="grid min-w-0 flex-1 gap-1.5 sm:flex-none">
                <Label for="sales-end" class="text-sm">To</Label>
                <Input
                    id="sales-end"
                    v-model="end"
                    type="date"
                    class="h-12 w-full rounded-xl border-sf-serenity-blue/30 bg-background px-4 shadow-none sm:w-56"
                />
            </div>
            <Button
                class="h-12 w-full rounded-xl border-sf-primary bg-sf-primary px-6 text-white shadow-sm hover:border-sf-primary-hover hover:bg-sf-primary-hover sm:w-auto"
                :disabled="!start || !end"
                @click="applyCustom"
            >
                Apply
            </Button>
        </div>

        <p
            class="w-full text-sm text-sf-primary-soft dark:text-muted-foreground"
        >
            Showing {{ range.label }} · {{ range.days }}
            {{ range.days === 1 ? 'day' : 'days' }}. Custom ranges cover up to
            {{ maxRangeDays }} days.
        </p>
    </div>
</template>
