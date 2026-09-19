<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { Eye, ShoppingCart } from '@lucide/vue';
import { computed } from 'vue';
import ProductThumb from '@/components/storefront/ProductThumb.vue';
import { useLowStockThreshold } from '@/composables/useLowStockThreshold';
import { useStorefrontCart } from '@/composables/useStorefrontCart';
import {
    formatPrice,
    totalStock,
} from '@/pages/admin/products/all-products/types';
import type { Product } from '@/pages/admin/products/all-products/types';
import { show } from '@/routes/storefront/products';

const props = withDefaults(
    defineProps<{
        product: Product;
        /** Position in the grid — drives which well colour this card takes. */
        index?: number;
        /** Catalog uses the approved compact treatment without changing shared cards. */
        variant?: 'default' | 'catalog';
    }>(),
    { index: 0, variant: 'default' },
);

const isCatalog = computed(() => props.variant === 'catalog');

/** Placeholder artwork sits inset in the well; real uploads fill it. */
const usesPlaceholderAsset = computed(() =>
    Boolean(
        props.product.images[0]?.url.toLowerCase().endsWith('/placeholder.svg'),
    ),
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

// From the server, so this badge and the admin's low-stock tile cannot drift.
const lowStockThreshold = useLowStockThreshold();

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

    if (stock.value <= lowStockThreshold.value) {
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

const formatCount = computed(() => props.product.variants.length);

/**
 * "3 options" — deliberately not the admin table's "3 formats".
 *
 * Same count off the same variants; only the word changes with the audience.
 * The operator manages formats, so `formatCount` above keeps the name the rest
 * of this codebase uses for them, while the customer is being told there is
 * more than one thing here to choose from.
 *
 * The singular branch is kept even though the gate below never reaches one: a
 * plural-only string would quietly become wrong for any later caller that does.
 */
const optionCountLabel = computed(
    () => `${formatCount.value} option${formatCount.value === 1 ? '' : 's'}`,
);

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
    <!--
        Hover is a 2px lift and a slightly heavier shadow, nothing more. The
        card keeps its footprint: nothing around it moves, and its own height
        never changes, so a grid cannot reflow under the pointer.
    -->
    <div
        class="group relative flex flex-col overflow-hidden border bg-white transition duration-sf-fast ease-sf hover:border-sf-primary/30 motion-safe:hover:-translate-y-0.5"
        :class="
            isCatalog
                ? 'h-full rounded-xl border-sf-line-strong font-sans shadow-[0_8px_24px_-18px_rgba(30,35,60,0.3)] hover:shadow-[0_14px_30px_-16px_rgba(30,35,60,0.28)]'
                : 'rounded-2xl border-sf-line hover:shadow-[0_18px_40px_-12px_rgba(30,35,60,0.22)]'
        "
    >
        <Link
            :href="show(product.slug)"
            class="relative block overflow-hidden"
            :class="[wellClass, isCatalog ? 'aspect-[4/3]' : 'aspect-square']"
            :aria-label="`View details for ${product.name}`"
        >
            <!--
                object-contain inside the fixed media panel, never cover: a
                portrait or panoramic source letterboxes onto the coloured well
                rather than being cropped to fill it.

                Compact cards give real uploads the full panel so their canvas
                grows without cropping; placeholder artwork keeps its inset.

                The hover scale is capped at 1.02 in every branch, matching the
                rest of the storefront. Under object-contain a larger scale only
                pushes the subject further into the well's padding anyway — it
                never revealed more of the image.
            -->
            <span
                class="block size-full transition duration-sf-fast ease-sf group-hover:blur-[3px]"
                :class="
                    isCatalog
                        ? usesPlaceholderAsset
                            ? 'p-4 motion-safe:group-hover:scale-[1.02] sm:p-5'
                            : 'p-0 motion-safe:group-hover:scale-[1.02]'
                        : 'p-6 motion-safe:group-hover:scale-[1.02]'
                "
            >
                <ProductThumb
                    :product="product"
                    icon-class="size-12"
                    :image-class="
                        isCatalog && !usesPlaceholderAsset
                            ? 'scale-[1.195]'
                            : undefined
                    "
                />
            </span>

            <!-- Scrim, so the white pill below keeps contrast over pale vials. -->
            <span
                aria-hidden="true"
                class="absolute inset-0 bg-sf-ink/15 opacity-0 transition-opacity duration-sf-fast ease-sf group-hover:opacity-100"
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
                    class="inline-flex translate-y-2 items-center gap-2 rounded-full bg-sf-rose-quartz px-5 py-2.5 text-sm font-semibold text-sf-ink opacity-0 shadow-[0_8px_24px_rgba(30,35,60,0.18)] transition duration-sf-fast ease-sf group-hover:translate-y-0 group-hover:opacity-100"
                    :class="isCatalog ? 'font-sans' : 'font-display'"
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

        <div
            class="flex flex-1 flex-col"
            :class="isCatalog ? 'p-4 sm:p-5' : 'p-5'"
        >
            <Link
                :href="show(product.slug)"
                class="font-semibold tracking-[-0.01em] text-sf-rose-deep transition-colors duration-sf-fast ease-sf hover:text-sf-primary"
                :class="
                    isCatalog
                        ? 'font-sans text-lg leading-[1.3]'
                        : 'font-display text-xl'
                "
            >
                {{ product.name }}
            </Link>

            <span
                v-if="formatLine"
                class="mt-1 flex flex-wrap items-center gap-x-2 gap-y-1 text-[13px] text-sf-subtle"
            >
                {{ formatLine }}

                <!--
                    A hint that there is more behind the card, not a feature of
                    it: quiet enough to lose to the price and the Add to cart
                    button, and absent entirely when the cheapest format above
                    is the only one there is.
                -->
                <span
                    v-if="formatCount > 1"
                    class="rounded-full border border-sf-line-strong bg-sf-tint px-2 py-0.5 text-[11px] font-medium whitespace-nowrap text-sf-subtle"
                >
                    {{ optionCountLabel }}
                </span>
            </span>

            <p
                v-if="product.short_description.trim()"
                class="line-clamp-2 text-sm text-sf-muted"
                :class="
                    isCatalog ? 'mt-1.5 leading-[1.45]' : 'mt-2 leading-[1.55]'
                "
            >
                {{ product.short_description }}
            </p>

            <div
                v-if="prices"
                class="mt-auto flex items-baseline gap-1.5"
                :class="isCatalog ? 'flex-wrap gap-y-0.5 pt-3' : 'pt-4'"
            >
                <span v-if="prices.high" class="text-sm text-sf-subtle italic">
                    from
                </span>
                <span
                    class="font-semibold"
                    :class="
                        isCatalog
                            ? 'font-sans text-xl text-sf-primary-deep'
                            : 'font-display text-[22px] text-sf-rose-deep'
                    "
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
                class="inline-flex w-full items-center justify-center gap-2.5 bg-sf-primary px-5 font-semibold text-white transition duration-sf-fast ease-sf hover:bg-sf-primary-deep focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-sf-primary active:translate-y-px disabled:cursor-not-allowed disabled:bg-sf-line-strong disabled:text-sf-subtle disabled:shadow-none disabled:hover:bg-sf-line-strong"
                :class="
                    isCatalog
                        ? 'mt-3 min-h-11 rounded-lg py-2.5 font-sans text-sm shadow-[0_5px_14px_-6px_rgba(50,70,160,0.45)] hover:shadow-[0_8px_18px_-7px_rgba(50,70,160,0.55)]'
                        : 'mt-4 rounded-xl py-3.5 font-display text-[15px] shadow-[0_6px_16px_-6px_rgba(50,70,160,0.55)] hover:shadow-[0_10px_22px_-8px_rgba(50,70,160,0.7)]'
                "
                @click="defaultVariant && add(product, defaultVariant.id)"
            >
                <ShoppingCart class="size-[17px]" />
                {{ inStock ? 'Add to cart' : 'Out of stock' }}
            </button>
        </div>
    </div>
</template>
