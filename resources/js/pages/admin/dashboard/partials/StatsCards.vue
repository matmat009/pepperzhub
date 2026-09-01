<script setup lang="ts">
import { Banknote, PackageX, ShoppingCart, Wallet } from '@lucide/vue';
import { computed } from 'vue';
import { formatPrice } from '@/pages/admin/products/all-products/types';
import type { DashboardStats } from '../types';

/**
 * Four numbers, one row.
 *
 * Every value arrives computed — notably revenue, whose definition (verified,
 * not cancelled, this calendar month, dated by verification) lives in
 * DashboardController and must not be re-derived here.
 */
const props = defineProps<{
    stats: DashboardStats;
    /** From the server, so the threshold is stated in one place only. */
    lowStockThreshold: number;
}>();

/** Explicit entries rather than a loop over the props object, so the order,
 * wording and emphasis of each tile are visible at a glance. */
const cards = computed(() => [
    {
        key: 'pending',
        label: 'Pending Verification',
        value: String(props.stats.pending_verification),
        hint:
            props.stats.pending_verification === 1
                ? 'order waiting'
                : 'orders waiting',
        icon: Wallet,
        // The only tile that represents work owed to a customer, so it is the
        // only one that changes colour when it is non-zero.
        emphasis: props.stats.pending_verification > 0,
    },
    {
        key: 'today',
        label: 'Orders Today',
        value: String(props.stats.orders_today),
        hint: 'placed since midnight',
        icon: ShoppingCart,
        emphasis: false,
    },
    {
        key: 'revenue',
        label: 'Revenue This Month',
        value: formatPrice(props.stats.revenue_this_month),
        hint: 'verified payments only',
        icon: Banknote,
        emphasis: false,
    },
    {
        key: 'stock',
        label: 'Low / Out of Stock',
        value: String(props.stats.low_stock),
        hint: `formats at or below ${props.lowStockThreshold}`,
        icon: PackageX,
        emphasis: props.stats.low_stock > 0,
    },
]);
</script>

<template>
    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <section
            v-for="card in cards"
            :key="card.key"
            class="rounded-xl border bg-card p-5"
        >
            <div class="flex items-start justify-between gap-3">
                <span class="text-sm text-muted-foreground">
                    {{ card.label }}
                </span>
                <component
                    :is="card.icon"
                    class="size-4 shrink-0 text-muted-foreground"
                />
            </div>
            <p
                class="mt-3 text-3xl font-semibold tabular-nums"
                :class="card.emphasis ? 'text-amber-600' : ''"
            >
                {{ card.value }}
            </p>
            <p class="mt-1 text-xs text-muted-foreground">{{ card.hint }}</p>
        </section>
    </div>
</template>
