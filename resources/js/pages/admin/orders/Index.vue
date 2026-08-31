<script setup lang="ts">
import type { Table } from '@tanstack/vue-table';
import { Head, router } from '@inertiajs/vue3';
import { ListFilter, Search, X } from '@lucide/vue';
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
        <div>
            <h1 class="text-2xl font-semibold tracking-tight">Orders</h1>
            <p class="mt-1 text-sm text-muted-foreground">
                {{ orders.length }}
                {{ orders.length === 1 ? 'order' : 'orders' }}
                <template v-if="awaitingCount">
                    · {{ awaitingCount }} awaiting payment verification
                </template>
            </p>
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
