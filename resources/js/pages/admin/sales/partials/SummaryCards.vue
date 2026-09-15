<script setup lang="ts">
import { computed } from 'vue';
import { formatPrice } from '@/pages/admin/products/all-products/types';

/**
 * The three numbers that describe the selected period at a glance.
 *
 * Revenue and the order count are handed straight through from the page's own
 * props — the server's figures, the same ones the comparison line and the
 * Orders link read. Nothing here recomputes either, because what counts as
 * revenue is decided once in Order::revenueQuery() and a second opinion in
 * TypeScript is exactly the drift that rule exists to prevent.
 *
 * No trend arrows on purpose: the comparison against the prior period already
 * sits beside the chart, and repeating it three times would make the strip
 * compete with the thing it is meant to summarise.
 */
const props = defineProps<{
    revenue: number;
    orderCount: number;
    /** The period all three describe — "September 2026", say. */
    periodLabel: string;
}>();

/**
 * Revenue spread across the orders that produced it.
 *
 * The one derived number on this page, and the only one with an undefined
 * case: no orders means no average. Dividing anyway gives NaN, and ₱0.00 would
 * read as "orders averaging nothing" rather than "nothing to average" — a dash
 * is the only one of the three that is true.
 */
const averageOrderValue = computed(() =>
    props.orderCount > 0 ? props.revenue / props.orderCount : null,
);

const cards = computed(() => [
    { label: 'Revenue', value: formatPrice(props.revenue) },
    { label: 'Verified Orders', value: String(props.orderCount) },
    {
        label: 'Average Order Value',
        value:
            averageOrderValue.value === null
                ? '—'
                : formatPrice(averageOrderValue.value),
    },
]);
</script>

<template>
    <!--
        Equal thirds, and deliberately lighter than the chart card below:
        smaller type, no chrome beyond the border. This is a summary of what
        the chart shows, not a second dashboard above it.
    -->
    <div class="grid gap-4 sm:grid-cols-3">
        <div
            v-for="card in cards"
            :key="card.label"
            class="rounded-xl border bg-card p-4 shadow-xs"
        >
            <p class="text-sm font-medium text-muted-foreground">
                {{ card.label }}
            </p>
            <p
                class="mt-1.5 text-2xl leading-none font-semibold tracking-tight tabular-nums"
            >
                {{ card.value }}
            </p>
            <p class="mt-1.5 text-xs text-muted-foreground">
                {{ periodLabel }}
            </p>
        </div>
    </div>
</template>
