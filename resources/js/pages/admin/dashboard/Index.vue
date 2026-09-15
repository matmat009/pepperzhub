<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { useLowStockThreshold } from '@/composables/useLowStockThreshold';
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
    pendingPayments: PendingPayment[];
}>();

/*
 * Shared prop, not a page prop: the same number drives the storefront's
 * "Only N left" badge, so it is published once for every response rather than
 * handed to this screen alone.
 */
const lowStockThreshold = useLowStockThreshold();
</script>

<template>
    <Head title="Dashboard" />

    <div class="flex flex-1 flex-col gap-5 px-4 py-5 sm:gap-6 sm:py-6 lg:px-6">
        <header
            class="relative isolate flex min-h-48 items-center overflow-hidden rounded-2xl border border-white/70 px-6 py-8 shadow-sm sm:min-h-52 sm:px-9 sm:py-10 lg:px-10"
        >
            <img
                src="/images/admin/dashboard-banner.png"
                alt=""
                class="absolute inset-0 -z-10 size-full object-cover object-center"
                aria-hidden="true"
            />

            <div class="max-w-2xl text-sf-ink">
                <div
                    class="flex items-center gap-4 text-[0.6875rem] font-semibold tracking-[0.3em] text-sf-primary uppercase sm:text-xs"
                >
                    <span>PEPPERZHUB</span>
                    <span
                        aria-hidden="true"
                        class="h-px w-10 bg-sf-primary/65"
                    />
                </div>
                <h1
                    class="mt-3 text-4xl leading-none font-bold tracking-[-0.04em] sm:text-5xl lg:text-[3.5rem]"
                >
                    Dashboard
                </h1>
                <p
                    class="mt-3 text-base leading-relaxed text-sf-text sm:text-xl"
                >
                    What needs you right now, and how the month is going.
                </p>
            </div>
        </header>

        <StatsCards :stats="stats" :low-stock-threshold="lowStockThreshold" />

        <PendingPayments :payments="pendingPayments" />
    </div>
</template>
