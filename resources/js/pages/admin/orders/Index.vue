<script setup lang="ts">
import type { Table } from '@tanstack/vue-table';
import { Head, router } from '@inertiajs/vue3';
import { Clock3, ListFilter, Search, ShoppingCart, X } from '@lucide/vue';
import { computed } from 'vue';
import DataTable from '@/components/DataTable.vue';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuCheckboxItem,
    DropdownMenuContent,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { Input } from '@/components/ui/input';
import type { Features } from '@/components/features';
import { index, show } from '@/routes/admin/orders';
import { createOrderColumns } from './columns';
import type { OrderRow } from './types';

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Orders',
                href: index(),
            },
        ],
    },
});

const props = defineProps<{
    orders: OrderRow[];
    /** Enum value => label, from App\Support\OrderStatuses. */
    paymentStatuses: Record<string, string>;
    orderStatuses: Record<string, string>;
}>();

type OrderTable = Table<Features, OrderRow>;

const goToOrder = (order: OrderRow) => router.visit(show(order.id).url);

const columns = createOrderColumns();

/**
 * Filter state is read from and written to the table itself rather than
 * mirrored into refs, the same way the Products table does it — one source of
 * truth, so "clear" cannot leave the chrome and the rows disagreeing.
 */
const searchValue = (table: OrderTable): string =>
    (table.getColumn('order_number')?.getFilterValue() as string) ?? '';

const setSearch = (table: OrderTable, value: string | number) => {
    table.getColumn('order_number')?.setFilterValue(String(value) || undefined);
};

const selected = (table: OrderTable, column: string): string[] =>
    (table.getColumn(column)?.getFilterValue() as string[]) ?? [];

const toggleValue = (table: OrderTable, column: string, value: string) => {
    const current = selected(table, column);
    const next = current.includes(value)
        ? current.filter((item) => item !== value)
        : [...current, value];

    table.getColumn(column)?.setFilterValue(next.length ? next : undefined);
};

/**
 * Derived from the three filterable columns rather than table state — v9's
 * Table type exposes no getState().
 */
const hasFilters = (table: OrderTable): boolean =>
    searchValue(table) !== '' ||
    selected(table, 'payment_status').length > 0 ||
    selected(table, 'order_status').length > 0;

/** Awaiting verification is the queue the admin actually works from. */
const awaitingCount = computed(
    () =>
        props.orders.filter((order) => order.payment_status === 'unverified')
            .length,
);
</script>

<template>
    <Head title="Orders" />

    <div class="flex flex-col gap-6 p-4 md:p-6">
        <div class="space-y-4">
            <h1 class="text-2xl font-semibold tracking-tight">Orders</h1>

            <div class="grid gap-4 md:grid-cols-2">
                <section
                    aria-labelledby="total-orders-label"
                    class="relative isolate min-h-28 overflow-hidden rounded-xl border border-sf-serenity-blue/30 bg-sf-serenity-blue/10 px-5 py-4 shadow-xs dark:bg-sf-serenity-blue/15"
                >
                    <span
                        aria-hidden="true"
                        class="absolute -right-8 -bottom-16 -z-10 size-44 rounded-full bg-sf-serenity-blue/10 dark:bg-sf-serenity-blue/5"
                    />
                    <div class="flex h-full items-center gap-4">
                        <div
                            class="flex size-13 shrink-0 items-center justify-center rounded-full border border-white/90 bg-white/45 text-sf-primary shadow-xs dark:border-sf-serenity-blue/25 dark:bg-background/25 dark:text-sf-serenity-blue"
                        >
                            <ShoppingCart aria-hidden="true" class="size-6" />
                        </div>
                        <div class="min-w-0">
                            <p
                                id="total-orders-label"
                                class="text-sm font-medium text-sf-primary-soft dark:text-sf-serenity-blue"
                            >
                                Total Orders
                            </p>
                            <p class="mt-1 flex flex-wrap items-baseline gap-2">
                                <span
                                    class="text-4xl leading-none font-semibold tracking-tight text-sf-primary tabular-nums dark:text-sf-serenity-blue"
                                >
                                    {{ orders.length }}
                                </span>
                                <span
                                    class="text-sm text-sf-primary-soft/80 dark:text-sf-serenity-blue/80"
                                >
                                    {{
                                        orders.length === 1 ? 'order' : 'orders'
                                    }}
                                </span>
                            </p>
                        </div>
                    </div>
                </section>

                <section
                    aria-labelledby="awaiting-orders-label"
                    class="relative isolate min-h-28 overflow-hidden rounded-xl border border-sf-rose-line bg-sf-rose-tint/55 px-5 py-4 shadow-xs dark:bg-sf-rose-deep/10"
                >
                    <span
                        aria-hidden="true"
                        class="absolute -right-8 -bottom-16 -z-10 size-44 rounded-full bg-sf-rose-quartz/20 dark:bg-sf-rose-deep/5"
                    />
                    <div class="flex h-full items-center gap-4">
                        <div
                            class="flex size-13 shrink-0 items-center justify-center rounded-full border border-white/90 bg-white/45 text-sf-rose-deep shadow-xs dark:border-sf-rose-line/40 dark:bg-background/25 dark:text-sf-rose-mid"
                        >
                            <Clock3 aria-hidden="true" class="size-6" />
                        </div>
                        <div class="min-w-0">
                            <p
                                id="awaiting-orders-label"
                                class="text-sm font-medium text-sf-rose-deep dark:text-sf-rose-mid"
                            >
                                Awaiting Payment Verification
                            </p>
                            <p class="mt-1 flex flex-wrap items-baseline gap-2">
                                <span
                                    class="text-4xl leading-none font-semibold tracking-tight text-sf-rose-deep tabular-nums dark:text-sf-rose-mid"
                                >
                                    {{ awaitingCount }}
                                </span>
                                <span
                                    class="text-sm text-sf-rose-deep/75 dark:text-sf-rose-mid/80"
                                >
                                    {{
                                        awaitingCount === 1 ? 'order' : 'orders'
                                    }}
                                </span>
                            </p>
                        </div>
                    </div>
                </section>
            </div>
        </div>

        <DataTable
            :data="orders"
            :columns="columns"
            row-clickable
            empty-message="No orders match these filters."
            @row-click="goToOrder"
        >
            <template #toolbar="{ table }">
                <div class="flex flex-wrap items-center gap-2">
                    <div class="relative">
                        <Search
                            class="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground"
                        />
                        <Input
                            :model-value="searchValue(table as OrderTable)"
                            placeholder="Order no., name or phone"
                            aria-label="Search orders"
                            class="w-64 pl-9"
                            @update:model-value="
                                (value) => setSearch(table as OrderTable, value)
                            "
                        />
                    </div>

                    <DropdownMenu>
                        <DropdownMenuTrigger as-child>
                            <Button variant="outline" size="sm">
                                <ListFilter class="size-4" />
                                Payment
                            </Button>
                        </DropdownMenuTrigger>
                        <DropdownMenuContent align="start" class="w-52">
                            <DropdownMenuLabel>
                                Payment status
                            </DropdownMenuLabel>
                            <DropdownMenuSeparator />
                            <DropdownMenuCheckboxItem
                                v-for="(label, value) in paymentStatuses"
                                :key="value"
                                :model-value="
                                    selected(
                                        table as OrderTable,
                                        'payment_status',
                                    ).includes(value)
                                "
                                @select="(event) => event.preventDefault()"
                                @update:model-value="
                                    toggleValue(
                                        table as OrderTable,
                                        'payment_status',
                                        value,
                                    )
                                "
                            >
                                {{ label }}
                            </DropdownMenuCheckboxItem>
                        </DropdownMenuContent>
                    </DropdownMenu>

                    <DropdownMenu>
                        <DropdownMenuTrigger as-child>
                            <Button variant="outline" size="sm">
                                <ListFilter class="size-4" />
                                Fulfillment
                            </Button>
                        </DropdownMenuTrigger>
                        <DropdownMenuContent align="start" class="w-52">
                            <DropdownMenuLabel>Order status</DropdownMenuLabel>
                            <DropdownMenuSeparator />
                            <DropdownMenuCheckboxItem
                                v-for="(label, value) in orderStatuses"
                                :key="value"
                                :model-value="
                                    selected(
                                        table as OrderTable,
                                        'order_status',
                                    ).includes(value)
                                "
                                @select="(event) => event.preventDefault()"
                                @update:model-value="
                                    toggleValue(
                                        table as OrderTable,
                                        'order_status',
                                        value,
                                    )
                                "
                            >
                                {{ label }}
                            </DropdownMenuCheckboxItem>
                        </DropdownMenuContent>
                    </DropdownMenu>

                    <Button
                        v-if="hasFilters(table as OrderTable)"
                        variant="ghost"
                        size="sm"
                        @click="(table as OrderTable).resetColumnFilters()"
                    >
                        <X class="size-4" />
                        Clear
                    </Button>
                </div>
            </template>
        </DataTable>
    </div>
</template>
