<script setup lang="ts">
import type { Table } from '@tanstack/vue-table';
import { Head } from '@inertiajs/vue3';
import { ListFilter, Search } from '@lucide/vue';
import { computed, ref, watch } from 'vue';
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
import { Label } from '@/components/ui/label';
import { Switch } from '@/components/ui/switch';
import type { Features } from '@/components/features';
import { index } from '@/routes/admin/products/inventory';
import { createInventoryColumns } from './columns';
import StockAdjustDialog from './partials/StockAdjustDialog.vue';
import StockHistoryDialog from './partials/StockHistoryDialog.vue';
import { cloneItems, isLowStock, today } from './types';
import type { InventoryItem, StockReason } from './types';

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Inventory',
                href: index(),
            },
        ],
    },
});

const props = defineProps<{
    items: InventoryItem[];
}>();

type InventoryTable = Table<Features, InventoryItem>;

/**
 * Local working copy: the adjust endpoint is a stub, so applied movements live
 * here. Reseeded whenever the server sends a fresh list.
 */
const rows = ref<InventoryItem[]>(cloneItems(props.items));

watch(
    () => props.items,
    (items) => {
        rows.value = cloneItems(items);
    },
);

const adjustTarget = ref<InventoryItem | null>(null);
const adjustOpen = ref(false);
const historyTarget = ref<InventoryItem | null>(null);
const historyOpen = ref(false);

const openAdjust = (item: InventoryItem) => {
    adjustTarget.value = item;
    adjustOpen.value = true;
};

const openHistory = (item: InventoryItem) => {
    historyTarget.value = item;
    historyOpen.value = true;
};

const columns = createInventoryColumns({
    onAdjust: openAdjust,
    onHistory: openHistory,
});

const categories = computed(() =>
    [...new Set(props.items.map((item) => item.category))].sort(),
);

const applyAdjustment = (payload: {
    item: InventoryItem;
    delta: number;
    reason: StockReason;
    note: string;
}) => {
    const row = rows.value.find((item) => item.id === payload.item.id);

    if (!row) {
        return;
    }

    const resulting = Math.max(0, row.stock + payload.delta);

    row.stock = resulting;
    row.updated_at = today();
    row.history.push({
        id: (row.history.at(-1)?.id ?? 0) + 1,
        date: today(),
        delta: payload.delta,
        reason: payload.reason,
        resulting_stock: resulting,
        note: payload.note.trim() || null,
    });

    // Keep the open dialogs pointed at the updated row.
    adjustTarget.value = row;
    historyTarget.value = historyTarget.value ? row : null;
};

const searchValue = (table: InventoryTable): string =>
    (table.getColumn('product')?.getFilterValue() as string) ?? '';

const setSearch = (table: InventoryTable, value: string | number) => {
    table.getColumn('product')?.setFilterValue(String(value) || undefined);
};

const lowOnly = (table: InventoryTable): boolean =>
    Boolean(table.getColumn('stock')?.getFilterValue());

const setLowOnly = (table: InventoryTable, value: boolean) => {
    table.getColumn('stock')?.setFilterValue(value ? true : undefined);
};

const selectedCategories = (table: InventoryTable): string[] =>
    (table.getColumn('category')?.getFilterValue() as string[]) ?? [];

const toggleCategory = (table: InventoryTable, category: string) => {
    const current = selectedCategories(table);
    const next = current.includes(category)
        ? current.filter((item) => item !== category)
        : [...current, category];

    table.getColumn('category')?.setFilterValue(next.length ? next : undefined);
};

const lowStockCount = computed(
    () => rows.value.filter((item) => isLowStock(item.stock)).length,
);
</script>

<template>
    <Head title="Inventory" />

    <div class="flex flex-1 flex-col gap-6 px-4 py-6 lg:px-6">
        <header class="flex flex-wrap items-start justify-between gap-4">
            <div class="space-y-1">
                <h1 class="text-2xl font-semibold tracking-tight">Inventory</h1>
                <p class="text-sm text-muted-foreground">
                    Track and manage stock levels.
                </p>
            </div>
            <p
                v-if="lowStockCount"
                class="rounded-lg border border-amber-600/20 bg-amber-500/10 px-3 py-1.5 text-sm font-medium text-amber-800 dark:border-amber-400/20 dark:text-amber-300"
            >
                {{ lowStockCount }} item{{ lowStockCount === 1 ? '' : 's' }}
                need attention
            </p>
        </header>

        <DataTable
            :data="rows"
            :columns="columns"
            empty-message="No stock records match these filters."
        >
            <template #toolbar="{ table }">
                <div class="flex flex-wrap items-center gap-2">
                    <div class="relative flex-1 sm:max-w-xs">
                        <Search
                            class="pointer-events-none absolute top-1/2 left-2.5 size-4 -translate-y-1/2 text-muted-foreground"
                        />
                        <Input
                            :model-value="searchValue(table as InventoryTable)"
                            placeholder="Search products..."
                            class="h-9 pl-8"
                            @update:model-value="
                                (value) =>
                                    setSearch(table as InventoryTable, value)
                            "
                        />
                    </div>

                    <DropdownMenu>
                        <DropdownMenuTrigger as-child>
                            <Button variant="outline" size="sm" class="h-9">
                                <ListFilter />
                                Category
                                <span
                                    v-if="
                                        selectedCategories(
                                            table as InventoryTable,
                                        ).length
                                    "
                                    class="ml-1 rounded bg-primary px-1.5 text-xs text-primary-foreground"
                                >
                                    {{
                                        selectedCategories(
                                            table as InventoryTable,
                                        ).length
                                    }}
                                </span>
                            </Button>
                        </DropdownMenuTrigger>
                        <DropdownMenuContent align="end" class="w-48">
                            <DropdownMenuLabel>
                                Filter by category
                            </DropdownMenuLabel>
                            <DropdownMenuSeparator />
                            <DropdownMenuCheckboxItem
                                v-for="category in categories"
                                :key="category"
                                :model-value="
                                    selectedCategories(
                                        table as InventoryTable,
                                    ).includes(category)
                                "
                                @select="
                                    (event: Event) => event.preventDefault()
                                "
                                @update:model-value="
                                    () =>
                                        toggleCategory(
                                            table as InventoryTable,
                                            category,
                                        )
                                "
                            >
                                {{ category }}
                            </DropdownMenuCheckboxItem>
                        </DropdownMenuContent>
                    </DropdownMenu>

                    <div
                        class="ml-auto flex h-9 items-center gap-2 rounded-md border px-3"
                    >
                        <Switch
                            id="low-stock-only"
                            :model-value="lowOnly(table as InventoryTable)"
                            @update:model-value="
                                (value) =>
                                    setLowOnly(table as InventoryTable, value)
                            "
                        />
                        <Label
                            for="low-stock-only"
                            class="text-sm font-normal whitespace-nowrap"
                        >
                            Low stock only
                        </Label>
                    </div>
                </div>
            </template>
        </DataTable>
    </div>

    <StockAdjustDialog
        v-model:open="adjustOpen"
        :item="adjustTarget"
        @adjusted="applyAdjustment"
    />
    <StockHistoryDialog v-model:open="historyOpen" :item="historyTarget" />
</template>
