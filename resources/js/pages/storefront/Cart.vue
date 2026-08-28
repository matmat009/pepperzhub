<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { ArrowRight, FlaskConical, Minus, Plus, Trash2 } from '@lucide/vue';
import { useStorefrontCart } from '@/composables/useStorefrontCart';
import { formatPrice } from '@/pages/admin/products/all-products/types';
import { home } from '@/routes';
import { checkout } from '@/routes/storefront';
import { index as catalog, show } from '@/routes/storefront/products';

const { lines, count, subtotal, isEmpty, setQuantity, remove } =
    useStorefrontCart();
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

        <h1
            class="mt-5 font-display text-[34px] font-medium tracking-[-0.02em] text-sf-ink"
        >
            Your Cart
            <span v-if="!isEmpty" class="text-sf-subtle">({{ count }})</span>
        </h1>

        <div
            v-if="isEmpty"
            class="mt-10 flex flex-col items-center rounded-2xl border border-dashed border-sf-line-strong px-8 py-24 text-center"
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

        <div
            v-else
            class="mt-8 grid grid-cols-1 gap-10 lg:grid-cols-[1fr_380px]"
        >
            <div class="flex flex-col divide-y divide-sf-line">
                <div
                    v-for="line in lines"
                    :key="line.key"
                    class="flex flex-wrap items-center gap-5 py-6"
                >
                    <Link
                        :href="show(line.productSlug)"
                        class="grid size-24 shrink-0 place-items-center overflow-hidden rounded-xl border border-sf-line bg-sf-tint"
                    >
                        <img
                            v-if="line.imageUrl"
                            :src="line.imageUrl"
                            :alt="line.productName"
                            class="size-full object-contain"
                        />
                        <FlaskConical
                            v-else
                            class="size-8 text-sf-primary/35"
                        />
                    </Link>

                    <div class="min-w-[180px] flex-1">
                        <Link
                            :href="show(line.productSlug)"
                            class="font-display text-lg font-semibold text-sf-ink transition-colors duration-200 ease-out hover:text-sf-primary"
                        >
                            {{ line.productName }}
                        </Link>
                        <div class="mt-1 text-sm text-sf-subtle">
                            {{ line.variantLabel }}
                        </div>
                        <div class="mt-1 text-sm text-sf-muted">
                            {{ formatPrice(line.unitPrice) }} each
                        </div>
                    </div>

                    <div
                        class="flex items-center gap-1 rounded-full border border-sf-line-strong p-1"
                    >
                        <button
                            type="button"
                            aria-label="Decrease quantity"
                            class="grid size-8 place-items-center rounded-full text-sf-text transition-colors duration-200 ease-out hover:bg-sf-tint hover:text-sf-primary"
                            @click="setQuantity(line.key, line.quantity - 1)"
                        >
                            <Minus class="size-3.5" />
                        </button>
                        <span
                            class="w-7 text-center font-display font-semibold text-sf-ink"
                        >
                            {{ line.quantity }}
                        </span>
                        <button
                            type="button"
                            aria-label="Increase quantity"
                            class="grid size-8 place-items-center rounded-full text-sf-text transition-colors duration-200 ease-out hover:bg-sf-tint hover:text-sf-primary"
                            @click="setQuantity(line.key, line.quantity + 1)"
                        >
                            <Plus class="size-3.5" />
                        </button>
                    </div>

                    <div
                        class="w-28 text-right font-display text-lg font-semibold text-sf-primary-soft"
                    >
                        {{ formatPrice(line.unitPrice * line.quantity) }}
                    </div>

                    <button
                        type="button"
                        :aria-label="`Remove ${line.productName}`"
                        class="grid size-9 place-items-center rounded-full border border-sf-line-strong text-sf-subtle transition-colors duration-200 ease-out hover:border-sf-rose-deep hover:text-sf-rose-deep"
                        @click="remove(line.key)"
                    >
                        <Trash2 class="size-4" />
                    </button>
                </div>
            </div>

            <aside class="lg:sticky lg:top-28 lg:self-start">
                <div class="rounded-2xl border border-sf-line bg-sf-tint p-7">
                    <h2 class="font-display text-xl font-semibold text-sf-ink">
                        Order Summary
                    </h2>
                    <div class="mt-5 flex flex-col gap-3 text-[15px]">
                        <div class="flex justify-between text-sf-muted">
                            <span>Subtotal</span>
                            <span class="font-medium text-sf-ink">{{
                                formatPrice(subtotal)
                            }}</span>
                        </div>
                        <div class="flex justify-between text-sf-muted">
                            <span>Shipping</span>
                            <span class="italic">Calculated at checkout</span>
                        </div>
                    </div>
                    <div
                        class="mt-5 flex items-baseline justify-between border-t border-sf-line-strong pt-5"
                    >
                        <span class="font-display font-semibold text-sf-ink"
                            >Total</span
                        >
                        <span
                            class="font-display text-2xl font-semibold text-sf-primary-soft"
                        >
                            {{ formatPrice(subtotal) }}
                        </span>
                    </div>
                    <Link
                        :href="checkout()"
                        class="mt-6 flex items-center justify-center gap-2.5 rounded-full bg-sf-primary px-8 py-4 font-display text-base font-medium text-white transition-colors duration-200 ease-out hover:bg-sf-primary-deep"
                    >
                        Proceed to checkout
                        <ArrowRight class="size-4" />
                    </Link>
                    <Link
                        :href="catalog()"
                        class="mt-3 block text-center text-sm text-sf-muted transition-colors duration-200 ease-out hover:text-sf-primary"
                    >
                        Continue shopping
                    </Link>
                </div>
            </aside>
        </div>
    </div>
</template>
