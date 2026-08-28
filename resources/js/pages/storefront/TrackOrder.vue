<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { Check, CircleAlert, Copy, Search, Truck } from '@lucide/vue';
import { computed, ref } from 'vue';
import { formatPrice } from '@/pages/admin/products/all-products/types';
import { home } from '@/routes';
import { index as catalog } from '@/routes/storefront/products';

/**
 * Dummy lookup. No order schema exists yet, so exactly one combination
 * resolves — PZH-00231 / 09171234567 — and everything else falls through to
 * the not-found state. Sample record matches the design bundle.
 */
const DEMO = {
    orderNo: 'PZH-00231',
    phone: '09171234567',
    items: [
        { name: 'BPC-157', format: '5 mg vial', quantity: 2, unitPrice: 2450 },
        {
            name: 'Ipamorelin',
            format: '10 mg vial',
            quantity: 1,
            unitPrice: 5100,
        },
    ],
    shipping: 150,
    shipName: 'J&T – Luzon & Visayas',
    courier: 'J&T Express',
    trackingNo: 'JT2260041188PH',
    customer: 'Juan Dela Cruz',
};

/** Five stages; the demo order sits at "Shipped" (index 3). */
const STAGE_LABELS = [
    'Order Placed',
    'Payment Verified',
    'Preparing Order',
    'Shipped',
    'Delivered',
];

const CURRENT_STAGE = 3;

const orderInput = ref('');
const phoneInput = ref('');
const found = ref(false);
const error = ref(false);

const digits = (value: string) => value.replace(/\D/g, '');

const normalizeOrderNo = (value: string) =>
    value
        .trim()
        .toUpperCase()
        .replace(/^#/, '')
        .replace(/^PZH-?/, '');

const lookup = () => {
    const matches =
        normalizeOrderNo(orderInput.value) === normalizeOrderNo(DEMO.orderNo) &&
        digits(phoneInput.value) === DEMO.phone;

    found.value = matches;
    error.value = !matches;
};

const reset = () => {
    orderInput.value = '';
    phoneInput.value = '';
    found.value = false;
    error.value = false;
};

const subtotal = DEMO.items.reduce(
    (sum, item) => sum + item.unitPrice * item.quantity,
    0,
);

const total = subtotal + DEMO.shipping;

const stages = computed(() =>
    STAGE_LABELS.map((label, index) => ({
        label,
        done: index <= CURRENT_STAGE,
        current: index === CURRENT_STAGE,
    })),
);

const copied = ref(false);

const copyTracking = async () => {
    try {
        await navigator.clipboard.writeText(DEMO.trackingNo);
        copied.value = true;
        window.setTimeout(() => (copied.value = false), 2000);
    } catch {
        // Clipboard unavailable; the number is on screen to copy by hand.
    }
};

const fieldClass =
    'w-full rounded-xl border border-sf-line-strong bg-white px-4 py-3 text-[15px] text-sf-ink outline-none transition-colors duration-200 ease-out focus:border-sf-primary';
</script>

<template>
    <Head title="Track Order" />

    <div class="mx-auto w-full max-w-[980px] px-5 pt-10 pb-24 sm:px-10">
        <div class="flex items-center gap-2 text-sm text-sf-subtle">
            <Link
                :href="home()"
                class="transition-colors duration-200 ease-out hover:text-sf-primary"
                >Home</Link
            >
            <span>/</span>
            <span class="text-sf-ink">Track Order</span>
        </div>

        <h1
            class="mt-5 font-display text-[34px] font-medium tracking-[-0.02em] text-sf-ink"
        >
            Track Your Order
        </h1>
        <p class="mt-3 text-[17px] leading-[1.7] text-sf-muted">
            Enter your order number and the phone number you checked out with.
        </p>

        <form
            v-if="!found"
            class="mt-8 rounded-2xl border border-sf-line bg-white p-7"
            @submit.prevent="lookup"
        >
            <div class="grid grid-cols-1 gap-5 sm:grid-cols-2">
                <label class="flex flex-col gap-2">
                    <span class="text-sm font-medium text-sf-text"
                        >Order Number</span
                    >
                    <input
                        v-model="orderInput"
                        :class="fieldClass"
                        placeholder="e.g. PZH-00231"
                    />
                </label>
                <label class="flex flex-col gap-2">
                    <span class="text-sm font-medium text-sf-text"
                        >Phone Number</span
                    >
                    <input
                        v-model="phoneInput"
                        :class="fieldClass"
                        placeholder="0917 123 4567"
                    />
                </label>
            </div>

            <button
                type="submit"
                class="mt-6 inline-flex items-center gap-2.5 rounded-full bg-sf-primary px-9 py-3.5 font-display text-base font-medium text-white transition-colors duration-200 ease-out hover:bg-sf-primary-deep focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-sf-primary"
            >
                <Search class="size-4" />
                Track order
            </button>

            <div
                v-if="error"
                role="alert"
                class="mt-6 flex items-start gap-3 rounded-xl border border-sf-rose-line bg-sf-rose-tint p-5"
            >
                <CircleAlert class="mt-0.5 size-5 shrink-0 text-sf-rose-deep" />
                <div>
                    <div class="font-display font-semibold text-sf-rose-deep">
                        No order found
                    </div>
                    <p class="mt-1 text-[15px] leading-[1.6] text-sf-muted">
                        We couldn't match that order number and phone number.
                        Double-check both, or reach us at
                        <a
                            href="mailto:support@pepperzhub.ph"
                            class="font-semibold text-sf-primary hover:text-sf-primary-hover"
                            >support@pepperzhub.ph</a
                        >.
                    </p>
                </div>
            </div>
        </form>

        <div v-else class="mt-8 flex flex-col gap-8">
            <div class="rounded-2xl border border-sf-line bg-white p-7">
                <div
                    class="flex flex-wrap items-baseline justify-between gap-3"
                >
                    <h2 class="font-display text-xl font-semibold text-sf-ink">
                        Order {{ DEMO.orderNo }}
                    </h2>
                    <span class="text-[15px] text-sf-muted">{{
                        DEMO.customer
                    }}</span>
                </div>

                <ol class="mt-8 flex flex-col gap-0">
                    <li
                        v-for="(stage, index) in stages"
                        :key="stage.label"
                        class="flex gap-4"
                    >
                        <div class="flex flex-col items-center">
                            <span
                                class="grid size-9 shrink-0 place-items-center rounded-full font-display text-sm font-semibold"
                                :class="
                                    stage.done
                                        ? 'bg-sf-primary text-white'
                                        : 'border border-sf-line-strong bg-white text-sf-subtle'
                                "
                            >
                                <Check v-if="stage.done" class="size-4" />
                                <template v-else>{{ index + 1 }}</template>
                            </span>
                            <span
                                v-if="index < stages.length - 1"
                                class="h-12 w-px"
                                :class="
                                    stages[index + 1].done
                                        ? 'bg-sf-primary'
                                        : 'bg-sf-line-strong'
                                "
                            />
                        </div>
                        <div class="pb-2">
                            <div
                                class="font-display font-semibold"
                                :class="
                                    stage.done
                                        ? 'text-sf-ink'
                                        : 'text-sf-subtle'
                                "
                            >
                                {{ stage.label }}
                            </div>
                            <div
                                v-if="stage.current"
                                class="mt-1 text-sm text-sf-primary"
                            >
                                Current status
                            </div>
                        </div>
                    </li>
                </ol>

                <div
                    class="mt-6 flex flex-wrap items-center justify-between gap-4 rounded-xl border border-sf-line bg-sf-tint p-5"
                >
                    <div class="flex items-center gap-3">
                        <Truck class="size-5 shrink-0 text-sf-primary" />
                        <div>
                            <div class="text-sm text-sf-subtle">
                                {{ DEMO.courier }} · {{ DEMO.shipName }}
                            </div>
                            <div class="font-display font-semibold text-sf-ink">
                                {{ DEMO.trackingNo }}
                            </div>
                        </div>
                    </div>
                    <button
                        type="button"
                        class="inline-flex items-center gap-2 rounded-full border border-sf-line-strong bg-white px-4 py-2 text-sm font-medium text-sf-text transition-colors duration-200 ease-out hover:border-sf-primary hover:text-sf-primary"
                        @click="copyTracking"
                    >
                        <Copy class="size-3.5" />
                        {{ copied ? 'Copied' : 'Copy' }}
                    </button>
                </div>
            </div>

            <div class="rounded-2xl border border-sf-line bg-white p-7">
                <h2 class="font-display text-xl font-semibold text-sf-ink">
                    Items
                </h2>
                <div class="mt-5 flex flex-col divide-y divide-sf-line">
                    <div
                        v-for="item in DEMO.items"
                        :key="item.name"
                        class="flex items-center justify-between gap-4 py-3.5"
                    >
                        <span>
                            <span class="block font-medium text-sf-ink">{{
                                item.name
                            }}</span>
                            <span class="block text-sm text-sf-subtle"
                                >{{ item.format }} × {{ item.quantity }}</span
                            >
                        </span>
                        <span class="font-medium text-sf-ink">{{
                            formatPrice(item.unitPrice * item.quantity)
                        }}</span>
                    </div>
                </div>
                <div
                    class="mt-5 flex flex-col gap-3 border-t border-sf-line pt-5"
                >
                    <div class="flex justify-between text-[15px] text-sf-muted">
                        <span>Subtotal</span>
                        <span class="font-medium text-sf-ink">{{
                            formatPrice(subtotal)
                        }}</span>
                    </div>
                    <div class="flex justify-between text-[15px] text-sf-muted">
                        <span>Shipping</span>
                        <span class="font-medium text-sf-ink">{{
                            formatPrice(DEMO.shipping)
                        }}</span>
                    </div>
                    <div
                        class="mt-2 flex items-baseline justify-between border-t border-sf-line-strong pt-4"
                    >
                        <span class="font-display font-semibold text-sf-ink"
                            >Total</span
                        >
                        <span
                            class="font-display text-2xl font-semibold text-sf-primary-soft"
                            >{{ formatPrice(total) }}</span
                        >
                    </div>
                </div>
            </div>

            <div class="flex flex-wrap gap-3">
                <button
                    type="button"
                    class="rounded-full border-2 border-sf-primary bg-white px-8 py-3.5 font-display font-medium text-sf-primary transition-colors duration-200 ease-out hover:bg-sf-tint"
                    @click="reset"
                >
                    Track another order
                </button>
                <Link
                    :href="catalog()"
                    class="rounded-full bg-sf-primary px-8 py-3.5 font-display font-medium text-white transition-colors duration-200 ease-out hover:bg-sf-primary-deep"
                >
                    Continue shopping
                </Link>
            </div>
        </div>
    </div>
</template>
