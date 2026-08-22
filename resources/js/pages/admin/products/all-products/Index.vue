<script setup lang="ts">
import type { RowSelectionState, Table } from '@tanstack/vue-table';
import { Head, Link, router } from '@inertiajs/vue3';
import {
    Archive,
    Check,
    Columns3,
    Download,
    ListFilter,
    Plus,
    Search,
    Trash2,
    X,
} from '@lucide/vue';
import { computed, ref } from 'vue';
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
import { Separator } from '@/components/ui/separator';
import { Tabs, TabsList, TabsTrigger } from '@/components/ui/tabs';
import type { Features } from '@/components/features';
import { create, index, show } from '@/routes/admin/products';
import { createProductColumns } from './columns';
import DeleteDialog from './partials/DeleteDialog.vue';
import { PRODUCT_CATEGORIES, PRODUCT_STATUSES } from './types';
import type { Product, ProductStatus } from './types';

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Products',
                href: index(),
            },
        ],
    },
});

const props = defineProps<{
    products: Product[];
}>();

type ProductTable = Table<Features, Product>;

const rowSelection = ref<RowSelectionState>({});
const deleteTarget = ref<Product | null>(null);
const deleteOpen = ref(false);

const goToProduct = (product: Product) => router.visit(show(product.id).url);

const requestDelete = (product: Product) => {
    deleteTarget.value = product;
    deleteOpen.value = true;
};

const columns = createProductColumns({
    onView: goToProduct,
    onEdit: (product) => router.visit(`${show(product.id).url}?edit=1`),
    onDuplicate: (product) =>
        router.visit(`${create().url}?from=${product.id}`),
    onDelete: requestDelete,
});

/** Counts sit on the tabs, so they reflect the catalog rather than the filter. */
const statusCounts = computed(() => {
    const counts: Record<string, number> = { all: props.products.length };

    for (const status of PRODUCT_STATUSES) {
        counts[status] = props.products.filter(
            (product) => product.status === status,
        ).length;
    }

    return counts;
});

const activeStatus = (table: ProductTable): string =>
    (table.getColumn('status')?.getFilterValue() as ProductStatus) ?? 'all';

const setStatus = (table: ProductTable, value: string) => {
    table
        .getColumn('status')
        ?.setFilterValue(value === 'all' ? undefined : value);
};

const searchValue = (table: ProductTable): string =>
    (table.getColumn('product')?.getFilterValue() as string) ?? '';

const setSearch = (table: ProductTable, value: string | number) => {
    table.getColumn('product')?.setFilterValue(String(value) || undefined);
};

const selectedCategories = (table: ProductTable): string[] =>
    (table.getColumn('category')?.getFilterValue() as string[]) ?? [];

const toggleCategory = (table: ProductTable, category: string) => {
    const current = selectedCategories(table);
    const next = current.includes(category)
        ? current.filter((item) => item !== category)
        : [...current, category];

    table.getColumn('category')?.setFilterValue(next.length ? next : undefined);
};

const hideableColumns = (table: ProductTable) =>
    table.getAllColumns().filter((column) => column.getCanHide());
</script>

<template>
    <Head title="Products" />

    <div class="flex flex-1 flex-col gap-6 px-4 py-6 lg:px-6">
        <header class="flex flex-wrap items-start justify-between gap-4">
            <div class="space-y-1">
                <h1 class="text-2xl font-semibold tracking-tight">Products</h1>
                <p class="text-sm text-muted-foreground">
                    Browse and manage your product catalog.
                </p>
            </div>
            <Button as-child>
                <Link :href="create()">
                    <Plus />
                    Add Product
                </Link>
            </Button>
        </header>

        <DataTable
            v-model:row-selection="rowSelection"
            :data="products"
            :columns="columns"
            row-clickable
            empty-message="No products match these filters."
            @row-click="goToProduct"
        >
            <template #toolbar="{ table }">
                <Tabs
                    :model-value="activeStatus(table as ProductTable)"
                    @update:model-value="
                        (value) =>
                            setStatus(table as ProductTable, String(value))
                    "
                >
                    <TabsList class="h-9">
                        <TabsTrigger value="all" class="gap-1.5 text-sm">
                            All
                            <span class="text-xs text-muted-foreground">
                                {{ statusCounts.all }}
                            </span>
                        </TabsTrigger>
                        <TabsTrigger
                            v-for="status in PRODUCT_STATUSES"
                            :key="status"
                            :value="status"
                            class="gap-1.5 text-sm"
                        >
                            {{ status }}
                            <span class="text-xs text-muted-foreground">
                                {{ statusCounts[status] }}
                            </span>
                        </TabsTrigger>
                    </TabsList>
                </Tabs>

                <div class="flex flex-wrap items-center gap-2">
                    <div class="relative flex-1 sm:max-w-xs">
                        <Search
                            class="pointer-events-none absolute top-1/2 left-2.5 size-4 -translate-y-1/2 text-muted-foreground"
                        />
                        <Input
                            :model-value="searchValue(table as ProductTable)"
                            placeholder="Search products..."
                            class="h-9 pl-8"
                            @update:model-value="
                                (value) =>
                                    setSearch(table as ProductTable, value)
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
                                            table as ProductTable,
                                        ).length
                                    "
                                    class="ml-1 rounded bg-primary px-1.5 text-xs text-primary-foreground"
                                >
                                    {{
                                        selectedCategories(
                                            table as ProductTable,
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
                                v-for="category in PRODUCT_CATEGORIES"
                                :key="category"
                                :model-value="
                                    selectedCategories(
                                        table as ProductTable,
                                    ).includes(category)
                                "
                                @select="
                                    (event: Event) => event.preventDefault()
                                "
                                @update:model-value="
                                    () =>
                                        toggleCategory(
                                            table as ProductTable,
                                            category,
                                        )
                                "
                            >
                                {{ category }}
                            </DropdownMenuCheckboxItem>
                        </DropdownMenuContent>
                    </DropdownMenu>

                    <DropdownMenu>
                        <DropdownMenuTrigger as-child>
                            <Button variant="outline" size="sm" class="h-9">
                                <Columns3 />
                                Columns
                            </Button>
                        </DropdownMenuTrigger>
                        <DropdownMenuContent align="end" class="w-44">
                            <DropdownMenuLabel>
                                Toggle columns
                            </DropdownMenuLabel>
                            <DropdownMenuSeparator />
                            <DropdownMenuCheckboxItem
                                v-for="column in hideableColumns(
                                    table as ProductTable,
                                )"
                                :key="column.id"
                                class="capitalize"
                                :model-value="column.getIsVisible()"
                                @select="
                                    (event: Event) => event.preventDefault()
                                "
                                @update:model-value="
                                    (value) => column.toggleVisibility(!!value)
                                "
                            >
                                {{ column.id.replace('_', ' ') }}
                            </DropdownMenuCheckboxItem>
                        </DropdownMenuContent>
                    </DropdownMenu>

                    <Button variant="outline" size="sm" class="h-9">
                        <Download />
                        Export
                    </Button>
                </div>
            </template>

            <template #bulk="{ table, selected }">
                <Transition
                    enter-active-class="transition duration-200 ease-out"
                    enter-from-class="-translate-y-1 opacity-0"
                    enter-to-class="translate-y-0 opacity-100"
                    leave-active-class="transition duration-150 ease-in"
                    leave-from-class="translate-y-0 opacity-100"
                    leave-to-class="-translate-y-1 opacity-0"
                >
                    <div
                        v-if="selected.length"
                        class="flex items-center gap-3 rounded-lg border bg-card px-3 py-2 shadow-sm"
                    >
                        <span class="text-sm font-medium">
                            {{ selected.length }} selected
                        </span>
                        <Separator
                            orientation="vertical"
                            class="data-[orientation=vertical]:h-5"
                        />
                        <Button variant="ghost" size="sm" class="h-8">
                            <Archive />
                            Archive
                        </Button>
                        <Button
                            variant="ghost"
                            size="sm"
                            class="h-8 text-destructive hover:bg-destructive/10 hover:text-destructive"
                        >
                            <Trash2 />
                            Delete
                        </Button>
                        <Button
                            variant="ghost"
                            size="icon"
                            class="ml-auto size-8 text-muted-foreground"
                            @click="table.resetRowSelection()"
                        >
                            <X class="size-4" />
                            <span class="sr-only">Clear selection</span>
                        </Button>
                    </div>
                </Transition>
            </template>

            <template #empty>
                <div class="flex flex-col items-center gap-1">
                    <Check class="size-5 text-muted-foreground" />
                    <span>No products match these filters.</span>
                </div>
            </template>
        </DataTable>
    </div>

    <DeleteDialog v-model:open="deleteOpen" :product="deleteTarget" />
</template>
