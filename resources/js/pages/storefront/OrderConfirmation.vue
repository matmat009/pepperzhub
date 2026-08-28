<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { ArrowRight, Check, Clock, MessageCircle, Phone } from '@lucide/vue';
import { formatPrice } from '@/pages/admin/products/all-products/types';
import { index as catalog } from '@/routes/storefront/products';

/**
 * Dummy order. No order schema exists yet, so nothing here is looked up — the
 * numbers below are fixed sample data matching the design bundle.
 */
const order = {
    number: 'PZH-00248',
    customer: 'Juan Dela Cruz',
    phone: '0917 123 4567',
    social: 'fb.com/juandc',
    items: [
        { name: 'BPC-157', format: '5 mg vial', quantity: 2, unitPrice: 2400 },
        { name: 'TB-500', format: '5 mg vial', quantity: 1, unitPrice: 2800 },
    ],
    shipping: 150,
};

const subtotal = order.items.reduce(
    (sum, item) => sum + item.unitPrice * item.quantity,
    0,
);

const total = subtotal + order.shipping;

const steps = [
    {
        title: 'Order Placed',
        copy: 'We have your order and payment proof.',
        done: true,
    },
    {
        title: 'Payment Verification',
        copy: 'Our team is confirming your transfer.',
        done: false,
    },
    {
        title: 'Preparing for Shipment',
        copy: 'Packed and handed to the courier.',
        done: false,
    },
];
</script>

<template>
    <Head title="Order Placed" />

    <div class="mx-auto w-full max-w-[980px] px-5 pt-12 pb-24 sm:px-10">
        <div class="flex flex-col items-center text-center">
            <span
                class="grid size-21 place-items-center rounded-full border border-sf-primary/25 bg-sf-tint text-sf-primary"
            >
                <Clock class="size-9.5 stroke-[1.8]" />
            </span>
            <h1
                class="mt-6 font-display text-[40px] leading-[1.15] font-medium tracking-[-0.02em] text-balance text-sf-ink"
            >
                Order Placed — Pending Verification
            </h1>
            <p
                class="mt-4 max-w-[600px] text-[17px] leading-[1.7] text-balance text-sf-muted"
            >
                We've received your order and payment proof. We'll verify your
                payment and reach out via
                <span class="font-semibold text-sf-ink">{{
                    order.social
                }}</span>
                or
                <span class="font-semibold text-sf-ink">{{ order.phone }}</span>
                shortly.
            </p>

            <div
                class="mt-7 rounded-full border border-sf-line bg-sf-tint px-6 py-3 font-display text-lg font-semibold text-sf-ink"
            >
                Order {{ order.number }}
            </div>
        </div>

        <ol class="mt-14 grid grid-cols-1 gap-6 sm:grid-cols-3">
            <li
                v-for="(step, index) in steps"
                :key="step.title"
                class="flex flex-col items-center gap-3 rounded-2xl border p-6 text-center"
                :class="
                    step.done
                        ? 'border-sf-primary/30 bg-sf-tint'
                        : 'border-sf-line bg-white'
                "
            >
                <span
                    class="grid size-11 place-items-center rounded-full font-display font-semibold"
                    :class="
                        step.done
                            ? 'bg-sf-primary text-white'
                            : 'border border-sf-line-strong bg-white text-sf-subtle'
                    "
                >
                    <Check v-if="step.done" class="size-5" />
                    <template v-else>{{ index + 1 }}</template>
                </span>
                <span class="font-display font-semibold text-sf-ink">{{
                    step.title
                }}</span>
                <span class="text-sm leading-[1.6] text-sf-muted">{{
                    step.copy
                }}</span>
            </li>
        </ol>

        <div class="mt-12 rounded-2xl border border-sf-line bg-white p-7">
            <h2 class="font-display text-xl font-semibold text-sf-ink">
                Order Summary
            </h2>
            <div class="mt-5 flex flex-col divide-y divide-sf-line">
                <div
                    v-for="item in order.items"
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
            <div class="mt-5 flex flex-col gap-3 border-t border-sf-line pt-5">
                <div class="flex justify-between text-[15px] text-sf-muted">
                    <span>Subtotal</span>
                    <span class="font-medium text-sf-ink">{{
                        formatPrice(subtotal)
                    }}</span>
                </div>
                <div class="flex justify-between text-[15px] text-sf-muted">
                    <span>Shipping</span>
                    <span class="font-medium text-sf-ink">{{
                        formatPrice(order.shipping)
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

        <div class="mt-10 text-center">
            <h2 class="font-display text-xl font-semibold text-sf-ink">
                Notify Us
            </h2>
            <p class="mt-2 text-[15px] text-sf-muted italic">
                Send your payment screenshot to speed up verification.
            </p>
            <div class="mt-5 flex flex-wrap justify-center gap-3">
                <a
                    href="#"
                    class="inline-flex items-center gap-2.5 rounded-full border-2 border-sf-primary bg-white px-7 py-3.5 font-display font-medium text-sf-primary transition-colors duration-200 ease-out hover:bg-sf-tint"
                >
                    <MessageCircle class="size-4" />
                    Message on Facebook
                </a>
                <a
                    href="#"
                    class="inline-flex items-center gap-2.5 rounded-full border-2 border-sf-primary bg-white px-7 py-3.5 font-display font-medium text-sf-primary transition-colors duration-200 ease-out hover:bg-sf-tint"
                >
                    <Phone class="size-4" />
                    Message on WhatsApp
                </a>
            </div>
        </div>

        <div class="mt-12 flex justify-center">
            <Link
                :href="catalog()"
                class="inline-flex items-center gap-2.5 rounded-full bg-sf-primary px-9 py-4 font-display text-base font-medium text-white transition-colors duration-200 ease-out hover:bg-sf-primary-deep"
            >
                Continue shopping
                <ArrowRight class="size-4" />
            </Link>
        </div>
    </div>
</template>
