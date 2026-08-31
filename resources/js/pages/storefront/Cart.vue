<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import {
    ArrowRight,
    FlaskConical,
    Lock,
    Minus,
    Plus,
    Trash2,
} from '@lucide/vue';
import { computed } from 'vue';
import { useStorefrontCart } from '@/composables/useStorefrontCart';
import type { CartLine } from '@/composables/useStorefrontCart';
import { formatPrice } from '@/pages/admin/products/all-products/types';
import { home } from '@/routes';
import { checkout } from '@/routes/storefront';
import { index as catalog, show } from '@/routes/storefront/products';

/**
 * Lines and subtotal are hydrated server-side from product_variants on every
 * visit, so a price change or a sell-out shows up without the client
 * recomputing anything.
 */
const props = defineProps<{
    lines: CartLine[];
    subtotal: number;
}>();

const { setQuantity, remove } = useStorefrontCart();

const isEmpty = computed(() => props.lines.length === 0);

const itemCount = computed(() =>
    props.lines.reduce((sum, line) => sum + line.quantity, 0),
);

/** Same alternation the product cards use, so a thumbnail keeps its colour. */
const wellClass = (index: number) =>
    index % 2 === 0 ? 'bg-sf-well-blue' : 'bg-sf-well-rose';
</script>

<template>
    <Head title="Cart" />

    <div class="mx-auto w-full max-w-[1680px] px-5 pt-8 pb-24 sm:px-10">
        <div class="flex items-center gap-2 text-sm text-sf-subtle">
            <Link
                :href="home()"
                class="transition-colors duration-200 ease-out hover:text-sf-primary"
                >Home</Link
            >
            <span>/</span>
            <span class="text-sf-ink">Cart</span>
        </div>

        <div class="mt-5 flex items-baseline gap-3">
            <h1
                class="font-display text-[34px] font-medium tracking-[-0.02em] text-sf-ink"
            >
                Cart
            </h1>
            <span v-if="!isEmpty" class="text-sm text-sf-subtle">
                {{ itemCount }} {{ itemCount === 1 ? 'item' : 'items' }}
            </span>
        </div>

        <div
            v-if="isEmpty"
            class="mt-8 flex flex-col items-center rounded-2xl border border-dashed border-sf-line-strong px-8 py-24 text-center"
        >
            <span
                class="grid size-20 place-items-center rounded-full bg-sf-tint text-sf-primary"
            >
                <FlaskConical class="size-9" />
            </span>
            <p class="mt-6 font-display text-2xl font-semibold text-sf-ink">
                Your cart is empty
            </p>
            <p
                class="mt-2 max-w-md text-[15px] leading-[1.7] text-sf-muted italic"
            >
                Browse the catalogue and add a peptide to get started.
            </p>
            <Link
                :href="catalog()"
                class="mt-8 inline-flex items-center gap-2.5 rounded-full bg-sf-primary px-9 py-3.5 font-display text-base font-medium text-white transition-colors duration-200 ease-out hover:bg-sf-primary-deep"
            >
                Browse peptides
                <ArrowRight class="size-4" />
            </Link>
        </div>

        <!--
            items-start is what lets the summary stick: a stretched grid item
            fills the row and leaves sticky no distance to travel.
        -->
        <div
            v-else
            class="mt-8 grid grid-cols-1 items-start gap-8 lg:grid-cols-[minmax(0,1fr)_360px]"
        >
            <div class="flex flex-col gap-4">
                <div
                    v-for="(line, i) in lines"
                    :key="line.variant_id"
                    class="rounded-2xl border border-sf-line bg-white p-4 transition-colors duration-200 ease-out hover:border-sf-line-strong sm:p-5"
                >
                    <div class="flex gap-4 sm:gap-5">
                        <Link
                            :href="show(line.product_slug)"
                            class="grid size-20 shrink-0 place-items-center overflow-hidden rounded-xl sm:size-24"
                            :class="wellClass(i)"
                        >
                            <img
                                v-if="line.image_url"
                                :src="line.image_url"
                                :alt="line.product_name"
                                class="size-full object-contain p-2"
                            />
                            <FlaskConical
                                v-else
                                class="size-7 text-sf-primary/35"
                            />
                        </Link>

                        <div class="flex min-w-0 flex-1 flex-col">
                            <div class="flex items-start justify-between gap-4">
                                <div class="min-w-0">
                                    <div
                                        v-if="line.product_category"
                                        class="text-[11px] font-semibold tracking-[0.14em] text-sf-subtle uppercase"
                                    >
                                        {{ line.product_category }}
                                    </div>
                                    <Link
                                        :href="show(line.product_slug)"
                                        class="mt-0.5 block truncate font-display text-lg font-semibold text-sf-ink transition-colors duration-200 ease-out hover:text-sf-primary"
                                    >
                                        {{ line.product_name }}
                                    </Link>
                                    <div class="mt-1 text-sm text-sf-muted">
                                        Format:
                                        <span class="text-sf-text">{{
                                            line.variant_label
                                        }}</span>
                                    </div>
                                </div>

                                <div class="shrink-0 text-right">
                                    <div
                                        class="font-display text-lg font-semibold text-sf-ink"
                                    >
                                        {{ formatPrice(line.line_total) }}
                                    </div>
                                    <div class="mt-0.5 text-xs text-sf-subtle">
                                        {{ formatPrice(line.unit_price) }} each
                                    </div>
                                </div>
                            </div>

                            <div
                                class="mt-4 flex flex-wrap items-center gap-x-5 gap-y-3"
                            >
                                <div
                                    class="flex items-center gap-1 rounded-full border border-sf-line-strong p-1"
                                >
                                    <button
                                        type="button"
                                        aria-label="Decrease quantity"
                                        class="grid size-7 place-items-center rounded-full text-sf-text transition-colors duration-200 ease-out hover:bg-sf-tint hover:text-sf-primary"
                                        @click="
                                            setQuantity(
                                                line.variant_id,
                                                line.quantity - 1,
                                            )
                                        "
                                    >
                                        <Minus class="size-3.5" />
                                    </button>
                                    <span
                                        class="w-7 text-center font-display text-sm font-semibold text-sf-ink"
                                        aria-live="polite"
                                    >
                                        {{ line.quantity }}
                                    </span>
                                    <button
                                        type="button"
                                        aria-label="Increase quantity"
                                        :disabled="line.quantity >= line.stock"
                                        class="grid size-7 place-items-center rounded-full text-sf-text transition-colors duration-200 ease-out hover:bg-sf-tint hover:text-sf-primary disabled:cursor-not-allowed disabled:opacity-40"
                                        @click="
                                            setQuantity(
                                                line.variant_id,
                                                line.quantity + 1,
                                            )
                                        "
                                    >
                                        <Plus class="size-3.5" />
                                    </button>
                                </div>

                                <button
                                    type="button"
                                    class="inline-flex items-center gap-1.5 text-sm text-sf-subtle transition-colors duration-200 ease-out hover:text-sf-rose-deep focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-sf-rose-deep"
                                    @click="remove(line.variant_id)"
                                >
                                    <Trash2 class="size-4" />
                                    Remove
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <aside class="lg:sticky lg:top-28">
                <div class="rounded-2xl border border-sf-line bg-white p-6">
                    <h2 class="font-display text-xl font-semibold text-sf-ink">
                        Summary
                    </h2>

                    <div class="mt-5 flex flex-col gap-3 text-[15px]">
                        <div class="flex justify-between text-sf-muted">
                            <span>Subtotal</span>
                            <span class="font-medium text-sf-ink">{{
                                formatPrice(subtotal)
                            }}</span>
                        </div>
                        <div class="flex justify-between text-sf-muted">
                            <span>Delivery</span>
                            <span class="italic">Calculated at checkout</span>
                        </div>
                    </div>

                    <div
                        class="mt-5 flex items-baseline justify-between border-t border-sf-line pt-5"
                    >
                        <span class="font-display font-semibold text-sf-ink"
                            >Total</span
                        >
                        <span
                            class="font-display text-xl font-semibold text-sf-ink"
                        >
                            {{ formatPrice(subtotal) }}
                        </span>
                    </div>

                    <Link
                        :href="checkout()"
                        class="mt-6 flex w-full items-center justify-center rounded-full bg-sf-primary px-6 py-3.5 font-display text-[15px] font-semibold text-white shadow-[0_6px_16px_-6px_rgba(50,70,160,0.55)] transition-colors duration-200 ease-out hover:bg-sf-primary-deep focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-sf-primary"
                    >
                        Checkout
                    </Link>

                    <Link
                        :href="catalog()"
                        class="mt-3 flex w-full items-center justify-center rounded-full border border-sf-line-strong bg-white px-6 py-3 font-display text-[15px] font-medium text-sf-text transition-colors duration-200 ease-out hover:border-sf-primary hover:text-sf-primary"
                    >
                        Continue Shopping
                    </Link>

                    <div
                        class="mt-4 flex items-center justify-center gap-1.5 text-xs text-sf-subtle"
                    >
                        <Lock class="size-3.5" />
                        Manual payment · Bank transfer or QR
                    </div>
                </div>
            </aside>
        </div>
    </div>
</template>
