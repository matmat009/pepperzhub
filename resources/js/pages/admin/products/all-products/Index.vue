<script setup lang="ts">
import type {
    ColumnFiltersState,
    RowSelectionState,
    Table,
} from '@tanstack/vue-table';
import { Head, Link, router } from '@inertiajs/vue3';
import {
    Archive,
    Check,
    ChevronRight,
    Columns3,
    Download,
    ListFilter,
    MoreHorizontal,
    Package,
    Plus,
    Search,
    Trash2,
    TriangleAlert,
    X,
} from '@lucide/vue';
import { computed, ref, watch } from 'vue';
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
import { Separator } from '@/components/ui/separator';
import type { Features } from '@/components/features';
import { useLowStockThreshold } from '@/composables/useLowStockThreshold';
import { bulkArchive, create, index, show } from '@/routes/admin/products';
import { index as inventory } from '@/routes/admin/products/inventory';
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

const lowStockThreshold = useLowStockThreshold();

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

const lowStockCount = computed(
    () =>
        props.products.filter((product) =>
            product.variants.some(
                (variant) => variant.stock <= lowStockThreshold.value,
            ),
        ).length,
);

const selectedStatuses = (table: ProductTable): ProductStatus[] =>
    (table.getColumn('status')?.getFilterValue() as ProductStatus[]) ?? [];

const toggleStatus = (table: ProductTable, status: ProductStatus) => {
    const current = selectedStatuses(table);
    const next = current.includes(status)
        ? current.filter((item) => item !== status)
        : [...current, status];

    table.getColumn('status')?.setFilterValue(next.length ? next : undefined);
};

const clearStatuses = (table: ProductTable) => {
    table.getColumn('status')?.setFilterValue(undefined);
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

/**
 * Filters live only in the TanStack instance, so a real reload remounts the
 * tree and drops them. sessionStorage rather than localStorage: the state dies
 * with the tab, which matches how filtering is actually used — narrow the list,
 * open a product, come back to the same view — without a filter set days ago
 * silently hiding half the catalog on a fresh visit.
 *
 * `columnFilters` is TanStack's own shape, so it round-trips as-is: no
 * translation through the toolbar's status/search/category helpers, and no
 * post-mount restore step. The saved value simply *is* the ref's initial value.
 */
const FILTERS_STORAGE_KEY = 'pepperzhub:products:filters';

const readStoredFilters = (): ColumnFiltersState => {
    try {
        const stored = sessionStorage.getItem(FILTERS_STORAGE_KEY);
        const parsed = stored ? JSON.parse(stored) : null;

        if (!Array.isArray(parsed)) {
            return [];
        }

        return (parsed as ColumnFiltersState).flatMap((filter) => {
            if (filter.id !== 'status') {
                return [filter];
            }

            // Migrate the previous single-select value without discarding the
            // rest of the filters saved in this tab.
            const values = Array.isArray(filter.value)
                ? filter.value
                : [filter.value];
            const statuses = values.filter((value): value is ProductStatus =>
                PRODUCT_STATUSES.includes(value as ProductStatus),
            );

            return statuses.length ? [{ id: filter.id, value: statuses }] : [];
        });
    } catch {
        // Unreadable or corrupt — start unfiltered rather than failing setup.
        return [];
    }
};

const columnFilters = ref<ColumnFiltersState>(readStoredFilters());

watch(
    columnFilters,
    (value) => {
        try {
            sessionStorage.setItem(FILTERS_STORAGE_KEY, JSON.stringify(value));
        } catch {
            // Storage unavailable (private mode, quota) — filtering still works
            // for this page view, it just will not survive the next reload.
        }
    },
    { deep: true },
);
</script>

<template>
    <Head title="Products" />

    <div
        :class="[
            'flex flex-1 flex-col gap-5 px-4 py-6 lg:px-6',
            hasSelection && 'pb-24 md:pb-6',
        ]"
    >
        <header
            class="flex flex-col gap-4 md:flex-row md:flex-wrap md:items-start md:justify-between"
        >
            <div class="space-y-1">
                <h1 class="text-2xl font-semibold tracking-tight md:text-3xl">
                    Products
                </h1>
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

        <div class="grid gap-4 md:grid-cols-2">
            <section
                aria-labelledby="total-products-label"
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
                        <Package aria-hidden="true" class="size-6" />
                    </div>
                    <div class="min-w-0">
                        <p
                            id="total-products-label"
                            class="text-sm font-medium text-sf-primary-soft dark:text-sf-serenity-blue"
                        >
                            Total Products
                        </p>
                        <p class="mt-1 flex flex-wrap items-baseline gap-2">
                            <span
                                class="text-4xl leading-none font-semibold tracking-tight text-sf-primary tabular-nums dark:text-sf-serenity-blue"
                            >
                                {{ products.length }}
                            </span>
                            <span
                                class="text-sm text-sf-primary-soft/80 dark:text-sf-serenity-blue/80"
                            >
                                {{
                                    products.length === 1
                                        ? 'product'
                                        : 'products'
                                }}
                            </span>
                        </p>
                    </div>
                </div>
            </section>

            <Link
                :href="inventory({ query: { low_stock: 1 } })"
                aria-labelledby="low-stock-products-label"
                class="group relative isolate min-h-28 overflow-hidden rounded-xl border border-sf-rose-line bg-sf-rose-tint/55 px-5 py-4 shadow-xs transition-colors hover:bg-sf-rose-tint/75 focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 focus-visible:outline-none dark:bg-sf-rose-deep/10 dark:hover:bg-sf-rose-deep/15"
            >
                <span
                    aria-hidden="true"
                    class="absolute -right-8 -bottom-16 -z-10 size-44 rounded-full bg-sf-rose-quartz/20 dark:bg-sf-rose-deep/5"
                />
                <div class="flex h-full items-center gap-4">
                    <div
                        class="flex size-13 shrink-0 items-center justify-center rounded-full border border-white/90 bg-white/45 text-sf-rose-deep shadow-xs dark:border-sf-rose-line/40 dark:bg-background/25 dark:text-sf-rose-mid"
                    >
                        <TriangleAlert aria-hidden="true" class="size-6" />
                    </div>
                    <div class="min-w-0">
                        <p
                            id="low-stock-products-label"
                            class="text-sm font-medium text-sf-rose-deep dark:text-sf-rose-mid"
                        >
                            Low Stock
                        </p>
                        <p class="mt-1 flex flex-wrap items-baseline gap-2">
                            <span
                                class="text-4xl leading-none font-semibold tracking-tight text-sf-rose-deep tabular-nums dark:text-sf-rose-mid"
                            >
                                {{ lowStockCount }}
                            </span>
                            <span
                                class="text-sm text-sf-rose-deep/75 dark:text-sf-rose-mid/80"
                            >
                                {{
                                    lowStockCount === 1 ? 'product' : 'products'
                                }}
                            </span>
                        </p>
                    </div>
                    <span
                        class="ml-auto hidden items-center gap-1 rounded-lg border border-sf-rose-line bg-background/75 px-3 py-2 text-sm font-medium text-sf-rose-deep shadow-xs sm:flex dark:bg-background/40 dark:text-sf-rose-mid"
                    >
                        View inventory
                        <ChevronRight
                            aria-hidden="true"
                            class="size-4 transition-transform group-hover:translate-x-0.5"
                        />
                    </span>
                </div>
            </Link>
        </div>

        <DataTable
            v-model:row-selection="rowSelection"
            v-model:column-filters="columnFilters"
            :data="products"
            :columns="columns"
            :can-expand-row="canExpandRow"
            row-clickable
            empty-message="No products match these filters."
            class="gap-5 [&_[data-slot=table-body]_[data-slot=table-cell]:not(.p-0)]:py-3.5 [&_[data-slot=table-body]>[data-slot=table-row]]:border-sf-serenity-blue/25 [&_[data-slot=table-container]]:ring-1 [&_[data-slot=table-container]]:ring-sf-serenity-blue/35 [&_[data-slot=table-container]]:ring-inset [&_[data-slot=table-head]]:h-12 [&_[data-slot=table-head]]:text-sf-primary-soft [&_[data-slot=table-header]]:bg-sf-serenity-blue/[0.08] [&_[data-slot=table-header]>[data-slot=table-row]]:border-sf-serenity-blue/30 [&_[data-slot=table-row][data-state=selected]]:bg-primary/5"
            @row-click="goToProduct"
        >
            <template #toolbar="{ table }">
                <div
                    class="flex flex-col gap-3 xl:flex-row xl:items-center xl:justify-between"
                >
                    <DropdownMenu>
                        <DropdownMenuTrigger as-child>
                            <Button
                                variant="outline"
                                class="w-full shrink-0 md:w-auto"
                            >
                                <ListFilter />
                                Status
                                <span
                                    v-if="
                                        selectedStatuses(table as ProductTable)
                                            .length
                                    "
                                    class="ml-1 rounded bg-primary/10 px-1.5 text-xs text-primary"
                                >
                                    {{
                                        selectedStatuses(table as ProductTable)
                                            .length
                                    }}
                                </span>
                            </Button>
                        </DropdownMenuTrigger>
                        <DropdownMenuContent align="start" class="w-52">
                            <DropdownMenuLabel>
                                Filter by status
                            </DropdownMenuLabel>
                            <DropdownMenuSeparator />
                            <DropdownMenuCheckboxItem
                                :model-value="
                                    !selectedStatuses(table as ProductTable)
                                        .length
                                "
                                @select="
                                    (event: Event) => event.preventDefault()
                                "
                                @update:model-value="
                                    () => clearStatuses(table as ProductTable)
                                "
                            >
                                <span
                                    class="flex flex-1 items-center justify-between gap-3"
                                >
                                    All
                                    <span class="text-xs text-muted-foreground">
                                        {{ statusCounts.all }}
                                    </span>
                                </span>
                            </DropdownMenuCheckboxItem>
                            <DropdownMenuSeparator />
                            <DropdownMenuCheckboxItem
                                v-for="status in PRODUCT_STATUSES"
                                :key="status"
                                :model-value="
                                    selectedStatuses(
                                        table as ProductTable,
                                    ).includes(status)
                                "
                                @select="
                                    (event: Event) => event.preventDefault()
                                "
                                @update:model-value="
                                    () =>
                                        toggleStatus(
                                            table as ProductTable,
                                            status,
                                        )
                                "
                            >
                                <span
                                    class="flex flex-1 items-center justify-between gap-3"
                                >
                                    {{ status }}
                                    <span class="text-xs text-muted-foreground">
                                        {{ statusCounts[status] }}
                                    </span>
                                </span>
                            </DropdownMenuCheckboxItem>
                        </DropdownMenuContent>
                    </DropdownMenu>

                    <div
                        class="flex min-w-0 flex-wrap items-center gap-2 xl:flex-1 xl:justify-end"
                    >
                        <div
                            class="relative min-w-48 flex-1 sm:max-w-md xl:max-w-lg"
                        >
                            <Search
                                class="pointer-events-none absolute top-1/2 left-2.5 size-4 -translate-y-1/2 text-muted-foreground"
                            />
                            <Input
                                :model-value="
                                    searchValue(table as ProductTable)
                                "
                                placeholder="Search products..."
                                class="h-10 rounded-lg pl-9 shadow-none"
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
                                    class="hidden md:inline-flex"
                                >
                                    <ListFilter />
                                    Category
                                    <span
                                        v-if="
                                            selectedCategories(
                                                table as ProductTable,
                                            ).length
                                        "
                                        class="ml-1 rounded bg-primary/10 px-1.5 text-xs text-primary"
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
                                    class="hidden md:inline-flex"
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
                                        (value) =>
                                            column.toggleVisibility(!!value)
                                    "
                                >
                                    {{ column.id.replace('_', ' ') }}
                                </DropdownMenuCheckboxItem>
                            </DropdownMenuContent>
                        </DropdownMenu>

                        <Button variant="outline" class="hidden md:inline-flex">
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
                                    class="shrink-0 md:hidden"
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
                </div>
            </template>

            <!--
                Selecting a row is occasional, and the bar would otherwise pop
                into the layout above the table — this is the "prevent a jarring
                change" case. Exit is faster than enter: the user has already
                decided by then.

                The bar is pinned to the bottom of the viewport below `md`,
                where the top of a phone screen is the hardest place to reach;
                from `md` up it sits in flow above the table as before. The
                enter offset follows it, so the bar always arrives from the edge
                it is anchored to rather than sliding down out of the bottom of
                the screen.

                This note sits *outside* `#bulk` on purpose. With nothing
                selected the slot renders only the Transition's `v-if`
                placeholder, and Vue's SSR drops a slot whose whole output is
                comments (`ensureValidVNode`), while the client still renders
                one node for it. A comment in here would make that two nodes
                against the server's zero, and the hydration desync shifts every
                later sibling — the mobile-card wrapper then adopts the
                pagination wrapper's classes and its cards show up at desktop
                widths. Keep slot content free of leading comments.
            -->
            <template #bulk="{ table, selected }">
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
                            variant="outline"
                            size="sm"
                            :loading="bulkArchiving"
                            @click="archiveSelected(selected)"
                        >
                            <Archive />
                            Archive
                        </Button>
                        <Button
                            variant="destructive"
                            size="sm"
                            @click="requestBulkDelete(selected)"
                        >
                            <Trash2 />
                            Delete
                        </Button>
                        <Button
                            variant="ghost"
                            size="icon-sm"
                            class="ml-auto"
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
