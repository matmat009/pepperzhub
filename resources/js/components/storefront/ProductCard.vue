<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { Eye, ShoppingCart } from '@lucide/vue';
import { computed } from 'vue';
import ProductThumb from '@/components/storefront/ProductThumb.vue';
import { useStorefrontCart } from '@/composables/useStorefrontCart';
import {
    formatPrice,
    LOW_STOCK_THRESHOLD,
    totalStock,
} from '@/pages/admin/products/all-products/types';
import type { Product } from '@/pages/admin/products/all-products/types';
import { show } from '@/routes/storefront/products';

const props = withDefaults(
    defineProps<{
        product: Product;
        /** Position in the grid — drives which well colour this card takes. */
        index?: number;
    }>(),
    { index: 0 },
);

/**
 * Alternating Serenity Blue / Rose Quartz well, by grid position rather than by
 * product id, so the rhythm survives filtering and re-sorting.
 *
 * Both class names are written out in full rather than composed, so Tailwind's
 * scanner can see them.
 */
const wellClass = computed(() =>
    props.index % 2 === 0 ? 'bg-sf-well-blue' : 'bg-sf-well-rose',
);

const { add } = useStorefrontCart();

const stock = computed(() => totalStock(props.product.variants));
const inStock = computed(() => stock.value > 0);

/**
 * Three states rather than two: a card showing "In stock" over two remaining
 * units reads as reassurance when it should read as urgency.
 */
const stockBadge = computed(() => {
    if (!inStock.value) {
        return {
            label: 'Out of stock',
            class: 'border-sf-line-strong bg-sf-surface text-sf-subtle',
        };
    }

    if (stock.value <= LOW_STOCK_THRESHOLD) {
        return {
            label: `Only ${stock.value} left`,
            class: 'border-sf-rose-line bg-sf-rose-tint text-sf-rose-deep',
        };
    }

    return {
        label: 'In stock',
        class: 'border-sf-primary/25 bg-sf-primary/8 text-sf-primary',
    };
});

/** The cheapest format is what the card's quick-add drops in. */
const defaultVariant = computed(
    () => [...props.product.variants].sort((a, b) => a.price - b.price)[0],
);

/** "5 mg vial" — the format the quick-add would put in the cart. */
const formatLine = computed(() => defaultVariant.value?.label ?? '');

/**
 * Split rather than reusing `priceRange` so "from" and the dash can sit back in
 * muted type while the figures carry the emphasis.
 */
const prices = computed(() => {
    const values = props.product.variants.map((variant) => variant.price);

    if (values.length === 0) {
        return null;
    }

    const low = Math.min(...values);
    const high = Math.max(...values);

    return {
        low: formatPrice(low),
        high: low === high ? null : formatPrice(high),
    };
});
</script>

<template>
    <div
        class="group relative flex flex-col overflow-hidden rounded-2xl border border-sf-line bg-white transition duration-300 ease-out hover:-translate-y-1 hover:border-sf-primary/30 hover:shadow-[0_18px_40px_-12px_rgba(30,35,60,0.22)]"
    >
        <Link
            :href="show(product.slug)"
            class="relative block aspect-square overflow-hidden"
            :class="wellClass"
            :aria-label="`View details for ${product.name}`"
        >
            <!--
                object-contain inside a fixed square, never cover: a portrait or
                panoramic source letterboxes onto the coloured well rather than
                being cropped to fill it.

                The padding is also what keeps the hover zoom honest — scaling
                110% expands into the inset rather than past the well's edge, so
                nothing is clipped in either state.
            -->
            <span
                class="block size-full p-6 transition duration-500 ease-out group-hover:blur-[3px] motion-safe:group-hover:scale-110"
            >
                <ProductThumb :product="product" icon-class="size-12" />
            </span>

            <!-- Scrim, so the white pill below keeps contrast over pale vials. -->
            <span
                aria-hidden="true"
                class="absolute inset-0 bg-sf-ink/15 opacity-0 transition-opacity duration-300 ease-out group-hover:opacity-100"
            />

            <span
                aria-hidden="true"
                class="pointer-events-none absolute inset-0 flex items-center justify-center"
            >
                <!--
                    The soft Rose Quartz ground (#F7CAC9), not the deepened
                    --sf-rose. Ink type rather than white: white on this tint is
                    1.47:1.
                -->
                <span
                    class="inline-flex translate-y-2 items-center gap-2 rounded-full bg-sf-rose-quartz px-5 py-2.5 font-display text-sm font-semibold text-sf-ink opacity-0 shadow-[0_8px_24px_rgba(30,35,60,0.18)] transition duration-300 ease-out group-hover:translate-y-0 group-hover:opacity-100"
                >
                    <Eye class="size-4" />
                    View Details
                </span>
            </span>
        </Link>

        <!--
            Siblings of the link rather than children: nesting them would put
            interactive-looking chrome inside the anchor. pointer-events-none
            keeps the corners clickable through to the image link.
        -->
        <span
            v-if="product.category"
            class="pointer-events-none absolute top-4 left-4 z-2 rounded-full border border-white/70 bg-white/90 px-3 py-1.5 text-[11px] font-semibold tracking-[0.14em] text-sf-ink uppercase shadow-[0_2px_8px_rgba(30,35,60,0.08)] backdrop-blur-sm"
        >
            {{ product.category }}
        </span>

        <span
            class="pointer-events-none absolute top-4 right-4 z-2 rounded-full border px-3 py-1.5 text-xs font-semibold whitespace-nowrap backdrop-blur-sm"
            :class="stockBadge.class"
        >
            {{ stockBadge.label }}
        </span>

        <div class="flex flex-1 flex-col p-5">
            <Link
                :href="show(product.slug)"
                class="font-display text-xl font-semibold tracking-[-0.01em] text-sf-rose-deep transition-colors duration-200 ease-out hover:text-sf-primary"
            >
                {{ product.name }}
            </Link>

            <span v-if="formatLine" class="mt-1 text-[13px] text-sf-subtle">
                {{ formatLine }}
            </span>

            <p
                v-if="product.short_description.trim()"
                class="mt-2 line-clamp-2 text-sm leading-[1.55] text-sf-muted"
            >
                {{ product.short_description }}
            </p>

            <div v-if="prices" class="mt-auto flex items-baseline gap-1.5 pt-4">
                <span v-if="prices.high" class="text-sm text-sf-subtle italic">
                    from
                </span>
                <span
                    class="font-display text-[22px] font-semibold text-sf-rose-deep"
                >
                    {{ prices.low }}
                </span>
                <template v-if="prices.high">
                    <span class="text-sm text-sf-subtle">–</span>
                    <span class="text-[15px] font-medium text-sf-subtle">
                        {{ prices.high }}
                    </span>
                </template>
            </div>

            <button
                type="button"
                :disabled="!inStock || !defaultVariant"
                :aria-label="
                    inStock ? `Add ${product.name} to cart` : 'Out of stock'
                "
                class="mt-4 inline-flex w-full items-center justify-center gap-2.5 rounded-xl bg-sf-primary px-5 py-3.5 font-display text-[15px] font-semibold text-white shadow-[0_6px_16px_-6px_rgba(50,70,160,0.55)] transition duration-200 ease-out hover:bg-sf-primary-deep hover:shadow-[0_10px_22px_-8px_rgba(50,70,160,0.7)] focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-sf-primary active:translate-y-px disabled:cursor-not-allowed disabled:bg-sf-line-strong disabled:text-sf-subtle disabled:shadow-none disabled:hover:bg-sf-line-strong"
                @click="defaultVariant && add(product, defaultVariant.id)"
            >
                <ShoppingCart class="size-[17px]" />
                {{ inStock ? 'Add to cart' : 'Out of stock' }}
            </button>
        </div>
    </div>
</template>
