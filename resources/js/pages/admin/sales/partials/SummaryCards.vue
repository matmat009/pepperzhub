<script setup lang="ts">
import { BadgeDollarSign, ShoppingCart, WalletCards } from '@lucide/vue';
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
    {
        label: 'Revenue',
        value: formatPrice(props.revenue),
        icon: WalletCards,
        tileClass:
            'bg-emerald-500/10 text-emerald-600 dark:bg-emerald-400/15 dark:text-emerald-400',
    },
    {
        label: 'Verified Orders',
        value: String(props.orderCount),
        icon: ShoppingCart,
        tileClass:
            'bg-sf-serenity-blue/20 text-sf-primary-deep dark:bg-sf-serenity-blue/15 dark:text-sf-serenity-blue',
    },
    {
        label: 'Average Order Value',
        value:
            averageOrderValue.value === null
                ? '—'
                : formatPrice(averageOrderValue.value),
        icon: BadgeDollarSign,
        tileClass:
            'bg-sf-rose-tint text-sf-rose-deep dark:bg-sf-rose-deep/15 dark:text-sf-rose-mid',
    },
]);
</script>

<template>
    <!-- The cards reach equal thirds only when each value and icon still has
         room; the intermediate two-column step keeps long currency readable. -->
    <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
        <div
            v-for="card in cards"
            :key="card.label"
            class="flex min-h-32 items-center rounded-2xl border border-sf-serenity-blue/20 bg-white p-5 shadow-[0_12px_30px_-22px_rgba(49,82,133,0.5)] dark:border-border dark:bg-card"
        >
            <div class="flex min-w-0 items-center gap-4">
                <span
                    class="grid size-14 shrink-0 place-items-center rounded-2xl"
                    :class="card.tileClass"
                >
                    <component :is="card.icon" class="size-6" />
                </span>
                <div class="min-w-0">
                    <p
                        class="text-sm font-medium text-sf-primary-soft dark:text-muted-foreground"
                    >
                        {{ card.label }}
                    </p>
                    <p
                        class="mt-2 text-3xl leading-none font-semibold tracking-tight tabular-nums"
                    >
                        {{ card.value }}
                    </p>
                    <p class="mt-2 text-sm leading-snug text-muted-foreground">
                        {{ periodLabel }}
                    </p>
                </div>
            </div>
        </div>
    </div>
</template>
