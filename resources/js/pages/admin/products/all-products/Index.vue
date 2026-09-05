<script setup lang="ts">
import type { RowSelectionState, Table } from '@tanstack/vue-table';
import { Head, Link, router } from '@inertiajs/vue3';
import {
    Archive,
    Check,
    Columns3,
    Download,
    ListFilter,
    MoreHorizontal,
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
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { Input } from '@/components/ui/input';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Separator } from '@/components/ui/separator';
import { Tabs, TabsList, TabsTrigger } from '@/components/ui/tabs';
import type { Features } from '@/components/features';
import { bulkArchive, create, index, show } from '@/routes/admin/products';
import { createProductColumns } from './columns';
import BulkDeleteDialog from './partials/BulkDeleteDialog.vue';
import DeleteDialog from './partials/DeleteDialog.vue';
import FormatBreakdown from './partials/FormatBreakdown.vue';
import ProductCard from './partials/ProductCard.vue';
import { PRODUCT_STATUSES } from './types';
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
const bulkDeleteIds = ref<number[]>([]);
const bulkDeleteOpen = ref(false);
const bulkArchiving = ref(false);

const goToProduct = (product: Product) => router.visit(show(product.id).url);

const requestDelete = (product: Product) => {
    deleteTarget.value = product;
    deleteOpen.value = true;
};

const selectedIds = (products: Product[]) =>
    products.map((product) => product.id);

const archiveSelected = (products: Product[]) => {
    const ids = selectedIds(products);

    if (!ids.length) {
        return;
    }

    router.post(
        bulkArchive().url,
        { ids },
        {
            preserveScroll: true,
            onStart: () => {
                bulkArchiving.value = true;
            },
            onSuccess: () => {
                rowSelection.value = {};
            },
            onFinish: () => {
                bulkArchiving.value = false;
            },
        },
    );
};

const requestBulkDelete = (products: Product[]) => {
    bulkDeleteIds.value = selectedIds(products);
    bulkDeleteOpen.value = bulkDeleteIds.value.length > 0;
};

const bulkDeleted = () => {
    rowSelection.value = {};
    bulkDeleteIds.value = [];
};

/** Only products with more than one format have a breakdown worth revealing. */
const canExpandRow = (product: Product) => product.variants.length > 1;

const columns = createProductColumns({
    onView: goToProduct,
    onEdit: (product) => router.visit(`${show(product.id).url}?edit=1`),
    onDuplicate: (product) =>
        router.visit(`${create().url}?from=${product.id}`),
    onDelete: requestDelete,
});

/** Category filter options come from the data, so they cannot drift. */
const categories = computed(() =>
    [...new Set(props.products.map((product) => product.category))].sort(),
);

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

/**
 * Drives the page's bottom padding on mobile. The bulk bar is fixed to the
 * viewport there, so without this it would sit over the pagination controls.
 */
const hasSelection = computed(() =>
    Object.values(rowSelection.value).some(Boolean),
);
</script>

<template>
    <Head title="Products" />

    <div
        :class="[
            'flex flex-1 flex-col gap-6 px-4 py-6 lg:px-6',
            hasSelection && 'pb-24 md:pb-6',
        ]"
    >
        <header
            class="flex flex-col gap-4 md:flex-row md:flex-wrap md:items-start md:justify-between"
        >
            <div class="space-y-1">
                <h1 class="text-2xl font-semibold tracking-tight">Products</h1>
                <p class="text-sm text-muted-foreground">
                    Browse and manage your product catalog.
                </p>
            </div>
            <Button as-child class="w-full md:w-auto">
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
            :can-expand-row="canExpandRow"
            row-clickable
            empty-message="No products match these filters."
            @row-click="goToProduct"
        >
            <template #toolbar="{ table }">
                <!--
                    Four tabs do not fit a 375px viewport without wrapping to a
                    second row, so below `md` the same filter collapses to a
                    Select. One source of truth either way: both read and write
                    the status column's filter value.
                -->
                <div class="md:hidden">
                    <Select
                        :model-value="activeStatus(table as ProductTable)"
                        @update:model-value="
                            (value) =>
                                setStatus(table as ProductTable, String(value))
                        "
                    >
                        <SelectTrigger class="h-9 w-full" aria-label="Status">
                            <SelectValue />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="all">
                                All ({{ statusCounts.all }})
                            </SelectItem>
                            <SelectItem
                                v-for="status in PRODUCT_STATUSES"
                                :key="status"
                                :value="status"
                            >
                                {{ status }} ({{ statusCounts[status] }})
                            </SelectItem>
                        </SelectContent>
                    </Select>
                </div>

                <Tabs
                    class="hidden md:flex"
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
                            <Button
                                variant="outline"
                                size="sm"
                                class="hidden h-9 md:inline-flex"
                            >
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
                                v-for="category in categories"
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
                            <!--
                                Column visibility has nothing to act on in card
                                view, so the control goes with the table.
                            -->
                            <Button
                                variant="outline"
                                size="sm"
                                class="hidden h-9 md:inline-flex"
                            >
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

                    <Button
                        variant="outline"
                        size="sm"
                        class="hidden h-9 md:inline-flex"
                    >
                        <Download />
                        Export
                    </Button>

                    <!--
                        Below `md` the category filter and Export fold into one
                        overflow menu, leaving the search field the full width
                        of the row rather than a third of it.
                    -->
                    <DropdownMenu>
                        <DropdownMenuTrigger as-child>
                            <Button
                                variant="outline"
                                size="icon"
                                class="size-9 shrink-0 md:hidden"
                            >
                                <MoreHorizontal />
                                <span class="sr-only">
                                    More filters and actions
                                </span>
                            </Button>
                        </DropdownMenuTrigger>
                        <DropdownMenuContent align="end" class="w-56">
                            <DropdownMenuLabel>
                                Filter by category
                            </DropdownMenuLabel>
                            <DropdownMenuSeparator />
                            <DropdownMenuCheckboxItem
                                v-for="category in categories"
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
                            <DropdownMenuSeparator />
                            <DropdownMenuItem>
                                <Download />
                                Export
                            </DropdownMenuItem>
                        </DropdownMenuContent>
                    </DropdownMenu>
                </div>
            </template>

            <template #bulk="{ table, selected }">
                <!--
                    Selecting a row is occasional, and the bar would otherwise
                    pop into the layout above the table — this is the
                    "prevent a jarring change" case. Exit is faster than enter:
                    the user has already decided by then.

                    The bar is pinned to the bottom of the viewport below `md`,
                    where the top of a phone screen is the hardest place to
                    reach; from `md` up it sits in flow above the table as
                    before. The enter offset follows it, so the bar always
                    arrives from the edge it is anchored to rather than sliding
                    down out of the bottom of the screen.
                -->
                <Transition
                    enter-active-class="transition-[opacity,transform] duration-200 ease-out motion-reduce:transition-opacity"
                    enter-from-class="translate-y-1 opacity-0 md:-translate-y-1 motion-reduce:translate-y-0"
                    enter-to-class="translate-y-0 opacity-100"
                    leave-active-class="transition-[opacity,transform] duration-150 ease-out motion-reduce:transition-opacity"
                    leave-from-class="translate-y-0 opacity-100"
                    leave-to-class="translate-y-1 opacity-0 md:-translate-y-1 motion-reduce:translate-y-0"
                >
                    <div
                        v-if="selected.length"
                        class="fixed inset-x-0 bottom-0 z-30 flex items-center gap-3 border-t bg-card px-4 pt-3 pb-[calc(0.75rem+env(safe-area-inset-bottom))] shadow-lg md:static md:rounded-lg md:border md:px-3 md:py-2 md:shadow-sm"
                    >
                        <span class="text-sm font-medium">
                            {{ selected.length }} selected
                        </span>
                        <Separator
                            orientation="vertical"
                            class="data-[orientation=vertical]:h-5"
                        />
                        <Button
                            variant="ghost"
                            size="sm"
                            class="h-8"
                            :disabled="bulkArchiving"
                            @click="archiveSelected(selected)"
                        >
                            <Archive />
                            Archive
                        </Button>
                        <Button
                            variant="ghost"
                            size="sm"
                            class="h-8 text-destructive hover:bg-destructive/10 hover:text-destructive"
                            @click="requestBulkDelete(selected)"
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

            <!--
                Below `md` the same rows render as cards. Product-specific
                markup stays here rather than in DataTable, which knows nothing
                about products.
            -->
            <template #mobile-card="{ row, selected, toggle }">
                <ProductCard
                    :product="row"
                    :selected="selected"
                    @update:selected="toggle"
                    @open="goToProduct"
                    @view="goToProduct"
                    @edit="
                        (product) =>
                            router.visit(`${show(product.id).url}?edit=1`)
                    "
                    @duplicate="
                        (product) =>
                            router.visit(`${create().url}?from=${product.id}`)
                    "
                    @remove="requestDelete"
                />
            </template>

            <template #expanded="{ row }">
                <FormatBreakdown :product="row" />
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
    <BulkDeleteDialog
        v-model:open="bulkDeleteOpen"
        :ids="bulkDeleteIds"
        @deleted="bulkDeleted"
    />
</template>
