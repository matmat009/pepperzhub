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
        tileClass:
            'bg-orange-500/10 text-orange-600 dark:bg-orange-400/15 dark:text-orange-400',
        // The only tile that represents work owed to a customer, so it is the
        // only one that changes colour when it is non-zero.
        valueClass:
            props.stats.pending_verification > 0
                ? 'text-orange-600 dark:text-orange-400'
                : 'text-foreground',
    },
    {
        key: 'today',
        label: 'Orders Today',
        value: String(props.stats.orders_today),
        hint: 'placed since midnight',
        icon: ShoppingCart,
        tileClass:
            'bg-sf-serenity-blue/20 text-sf-primary-deep dark:bg-sf-serenity-blue/15 dark:text-sf-serenity-blue',
        valueClass: 'text-foreground',
    },
    {
        key: 'revenue',
        label: 'Revenue This Month',
        value: formatPrice(props.stats.revenue_this_month),
        hint: 'verified payments only',
        icon: Banknote,
        tileClass:
            'bg-emerald-500/10 text-emerald-600 dark:bg-emerald-400/15 dark:text-emerald-400',
        valueClass: 'text-foreground',
    },
    {
        key: 'stock',
        label: 'Low / Out of Stock',
        value: String(props.stats.low_stock),
        hint: `formats at or below ${props.lowStockThreshold}`,
        icon: PackageX,
        tileClass:
            'bg-sf-rose-tint text-sf-rose-deep dark:bg-sf-rose-deep/15 dark:text-sf-rose-mid',
        valueClass:
            props.stats.low_stock > 0
                ? 'text-sf-rose-deep dark:text-sf-rose-mid'
                : 'text-foreground',
    },
]);
</script>

<template>
    <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
        <section
            v-for="card in cards"
            :key="card.key"
            class="flex min-h-36 items-center rounded-2xl border border-sf-serenity-blue/20 bg-white p-5 shadow-[0_12px_30px_-22px_rgba(49,82,133,0.5)] dark:border-border dark:bg-card"
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
                        :class="card.valueClass"
                    >
                        {{ card.value }}
                    </p>
                    <p class="mt-2 text-sm leading-snug text-muted-foreground">
                        {{ card.hint }}
                    </p>
                </div>
            </div>
        </section>
    </div>
</template>
