<script setup lang="ts">
import { computed, ref } from 'vue';
import { Tabs, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { formatPrice } from '@/pages/admin/products/all-products/types';
import type { TopProducts } from '../types';

/**
 * Best sellers, by money or by units.
 *
 * Two rankings rather than one sortable table: they answer different questions
 * — what earns and what moves — and a shop selling one ₱1,000 kit against
 * forty ₱45 vials wants both visible as distinct facts, not as one column the
 * reader has to re-sort to notice.
 *
 * Names are the order lines' own snapshots, so a product renamed or deleted
 * since still appears here under what it actually sold as.
 */
const props = defineProps<{
    products: TopProducts;
}>();

const mode = ref<'revenue' | 'units'>('revenue');

const rows = computed(() =>
    mode.value === 'revenue'
        ? props.products.by_revenue
        : props.products.by_units,
);

// Bar widths are relative to the leader, so the shape of the list is readable
// at a glance whatever the absolute numbers happen to be.
const leader = computed(() =>
    Math.max(
        1,
        ...rows.value.map((row) =>
            mode.value === 'revenue' ? row.revenue : row.units,
        ),
    ),
);

const share = (value: number) =>
    `${Math.max(2, (value / leader.value) * 100)}%`;
</script>

<template>
    <div class="flex flex-col gap-5">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <h2 class="text-xl font-semibold tracking-tight">Top products</h2>
            <Tabs v-model="mode">
                <TabsList
                    class="h-10 rounded-lg border border-sf-serenity-blue/20 bg-muted/40 p-0.5 shadow-none"
                >
                    <TabsTrigger
                        value="revenue"
                        class="h-9 rounded-md px-3 text-xs data-[state=active]:border-sf-serenity-blue/30 data-[state=active]:bg-sf-serenity-blue/15 data-[state=active]:text-sf-primary-deep data-[state=active]:shadow-none dark:data-[state=active]:text-sf-serenity-blue"
                    >
                        By revenue
                    </TabsTrigger>
                    <TabsTrigger
                        value="units"
                        class="h-9 rounded-md px-3 text-xs data-[state=active]:border-sf-serenity-blue/30 data-[state=active]:bg-sf-serenity-blue/15 data-[state=active]:text-sf-primary-deep data-[state=active]:shadow-none dark:data-[state=active]:text-sf-serenity-blue"
                    >
                        By units
                    </TabsTrigger>
                </TabsList>
            </Tabs>
        </div>

        <ol v-if="rows.length" class="flex flex-col gap-5">
            <li
                v-for="(row, position) in rows"
                :key="row.product_name"
                class="flex flex-col gap-2"
            >
                <div class="flex items-baseline justify-between gap-3 text-sm">
                    <span class="flex min-w-0 items-baseline gap-2">
                        <span
                            class="w-4 shrink-0 text-xs text-muted-foreground tabular-nums"
                        >
                            {{ position + 1 }}
                        </span>
                        <span class="truncate font-medium">
                            {{ row.product_name }}
                        </span>
                    </span>
                    <span class="shrink-0 font-medium tabular-nums">
                        <template v-if="mode === 'revenue'">
                            {{ formatPrice(row.revenue) }}
                        </template>
                        <template v-else>
                            {{ row.units }}
                            {{ row.units === 1 ? 'unit' : 'units' }}
                        </template>
                    </span>
                </div>
                <div
                    class="h-2 overflow-hidden rounded-full bg-sf-line-strong dark:bg-muted"
                >
                    <div
                        class="h-full rounded-full bg-sf-rose"
                        :style="{
                            width: share(
                                mode === 'revenue' ? row.revenue : row.units,
                            ),
                        }"
                    />
                </div>
            </li>
        </ol>

        <p v-else class="py-8 text-center text-sm text-muted-foreground">
            No verified sales in this range.
        </p>
    </div>
</template>
