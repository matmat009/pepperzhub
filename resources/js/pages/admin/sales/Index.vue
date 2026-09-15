<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import {
    ArrowRight,
    Download,
    Minus,
    TrendingDown,
    TrendingUp,
} from '@lucide/vue';
import { computed } from 'vue';
import { Button } from '@/components/ui/button';
import { formatPrice } from '@/pages/admin/products/all-products/types';
import { index as ordersIndex } from '@/routes/admin/orders';
// `export` is a reserved word, so Wayfinder emits the endpoint as exportMethod.
import {
    exportMethod as salesExport,
    index as salesIndex,
} from '@/routes/admin/sales';
import RangePicker from './partials/RangePicker.vue';
import RevenueChart from './partials/RevenueChart.vue';
import TopProducts from './partials/TopProducts.vue';
import { formatPercent, percentChange } from './types';
import type {
    RangeKey,
    SalesComparison,
    SalesPoint,
    SalesRange,
    TopProducts as TopProductsShape,
} from './types';

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Sales',
                href: salesIndex(),
            },
        ],
    },
});

const props = defineProps<{
    range: SalesRange;
    revenue: number;
    orderCount: number;
    comparison: SalesComparison;
    series: SalesPoint[];
    topProducts: TopProductsShape;
    rangeKeys: RangeKey[];
    maxRangeDays: number;
}>();

/**
 * The query the server was asked for, rebuilt from the range it answered with.
 *
 * Everything that leaves this page — the export, the link to Orders, the next
 * visit — is built from this one object, so the three can never describe
 * different windows.
 */
const query = computed(() => ({
    range: props.range.key,
    start: props.range.start,
    end: props.range.end,
}));

const select = (payload: { key: RangeKey; start?: string; end?: string }) => {
    router.get(
        salesIndex.url({
            query: {
                range: payload.key,
                start: payload.start,
                end: payload.end,
            },
        }),
        {},
        { preserveScroll: true, preserveState: true },
    );
};

const exportUrl = computed(() => salesExport.url({ query: query.value }));

/**
 * Orders, narrowed to exactly what this page counted.
 *
 * All three conditions, not just the date: revenue is verified *and*
 * not-cancelled, so a link carrying only the range would list orders the
 * figure above it deliberately excluded, and the two would appear to disagree.
 */
const ordersUrl = computed(() =>
    ordersIndex.url({
        query: {
            payment_status: 'verified',
            exclude_cancelled: 1,
            verified_from: props.range.start,
            verified_to: props.range.end,
        },
    }),
);

const change = computed(() =>
    percentChange(props.revenue, props.comparison.revenue),
);

const direction = computed(() => {
    if (change.value === null || change.value === 0) {
        return 'flat';
    }

    return change.value > 0 ? 'up' : 'down';
});

const trendIcon = computed(() =>
    direction.value === 'up'
        ? TrendingUp
        : direction.value === 'down'
          ? TrendingDown
          : Minus,
);

const trendTone = computed(() =>
    direction.value === 'up'
        ? 'text-emerald-600 dark:text-emerald-400'
        : direction.value === 'down'
          ? 'text-red-600 dark:text-red-400'
          : 'text-muted-foreground',
);
</script>

<template>
    <Head title="Sales" />

    <div class="flex flex-col gap-6 p-4 md:p-6">
        <header class="flex flex-wrap items-start justify-between gap-4">
            <div class="space-y-1">
                <h1 class="text-2xl font-semibold tracking-tight">Sales</h1>
                <p class="text-sm text-muted-foreground">
                    Verified revenue, by the date each payment was confirmed.
                </p>
            </div>
            <Button as-child variant="outline">
                <!--
                    A plain anchor, not an Inertia Link: the response is a CSV
                    download, and Inertia would try to parse it as a page.
                -->
                <a :href="exportUrl" download>
                    <Download />
                    Export CSV
                </a>
            </Button>
        </header>

        <div class="rounded-xl border bg-card p-4 shadow-xs md:p-5">
            <RangePicker
                :range="range"
                :max-range-days="maxRangeDays"
                @select="select"
            />
        </div>

        <div class="grid gap-6 lg:grid-cols-[minmax(0,2fr)_minmax(0,1fr)]">
            <section
                class="flex flex-col gap-5 rounded-xl border bg-card p-5 shadow-xs"
            >
                <div class="flex flex-wrap items-end justify-between gap-4">
                    <div class="space-y-1">
                        <p class="text-sm text-muted-foreground">
                            Revenue · {{ range.label }}
                        </p>
                        <p
                            class="text-4xl leading-none font-semibold tracking-tight tabular-nums"
                        >
                            {{ formatPrice(revenue) }}
                        </p>
                        <p class="flex flex-wrap items-center gap-2 text-sm">
                            <span
                                :class="[
                                    'flex items-center gap-1 font-medium',
                                    trendTone,
                                ]"
                            >
                                <component
                                    :is="trendIcon"
                                    aria-hidden="true"
                                    class="size-4"
                                />
                                <template v-if="change === null">
                                    No prior sales
                                </template>
                                <template v-else>
                                    {{ formatPercent(change) }}
                                </template>
                            </span>
                            <span class="text-muted-foreground">
                                vs {{ formatPrice(comparison.revenue) }} in
                                {{ comparison.label }}
                            </span>
                        </p>
                    </div>

                    <Link
                        :href="ordersUrl"
                        class="inline-flex items-center gap-1.5 text-sm font-medium text-sf-primary underline-offset-4 hover:underline"
                    >
                        {{ orderCount }}
                        {{ orderCount === 1 ? 'order' : 'orders' }} in Orders
                        <ArrowRight aria-hidden="true" class="size-4" />
                    </Link>
                </div>

                <RevenueChart :points="series" />
            </section>

            <section class="rounded-xl border bg-card p-5 shadow-xs">
                <TopProducts :products="topProducts" />
            </section>
        </div>
    </div>
</template>
