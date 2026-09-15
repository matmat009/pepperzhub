<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { ArrowRight, Check } from '@lucide/vue';
import { Button } from '@/components/ui/button';
import { formatPrice } from '@/pages/admin/products/all-products/types';
import { index as ordersIndex, show as orderShow } from '@/routes/admin/orders';
import type { PendingPayment } from '../types';

/**
 * The oldest payments still waiting on a decision.
 *
 * Short by design: five rows, each a link straight to the order where the
 * proof can actually be verified or rejected. Anything longer belongs on the
 * Orders screen, which this defers to rather than reproducing.
 */
defineProps<{
    payments: PendingPayment[];
}>();

const markerTones = [
    'bg-sf-serenity-blue/20 text-sf-primary-deep dark:bg-sf-serenity-blue/15 dark:text-sf-serenity-blue',
    'bg-sf-rose-tint text-sf-rose-deep dark:bg-sf-rose-deep/15 dark:text-sf-rose-mid',
    'bg-violet-500/10 text-violet-600 dark:bg-violet-400/15 dark:text-violet-300',
];
</script>

<template>
    <section
        class="rounded-2xl border border-sf-serenity-blue/20 bg-white p-5 shadow-[0_12px_30px_-24px_rgba(49,82,133,0.45)] sm:p-6 dark:border-border dark:bg-card"
    >
        <div
            class="flex flex-col gap-1.5 sm:flex-row sm:items-baseline sm:justify-between sm:gap-4"
        >
            <h2 class="text-xl font-semibold tracking-tight">
                Pending payments
            </h2>
            <span
                class="text-sm leading-relaxed text-sf-primary-soft dark:text-muted-foreground"
            >
                Oldest first — these customers are waiting.
            </span>
        </div>

        <div v-if="payments.length" class="mt-4 flex flex-col gap-2">
            <Link
                v-for="(payment, index) in payments"
                :key="payment.id"
                :href="orderShow(payment.id)"
                class="flex min-w-0 flex-col gap-3 rounded-xl border border-sf-serenity-blue/20 bg-background/55 p-3 transition-colors duration-200 ease-out hover:border-sf-serenity-blue/45 hover:bg-sf-serenity-blue/[0.07] focus-visible:ring-2 focus-visible:ring-sf-primary/25 focus-visible:outline-none sm:flex-row sm:items-center sm:justify-between sm:gap-5"
            >
                <div class="flex min-w-0 items-center gap-3">
                    <span
                        aria-hidden="true"
                        class="grid size-11 shrink-0 place-items-center rounded-full text-sm font-semibold"
                        :class="markerTones[index % markerTones.length]"
                    >
                        PZ
                    </span>
                    <div class="min-w-0">
                        <div class="font-semibold break-words tabular-nums">
                            {{ payment.order_number }}
                        </div>
                        <div
                            class="mt-0.5 text-sm break-words text-sf-primary-soft dark:text-muted-foreground"
                        >
                            {{ payment.name }}
                        </div>
                    </div>
                </div>
                <div
                    class="flex min-w-0 flex-wrap items-center justify-between gap-x-8 gap-y-1 sm:ml-auto sm:shrink-0 sm:justify-end sm:text-right"
                >
                    <span
                        class="text-sm whitespace-nowrap text-sf-primary-soft dark:text-muted-foreground"
                    >
                        {{ payment.waiting_for ?? '—' }}
                    </span>
                    <span class="font-semibold whitespace-nowrap tabular-nums">
                        {{ formatPrice(payment.total) }}
                    </span>
                </div>
            </Link>
        </div>

        <div
            v-else
            class="mt-4 flex flex-col items-center rounded-lg border border-dashed px-6 py-10 text-center"
        >
            <span
                class="grid size-10 place-items-center rounded-full bg-emerald-50 text-emerald-600"
            >
                <Check class="size-5" />
            </span>
            <p class="mt-3 text-sm font-medium">Nothing waiting</p>
            <p class="mt-1 text-sm text-muted-foreground">
                Every payment has been verified or rejected.
            </p>
        </div>

        <!--
            Plain Orders index, not a pre-filtered deep link: that screen loads
            every order and filters client-side, so there is no filter state in
            the URL to target. See the handover note.
        -->
        <Button
            as-child
            class="mt-4 h-11 rounded-lg border-sf-primary bg-sf-primary px-5 text-white shadow-sm hover:border-sf-primary-hover hover:bg-sf-primary-hover"
        >
            <Link :href="ordersIndex()">
                View all orders
                <ArrowRight class="size-4" />
            </Link>
        </Button>
    </section>
</template>
