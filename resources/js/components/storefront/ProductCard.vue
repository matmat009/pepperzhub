<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { ShoppingCart } from '@lucide/vue';
import { computed } from 'vue';
import ProductThumb from '@/components/storefront/ProductThumb.vue';
import { useStorefrontCart } from '@/composables/useStorefrontCart';
import {
    priceRange,
    totalStock,
} from '@/pages/admin/products/all-products/types';
import type { Product } from '@/pages/admin/products/all-products/types';
import { show } from '@/routes/storefront/products';

const props = defineProps<{ product: Product }>();

const { add } = useStorefrontCart();

const inStock = computed(() => totalStock(props.product.variants) > 0);

/** The cheapest format is what the card's quick-add drops in. */
const defaultVariant = computed(
    () => [...props.product.variants].sort((a, b) => a.price - b.price)[0],
);

/** "5 mg vial · Peptides" — the format line under the name. */
const formatLine = computed(() => {
    const label = defaultVariant.value?.label;

    return [label, props.product.category].filter(Boolean).join(' · ');
});
</script>

<template>
    <div
        class="group relative flex flex-col gap-1.5 rounded-xl border border-sf-line bg-white p-3.5 pb-5 transition duration-200 ease-out hover:-translate-y-1.5 hover:border-sf-primary/30 hover:shadow-[0_14px_34px_rgba(30,35,60,0.1)]"
    >
        <span
            v-if="product.featured"
            class="absolute top-6 left-6 z-2 rounded-full border border-sf-rose-line bg-sf-rose-tint px-3 py-1 text-xs font-semibold whitespace-nowrap text-sf-rose-deep"
        >
            Best Seller
        </span>

        <Link
            :href="show(product.slug)"
            class="block aspect-square overflow-hidden rounded-lg bg-sf-tint"
        >
            <ProductThumb :product="product" icon-class="size-10" />
        </Link>

        <Link
            :href="show(product.slug)"
            class="mt-3 px-1.5 font-display text-lg font-semibold text-sf-ink transition-colors duration-200 ease-out hover:text-sf-primary"
        >
            {{ product.name }}
        </Link>
        <span class="px-1.5 text-[13px] text-sf-subtle">{{ formatLine }}</span>

        <div class="mt-2.5 flex items-center justify-between gap-2 px-1.5">
            <span
                class="font-display text-[19px] font-semibold text-sf-primary-soft"
            >
                {{ priceRange(product.variants) }}
            </span>
            <button
                type="button"
                :disabled="!inStock || !defaultVariant"
                :aria-label="
                    inStock ? `Add ${product.name} to cart` : 'Out of stock'
                "
                class="grid size-10 shrink-0 place-items-center rounded-full border border-sf-line-strong bg-white text-sf-muted transition duration-200 ease-out hover:border-sf-primary hover:bg-sf-primary hover:text-white focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-sf-primary disabled:cursor-not-allowed disabled:opacity-40 disabled:hover:border-sf-line-strong disabled:hover:bg-white disabled:hover:text-sf-muted"
                @click="defaultVariant && add(product, defaultVariant.id)"
            >
                <ShoppingCart class="size-[17px]" />
            </button>
        </div>
    </div>
</template>
