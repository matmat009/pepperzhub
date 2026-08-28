<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import {
    Check,
    ChevronLeft,
    ChevronRight,
    Minus,
    Plus,
    Share2,
} from '@lucide/vue';
import { computed, ref, watch } from 'vue';
import ProductCard from '@/components/storefront/ProductCard.vue';
import ProductThumb from '@/components/storefront/ProductThumb.vue';
import { useStorefrontCart } from '@/composables/useStorefrontCart';
import { formatPrice } from '@/pages/admin/products/all-products/types';
import type { Product } from '@/pages/admin/products/all-products/types';
import { home } from '@/routes';
import { checkout } from '@/routes/storefront';
import { index as catalog } from '@/routes/storefront/products';

const props = defineProps<{
    product: Product;
    related: Product[];
}>();

const { add } = useStorefrontCart();

const imageIndex = ref(0);
const quantity = ref(1);
const descExpanded = ref(false);
const shared = ref(false);

/** Cheapest format preselected, matching the card's quick-add. */
const selectedVariantId = ref(
    [...props.product.variants].sort((a, b) => a.price - b.price)[0]?.id ?? '',
);

const selectedVariant = computed(() =>
    props.product.variants.find(
        (variant) => variant.id === selectedVariantId.value,
    ),
);

/** Price and stock both follow the format selector, never the product. */
const price = computed(() =>
    selectedVariant.value ? formatPrice(selectedVariant.value.price) : '—',
);

const stock = computed(() => selectedVariant.value?.stock ?? 0);
const outOfStock = computed(() => stock.value < 1);

const stockLabel = computed(() => {
    if (outOfStock.value) {
        return 'Out of stock';
    }

    return stock.value <= 5 ? `Only ${stock.value} left in stock` : 'In stock';
});

const images = computed(() => props.product.images);

const currentImage = computed(() => images.value[imageIndex.value] ?? null);

const details = computed(() => [
    ...props.product.purity_entries,
    ...props.product.storage_instructions,
]);

const longDescription = computed(
    () => props.product.full_description || props.product.short_description,
);

const canExpand = computed(() => longDescription.value.length > 280);

const shownDescription = computed(() =>
    canExpand.value && !descExpanded.value
        ? `${longDescription.value.slice(0, 280).trimEnd()}…`
        : longDescription.value,
);

// A different format can carry less stock than the quantity already dialled in.
watch(selectedVariantId, () => {
    quantity.value = 1;
});

const step = (delta: number) => {
    const next = quantity.value + delta;
    quantity.value = Math.min(Math.max(next, 1), Math.max(stock.value, 1));
};

const cycleImage = (delta: number) => {
    const count = images.value.length;

    if (count < 2) {
        return;
    }

    imageIndex.value = (imageIndex.value + delta + count) % count;
};

const addToCart = () => {
    if (!selectedVariant.value || outOfStock.value) {
        return;
    }

    add(props.product, selectedVariant.value.id, quantity.value);
};

const buyNow = () => {
    addToCart();
    router.visit(checkout().url);
};

const share = async () => {
    try {
        await navigator.clipboard.writeText(window.location.href);
        shared.value = true;
        window.setTimeout(() => (shared.value = false), 2000);
    } catch {
        // Clipboard is unavailable (insecure context, or permission denied);
        // the address bar already shows the URL, so there is nothing to say.
    }
};
</script>

<template>
    <Head :title="product.name" />

    <div class="mx-auto w-full max-w-[1680px] px-5 pt-8 pb-24 sm:px-10">
        <div class="flex flex-wrap items-center gap-2 text-sm text-sf-subtle">
            <Link
                :href="home()"
                class="transition-colors duration-200 ease-out hover:text-sf-primary"
                >Home</Link
            >
            <span>›</span>
            <Link
                :href="catalog()"
                class="transition-colors duration-200 ease-out hover:text-sf-primary"
                >{{ product.category || 'Products' }}</Link
            >
            <span>›</span>
            <span class="text-sf-ink">{{ product.name }}</span>
        </div>

        <div class="mt-8 grid grid-cols-1 gap-12 lg:grid-cols-2">
            <!-- Sticky on desktop only; on mobile it scrolls with the page. -->
            <div class="lg:sticky lg:top-28 lg:self-start">
                <div
                    class="relative aspect-square overflow-hidden rounded-2xl border border-sf-line bg-sf-tint"
                >
                    <img
                        v-if="currentImage"
                        :src="currentImage.url"
                        :alt="product.name"
                        class="size-full object-contain"
                    />
                    <ProductThumb
                        v-else
                        :product="product"
                        icon-class="size-20"
                    />

                    <button
                        type="button"
                        aria-label="Copy link to this product"
                        class="absolute top-4 right-4 grid size-10 place-items-center rounded-full border border-sf-line bg-white/90 text-sf-text backdrop-blur transition-colors duration-200 ease-out hover:border-sf-primary hover:text-sf-primary"
                        @click="share"
                    >
                        <Share2 class="size-4" />
                    </button>
                    <span
                        v-if="shared"
                        role="status"
                        class="absolute top-16 right-4 rounded-full bg-sf-ink px-3 py-1 text-xs text-white"
                    >
                        Link copied
                    </span>

                    <template v-if="images.length > 1">
                        <button
                            type="button"
                            aria-label="Previous image"
                            class="absolute top-1/2 left-4 grid size-10 -translate-y-1/2 place-items-center rounded-full border border-sf-line bg-white/90 text-sf-text backdrop-blur transition-colors duration-200 ease-out hover:border-sf-primary hover:text-sf-primary"
                            @click="cycleImage(-1)"
                        >
                            <ChevronLeft class="size-4" />
                        </button>
                        <button
                            type="button"
                            aria-label="Next image"
                            class="absolute top-1/2 right-4 grid size-10 -translate-y-1/2 place-items-center rounded-full border border-sf-line bg-white/90 text-sf-text backdrop-blur transition-colors duration-200 ease-out hover:border-sf-primary hover:text-sf-primary"
                            @click="cycleImage(1)"
                        >
                            <ChevronRight class="size-4" />
                        </button>
                    </template>
                </div>

                <div v-if="images.length > 1" class="mt-4 flex flex-wrap gap-3">
                    <button
                        v-for="(image, index) in images"
                        :key="image.id"
                        type="button"
                        :aria-label="`Show image ${index + 1}`"
                        :aria-current="index === imageIndex"
                        class="size-20 overflow-hidden rounded-lg border bg-sf-tint transition-colors duration-200 ease-out"
                        :class="
                            index === imageIndex
                                ? 'border-sf-primary'
                                : 'border-sf-line hover:border-sf-primary/50'
                        "
                        @click="imageIndex = index"
                    >
                        <img
                            :src="image.url"
                            :alt="`${product.name} view ${index + 1}`"
                            class="size-full object-contain"
                        />
                    </button>
                </div>
            </div>

            <div class="min-w-0">
                <div
                    class="font-display text-sm font-medium tracking-[0.12em] text-sf-primary uppercase"
                >
                    {{ product.category }}
                </div>
                <h1
                    class="mt-3 font-display text-[42px] leading-[1.15] font-medium tracking-[-0.02em] text-sf-ink"
                >
                    {{ product.name }}
                </h1>

                <div
                    class="mt-4 font-display text-[32px] font-semibold text-sf-primary-soft"
                >
                    {{ price }}
                </div>

                <div class="mt-7 border-t border-sf-line pt-7">
                    <div class="font-display text-lg font-semibold text-sf-ink">
                        Description
                    </div>
                    <p class="mt-3 text-[15px] leading-[1.75] text-sf-muted">
                        {{ shownDescription }}
                        <button
                            v-if="canExpand"
                            type="button"
                            class="ml-1 font-semibold text-sf-primary hover:text-sf-primary-hover"
                            @click="descExpanded = !descExpanded"
                        >
                            {{ descExpanded ? 'Show less' : 'Read more' }}
                        </button>
                    </p>
                </div>

                <div v-if="product.variants.length" class="mt-7">
                    <div class="flex items-center gap-2">
                        <span class="text-[15px] text-sf-subtle">Format:</span>
                        <span class="font-display font-semibold text-sf-ink">
                            {{ selectedVariant?.label ?? '—' }}
                        </span>
                    </div>
                    <div class="mt-3 flex flex-wrap gap-2.5">
                        <button
                            v-for="variant in product.variants"
                            :key="variant.id"
                            type="button"
                            class="rounded-full border px-5 py-2.5 text-[15px] font-medium transition duration-200 ease-out focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-sf-primary"
                            :class="[
                                variant.id === selectedVariantId
                                    ? 'border-sf-primary bg-sf-primary font-semibold text-white'
                                    : 'border-sf-line-strong bg-white text-sf-text hover:border-sf-primary',
                                variant.stock < 1 ? 'opacity-50' : '',
                            ]"
                            @click="selectedVariantId = variant.id"
                        >
                            {{ variant.label }}
                        </button>
                    </div>
                </div>

                <div
                    v-if="
                        selectedVariant?.is_kit &&
                        selectedVariant.kit_inclusions.length
                    "
                    class="mt-6 rounded-xl border border-sf-line bg-sf-tint p-5"
                >
                    <div class="font-display font-semibold text-sf-ink">
                        Kit includes
                    </div>
                    <div class="mt-3 flex flex-col gap-2">
                        <div
                            v-for="item in selectedVariant.kit_inclusions"
                            :key="item"
                            class="flex items-center gap-2.5 text-[15px] text-sf-muted"
                        >
                            <Check class="size-4 shrink-0 text-sf-primary" />
                            {{ item }}
                        </div>
                    </div>
                </div>

                <div v-if="details.length" class="mt-7">
                    <div class="font-display text-lg font-semibold text-sf-ink">
                        Technical details
                    </div>
                    <dl class="mt-3 divide-y divide-sf-line">
                        <div
                            v-for="detail in details"
                            :key="detail.id"
                            class="flex items-baseline justify-between gap-6 py-3"
                        >
                            <dt class="text-[15px] text-sf-subtle">
                                {{ detail.label }}
                            </dt>
                            <dd
                                class="text-right text-[15px] font-medium text-sf-ink"
                            >
                                {{ detail.value }}
                            </dd>
                        </div>
                    </dl>
                </div>

                <div
                    class="mt-7 flex flex-wrap items-center justify-between gap-4"
                >
                    <span
                        class="text-[15px] font-medium"
                        :class="
                            outOfStock ? 'text-sf-rose-deep' : 'text-sf-success'
                        "
                    >
                        {{ stockLabel }}
                    </span>
                    <div
                        class="flex items-center gap-1 rounded-full border border-sf-line-strong p-1"
                    >
                        <button
                            type="button"
                            aria-label="Decrease quantity"
                            :disabled="quantity <= 1"
                            class="grid size-9 place-items-center rounded-full text-sf-text transition-colors duration-200 ease-out hover:bg-sf-tint hover:text-sf-primary disabled:opacity-40"
                            @click="step(-1)"
                        >
                            <Minus class="size-4" />
                        </button>
                        <span
                            class="w-8 text-center font-display font-semibold text-sf-ink"
                            aria-live="polite"
                        >
                            {{ quantity }}
                        </span>
                        <button
                            type="button"
                            aria-label="Increase quantity"
                            :disabled="quantity >= stock"
                            class="grid size-9 place-items-center rounded-full text-sf-text transition-colors duration-200 ease-out hover:bg-sf-tint hover:text-sf-primary disabled:opacity-40"
                            @click="step(1)"
                        >
                            <Plus class="size-4" />
                        </button>
                    </div>
                </div>

                <div class="mt-6 flex flex-wrap gap-3">
                    <button
                        type="button"
                        :disabled="outOfStock"
                        class="flex-1 rounded-full bg-sf-primary px-8 py-4 font-display text-base font-medium text-white transition-colors duration-200 ease-out hover:bg-sf-primary-deep focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-sf-primary disabled:cursor-not-allowed disabled:opacity-40"
                        @click="addToCart"
                    >
                        {{ outOfStock ? 'Out of stock' : 'Add to cart' }}
                    </button>
                    <button
                        type="button"
                        :disabled="outOfStock"
                        class="flex-1 rounded-full border-2 border-sf-primary bg-white px-8 py-4 font-display text-base font-medium text-sf-primary transition-colors duration-200 ease-out hover:bg-sf-tint focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-sf-primary disabled:cursor-not-allowed disabled:opacity-40"
                        @click="buyNow"
                    >
                        Buy now
                    </button>
                </div>
            </div>
        </div>

        <section v-if="related.length" class="mt-24">
            <h2
                class="font-display text-[32px] font-medium tracking-[-0.02em] text-sf-ink"
            >
                You may also like
            </h2>
            <div
                class="mt-8 grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4"
            >
                <ProductCard
                    v-for="item in related"
                    :key="item.id"
                    :product="item"
                />
            </div>
        </section>
    </div>
</template>
