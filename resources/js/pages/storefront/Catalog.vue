<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { ChevronDown, Search, SlidersHorizontal, X } from '@lucide/vue';
import { computed, ref } from 'vue';
import ProductCard from '@/components/storefront/ProductCard.vue';
import { totalStock } from '@/pages/admin/products/all-products/types';
import type { Product } from '@/pages/admin/products/all-products/types';
import { home } from '@/routes';

const props = defineProps<{
    products: Product[];
    categories: string[];
}>();

type Sort = 'featured' | 'price-asc' | 'price-desc' | 'name';

const search = ref('');
const activeCategory = ref('All');
const sort = ref<Sort>('featured');
const inStockOnly = ref(false);
const outOfStockOnly = ref(false);
const stockOpen = ref(true);
const drawerOpen = ref(false);

const categoryTabs = computed(() => ['All', ...props.categories]);

/** Cheapest format, the figure the price sort orders on. */
const lowestPrice = (product: Product) =>
    product.variants.length
        ? Math.min(...product.variants.map((variant) => variant.price))
        : Number.POSITIVE_INFINITY;

const filtered = computed(() => {
    const term = search.value.trim().toLowerCase();

    const matches = props.products.filter((product) => {
        if (
            activeCategory.value !== 'All' &&
            product.category !== activeCategory.value
        ) {
            return false;
        }

        if (
            term &&
            !`${product.name} ${product.short_description}`
                .toLowerCase()
                .includes(term)
        ) {
            return false;
        }

        // Both boxes ticked (or neither) means no stock constraint at all.
        if (inStockOnly.value !== outOfStockOnly.value) {
            const hasStock = totalStock(product.variants) > 0;

            return inStockOnly.value ? hasStock : !hasStock;
        }

        return true;
    });

    return [...matches].sort((a, b) => {
        switch (sort.value) {
            case 'price-asc':
                return lowestPrice(a) - lowestPrice(b);
            case 'price-desc':
                return lowestPrice(b) - lowestPrice(a);
            case 'name':
                return a.name.localeCompare(b.name);
            default:
                return Number(b.featured) - Number(a.featured);
        }
    });
});

const pickCategory = (category: string) => {
    activeCategory.value = category;
    drawerOpen.value = false;
};
</script>

<template>
    <Head title="Products" />

    <div class="mx-auto w-full max-w-[1680px] px-5 pt-8 pb-24 sm:px-10">
        <div class="flex items-center gap-2 text-sm text-sf-subtle">
            <Link
                :href="home()"
                class="transition-colors duration-200 ease-out hover:text-sf-primary"
                >Home</Link
            >
            <span>/</span>
            <span class="text-sf-ink">Products</span>
        </div>

        <div
            class="mt-5 flex flex-wrap items-center justify-between gap-4 border-b border-sf-line pb-6"
        >
            <h1
                class="font-display text-[34px] font-medium tracking-[-0.02em] text-sf-ink"
            >
                Products ({{ filtered.length }})
            </h1>

            <div class="flex flex-wrap items-center gap-3">
                <label class="relative">
                    <Search
                        class="pointer-events-none absolute top-1/2 left-4 size-4 -translate-y-1/2 text-sf-subtle"
                    />
                    <input
                        v-model="search"
                        type="search"
                        placeholder="Search peptides"
                        aria-label="Search peptides"
                        class="w-56 rounded-full border border-sf-line-strong py-2.5 pr-4 pl-10 text-sm text-sf-ink transition-colors duration-200 ease-out outline-none focus:border-sf-primary"
                    />
                </label>

                <button
                    type="button"
                    class="inline-flex items-center gap-2 rounded-full border border-sf-line-strong px-4 py-2.5 text-sm font-medium text-sf-text transition-colors duration-200 ease-out hover:text-sf-primary focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-sf-primary lg:hidden"
                    @click="drawerOpen = true"
                >
                    Filters
                    <SlidersHorizontal class="size-4" />
                </button>

                <label class="flex items-center gap-2 text-sm text-sf-subtle">
                    Sort By
                    <select
                        v-model="sort"
                        class="rounded-full border border-sf-line-strong px-4 py-2.5 text-sm text-sf-ink transition-colors duration-200 ease-out outline-none focus:border-sf-primary"
                    >
                        <option value="featured">Featured</option>
                        <option value="price-asc">Price: Low to High</option>
                        <option value="price-desc">Price: High to Low</option>
                        <option value="name">Name A–Z</option>
                    </select>
                </label>
            </div>
        </div>

        <div class="mt-8 flex gap-10">
            <!-- Static rail on desktop, slide-over drawer below lg. -->
            <div
                v-if="drawerOpen"
                class="fixed inset-0 z-70 bg-[rgba(20,22,35,0.45)] lg:hidden"
                @click="drawerOpen = false"
            />
            <aside
                class="shrink-0 lg:block lg:w-56"
                :class="
                    drawerOpen
                        ? 'fixed inset-y-0 left-0 z-80 w-72 overflow-y-auto bg-white p-6 shadow-2xl'
                        : 'hidden'
                "
            >
                <div
                    v-if="drawerOpen"
                    class="mb-5 flex items-center justify-between lg:hidden"
                >
                    <span class="font-display text-lg font-semibold text-sf-ink"
                        >Filters</span
                    >
                    <button
                        type="button"
                        aria-label="Close filters"
                        class="grid size-8 place-items-center rounded-full border border-sf-line-strong text-sf-text hover:border-sf-primary hover:text-sf-primary"
                        @click="drawerOpen = false"
                    >
                        <X class="size-4" />
                    </button>
                </div>

                <div class="flex flex-col items-start gap-3">
                    <button
                        v-for="category in categoryTabs"
                        :key="category"
                        type="button"
                        class="text-left text-[15px] transition-colors duration-200 ease-out hover:text-sf-primary focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-sf-primary"
                        :class="
                            activeCategory === category
                                ? 'font-semibold text-sf-primary'
                                : 'text-sf-text'
                        "
                        @click="pickCategory(category)"
                    >
                        {{ category }}
                    </button>
                </div>

                <div class="mt-8 border-t border-sf-line pt-6">
                    <button
                        type="button"
                        class="flex w-full items-center justify-between font-display text-[15px] font-semibold text-sf-ink"
                        :aria-expanded="stockOpen"
                        @click="stockOpen = !stockOpen"
                    >
                        Stock Status
                        <ChevronDown
                            class="size-4 transition-transform duration-200 ease-out"
                            :class="stockOpen ? 'rotate-180' : ''"
                        />
                    </button>
                    <div v-if="stockOpen" class="mt-4 flex flex-col gap-3">
                        <label
                            class="flex items-center gap-2.5 text-[15px] text-sf-text"
                        >
                            <input
                                v-model="inStockOnly"
                                type="checkbox"
                                class="size-4 accent-sf-primary"
                            />
                            In Stock
                        </label>
                        <label
                            class="flex items-center gap-2.5 text-[15px] text-sf-text"
                        >
                            <input
                                v-model="outOfStockOnly"
                                type="checkbox"
                                class="size-4 accent-sf-primary"
                            />
                            Out of Stock
                        </label>
                    </div>
                </div>
            </aside>

            <div class="min-w-0 flex-1">
                <div
                    v-if="filtered.length"
                    class="grid grid-cols-1 gap-6 sm:grid-cols-2 xl:grid-cols-3 2xl:grid-cols-4"
                >
                    <ProductCard
                        v-for="product in filtered"
                        :key="product.id"
                        :product="product"
                    />
                </div>
                <div
                    v-else
                    class="rounded-2xl border border-dashed border-sf-line-strong px-8 py-20 text-center"
                >
                    <p class="font-display text-xl font-semibold text-sf-ink">
                        No products match those filters.
                    </p>
                    <p class="mt-2 text-[15px] text-sf-muted italic">
                        Try clearing the search or picking a different category.
                    </p>
                </div>
            </div>
        </div>
    </div>
</template>
