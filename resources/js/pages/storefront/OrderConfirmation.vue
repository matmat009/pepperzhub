<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { ArrowRight, Check, Clock } from '@lucide/vue';
import { computed } from 'vue';
import { useSiteSettings } from '@/composables/useSiteSettings';
import { vReveal } from '@/lib/scrollReveal';
import { formatPrice } from '@/pages/admin/products/all-products/types';
import { index as catalog } from '@/routes/storefront/products';
import { cancellationMessage, isCancelled, trackerSteps } from './orderTracker';
import type { OrderItemLine, OrderTracker } from './orderTracker';

/**
 * Reached by a random per-order token in the URL, so the page survives a
 * refresh and one order's link reveals nothing about another's. The tracker
 * stage is derived server-side by App\Support\OrderTracker.
 */
const props = defineProps<{
    order: {
        order_number: string;
        name: string;
        phone: string;
        social_handle: string;
        subtotal: number;
        shipping_fee: number;
        total: number;
        shipping_region_label: string;
        /** Resolved snapshot (shipped_via, else the checkout-time courier name) — not a live join. */
        courier: string | null;
        payment_method: string | null;
        items: OrderItemLine[];
    };
    tracker: OrderTracker;
}>();

// Only the first three stages are shown here; the full five live on Track Order.
const steps = computed(() => trackerSteps(props.tracker).slice(0, 3));

// The handle is optional at checkout, so the contact sentence has to drop both
// it and the "or" rather than read "reach out via  or 0917…".
const hasHandle = computed(() => props.order.social_handle.trim().length > 0);

const cancelled = computed(() => isCancelled(props.tracker));
const cancelledMessage = computed(() => cancellationMessage(props.tracker));

const settings = useSiteSettings();

/*
 * The "message us" panel below exists to shorten the wait on a verification
 * that has not happened yet, so it is gated on the payment rather than on the
 * order status: once the operator has confirmed the transfer there is nothing
 * left to hurry along, and a rejected or cancelled order is told to contact us
 * about a refund instead, which this panel's copy would contradict.
 */
const awaitingVerification = computed(
    () => !cancelled.value && props.tracker.payment_status === 'unverified',
);

/** What every channel pre-fills, so the operator gets the reference up front. */
const proofMessage = computed(
    () =>
        `Hi! I just placed Order #${props.order.order_number}, here's my payment proof`,
);

const proofSubject = computed(
    () => `Payment proof — Order #${props.order.order_number}`,
);

/**
 * Messenger addresses a page by username, not by profile URL, so the stored
 * link is reduced to the last segment of its path.
 *
 * Parsed rather than string-split so the query string and any trailing slash
 * fall away on their own, and so a bare domain — a valid URL, and one the
 * settings form accepts — yields an empty path and no button, rather than a
 * link to m.me/facebook.com.
 */
const facebookUsername = computed(() => {
    const stored = settings.value.facebook_url;

    if (!stored) {
        return null;
    }

    try {
        const segment =
            new URL(stored).pathname.replace(/\/+$/, '').split('/').pop() ?? '';

        return segment || null;
    } catch {
        return null;
    }
});

/**
 * wa.me wants full international format — no leading zero, no plus, no spaces.
 *
 * Converted here and only here: contact_phone stays in the local 09xx form the
 * operator types and the footer prints, so this must not write back.
 */
const whatsappNumber = computed(() => {
    const digits = (settings.value.contact_phone ?? '').replace(/\D/g, '');

    if (!digits) {
        return null;
    }

    return digits.startsWith('0') ? `63${digits.slice(1)}` : digits;
});

const facebookLink = computed(() =>
    facebookUsername.value
        ? `https://m.me/${facebookUsername.value}?text=${encodeURIComponent(proofMessage.value)}`
        : null,
);

const whatsappLink = computed(() =>
    whatsappNumber.value
        ? `https://wa.me/${whatsappNumber.value}?text=${encodeURIComponent(proofMessage.value)}`
        : null,
);

/*
 * Gmail's own compose window rather than a mailto:, which on a phone opens
 * whichever client happens to be the default — often none at all.
 */
const gmailLink = computed(() =>
    settings.value.contact_email
        ? `https://mail.google.com/mail/?view=cm&fs=1&to=${encodeURIComponent(settings.value.contact_email)}&su=${encodeURIComponent(proofSubject.value)}&body=${encodeURIComponent(proofMessage.value)}`
        : null,
);

/** No heading over an empty row — the operator may have set none of the three. */
const hasContactChannel = computed(
    () =>
        Boolean(facebookLink.value) ||
        Boolean(whatsappLink.value) ||
        Boolean(gmailLink.value),
);
</script>

<template>
    <Head title="Order Placed" />

    <div class="mx-auto w-full max-w-[980px] px-5 pt-12 pb-24 sm:px-10">
        <!--
            Status icon, then the headline, then the sentence, then the order
            number — the order someone reads them in, 50ms apart, and all of it
            settled inside half a second. Nothing here is gated on scrolling:
            this is the whole reason the customer is on the page.
        -->
        <div class="flex flex-col items-center text-center">
            <span
                class="sf-enter grid size-21 place-items-center rounded-full border border-sf-primary/25 bg-sf-tint text-sf-primary"
            >
                <Clock class="size-9.5 stroke-[1.8]" />
            </span>
            <h1
                class="sf-enter sf-delay-1 mt-6 font-display text-[34px] leading-[1.15] font-medium tracking-[-0.02em] text-balance text-sf-ink sm:text-[40px]"
            >
                {{
                    cancelled
                        ? 'Order Cancelled'
                        : 'Order Placed — Pending Verification'
                }}
            </h1>
            <p
                v-if="cancelled"
                class="sf-enter sf-delay-2 mt-4 max-w-[600px] text-[17px] leading-[1.7] text-balance text-sf-rose-deep"
            >
                {{ cancelledMessage }}
            </p>
            <p
                v-else
                class="sf-enter sf-delay-2 mt-4 max-w-[600px] text-[17px] leading-[1.7] text-balance text-sf-muted"
            >
                We've received your order and payment proof. We'll verify your
                payment and reach out via
                <span
                    v-if="hasHandle"
                    class="font-semibold break-all text-sf-ink"
                    >{{ order.social_handle }}</span
                >
                {{ hasHandle ? 'or' : '' }}
                <span class="font-semibold break-all text-sf-ink">{{
                    order.phone
                }}</span>
                shortly.
            </p>

            <div
                class="sf-enter sf-delay-3 mt-7 max-w-full rounded-full border border-sf-line bg-sf-tint px-6 py-3 font-display text-base font-semibold break-all text-sf-ink sm:text-lg"
            >
                Order {{ order.order_number }}
            </div>
        </div>

        <ol
            v-if="!cancelled"
            v-reveal="'stagger'"
            class="mt-14 grid grid-cols-1 gap-6 sm:grid-cols-3"
        >
            <li
                v-for="(step, index) in steps"
                :key="step.label"
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
                    step.label
                }}</span>
                <span
                    v-if="step.current"
                    class="text-sm leading-[1.6] text-sf-primary"
                    >Current status</span
                >
            </li>
        </ol>

        <!--
            Secondary to the tracker above it and the summary below: the order
            is already placed, so this is a shortcut, not a step. Each channel
            renders only when the operator has actually set it — a button to a
            platform's own homepage would be the dead link this page used to
            ship.

            Brand glyphs are inline for the same reason the footer's are:
            @lucide/vue dropped its brand set.
        -->
        <section
            v-if="awaitingVerification && hasContactChannel"
            v-reveal
            class="mt-10 rounded-2xl border border-sf-line bg-sf-tint px-7 py-6 text-center"
        >
            <p class="text-[15px] leading-[1.7] text-sf-muted">
                <span class="font-display font-semibold text-sf-ink"
                    >Speed up verification</span
                >
                — message us your order number now.
            </p>
            <div class="mt-5 flex flex-wrap justify-center gap-3">
                <a
                    v-if="facebookLink"
                    :href="facebookLink"
                    target="_blank"
                    rel="noopener noreferrer"
                    class="inline-flex items-center gap-3 rounded-full border border-sf-line-strong bg-white py-2 pr-6 pl-2 font-display text-[15px] font-medium text-sf-ink transition-colors duration-sf-fast ease-sf hover:border-sf-primary hover:text-sf-primary"
                >
                    <span
                        class="grid size-9 place-items-center rounded-full border border-sf-line-strong bg-sf-tint text-sf-primary"
                    >
                        <svg
                            class="size-[17px]"
                            viewBox="0 0 24 24"
                            fill="currentColor"
                            aria-hidden="true"
                        >
                            <path
                                d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z"
                            />
                        </svg>
                    </span>
                    Facebook
                </a>
                <a
                    v-if="whatsappLink"
                    :href="whatsappLink"
                    target="_blank"
                    rel="noopener noreferrer"
                    class="inline-flex items-center gap-3 rounded-full border border-sf-line-strong bg-white py-2 pr-6 pl-2 font-display text-[15px] font-medium text-sf-ink transition-colors duration-sf-fast ease-sf hover:border-sf-primary hover:text-sf-primary"
                >
                    <span
                        class="grid size-9 place-items-center rounded-full border border-sf-line-strong bg-sf-tint text-sf-primary"
                    >
                        <svg
                            class="size-[17px]"
                            viewBox="0 0 24 24"
                            fill="currentColor"
                            aria-hidden="true"
                        >
                            <path
                                d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.297-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51a12.8 12.8 0 0 0-.57-.01c-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.872.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 0 1-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 0 1-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 0 1 2.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0 0 12.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 0 0 5.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 0 0-3.48-8.413z"
                            />
                        </svg>
                    </span>
                    WhatsApp
                </a>
                <a
                    v-if="gmailLink"
                    :href="gmailLink"
                    target="_blank"
                    rel="noopener noreferrer"
                    class="inline-flex items-center gap-3 rounded-full border border-sf-line-strong bg-white py-2 pr-6 pl-2 font-display text-[15px] font-medium text-sf-ink transition-colors duration-sf-fast ease-sf hover:border-sf-primary hover:text-sf-primary"
                >
                    <span
                        class="grid size-9 place-items-center rounded-full border border-sf-line-strong bg-sf-tint text-sf-primary"
                    >
                        <svg
                            class="size-[17px]"
                            viewBox="0 0 24 24"
                            fill="currentColor"
                            aria-hidden="true"
                        >
                            <path
                                d="M24 5.457v13.909c0 .904-.732 1.636-1.636 1.636h-3.819V11.73L12 16.64l-6.545-4.91v9.273H1.636A1.636 1.636 0 0 1 0 19.366V5.457c0-2.023 2.309-3.178 3.927-1.964L5.455 4.64 12 9.548l6.545-4.91 1.528-1.145C21.69 2.28 24 3.434 24 5.457z"
                            />
                        </svg>
                    </span>
                    Gmail
                </a>
            </div>
        </section>

        <div
            v-reveal
            class="mt-12 rounded-2xl border border-sf-line bg-white p-7"
        >
            <h2 class="font-display text-xl font-semibold text-sf-ink">
                Order Summary
            </h2>
            <div class="mt-5 flex flex-col divide-y divide-sf-line">
                <div
                    v-for="item in order.items"
                    :key="`${item.product_name}-${item.variant_label}`"
                    class="flex items-start justify-between gap-4 py-3.5"
                >
                    <span class="min-w-0">
                        <span
                            class="block font-medium break-words text-sf-ink"
                            >{{ item.product_name }}</span
                        >
                        <span class="block text-sm text-sf-subtle"
                            >{{ item.variant_label }} ×
                            {{ item.quantity }}</span
                        >
                    </span>
                    <span class="shrink-0 font-medium text-sf-ink">{{
                        formatPrice(item.line_total)
                    }}</span>
                </div>
            </div>
            <div class="mt-5 flex flex-col gap-3 border-t border-sf-line pt-5">
                <div class="flex justify-between text-[15px] text-sf-muted">
                    <span>Subtotal</span>
                    <span class="font-medium text-sf-ink">{{
                        formatPrice(order.subtotal)
                    }}</span>
                </div>
                <div class="flex justify-between text-[15px] text-sf-muted">
                    <span>Shipping</span>
                    <span class="font-medium text-sf-ink">{{
                        formatPrice(order.shipping_fee)
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
                        >{{ formatPrice(order.total) }}</span
                    >
                </div>
            </div>
        </div>

        <div v-reveal class="mt-12 flex justify-center">
            <Link
                :href="catalog()"
                class="sf-cta inline-flex items-center gap-2.5 rounded-full bg-sf-primary px-9 py-4 font-display text-base font-medium text-white transition duration-sf-fast ease-sf hover:bg-sf-primary-deep hover:shadow-[0_10px_24px_-10px_rgba(50,70,160,0.7)] motion-safe:hover:-translate-y-0.5"
            >
                Continue shopping
                <ArrowRight class="sf-arrow size-4" />
            </Link>
        </div>
    </div>
</template>
