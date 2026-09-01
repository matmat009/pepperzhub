<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { dashboard } from '@/routes';
import PendingPayments from './partials/PendingPayments.vue';
import StatsCards from './partials/StatsCards.vue';
import type { DashboardStats, PendingPayment } from './types';

/**
 * Deliberately two widgets.
 *
 * Recent Orders and a revenue chart were both considered and cut: the Orders
 * screen already does the first better, and at this order volume the second is
 * one number, which now sits in the stats row.
 */
defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Dashboard',
                href: dashboard(),
            },
        ],
    },
});

defineProps<{
    stats: DashboardStats;
    lowStockThreshold: number;
    pendingPayments: PendingPayment[];
}>();
</script>

<template>
    <Head title="Dashboard" />

    <div class="flex flex-1 flex-col gap-6 px-4 py-6 lg:px-6">
        <header class="space-y-1">
            <h1 class="text-2xl font-semibold tracking-tight">Dashboard</h1>
            <p class="text-sm text-muted-foreground">
                What needs you right now, and how the month is going.
            </p>
        </header>

        <StatsCards :stats="stats" :low-stock-threshold="lowStockThreshold" />

        <PendingPayments :payments="pendingPayments" />
    </div>
</template>
