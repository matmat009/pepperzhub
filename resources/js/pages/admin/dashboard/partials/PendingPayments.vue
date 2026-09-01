<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { ArrowRight, Check } from '@lucide/vue';
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
</script>

<template>
    <section class="rounded-xl border bg-card p-5">
        <div class="flex flex-wrap items-baseline justify-between gap-2">
            <h2 class="font-semibold">Pending payments</h2>
            <span class="text-sm text-muted-foreground">
                Oldest first — these customers are waiting.
            </span>
        </div>

        <div v-if="payments.length" class="mt-4 flex flex-col divide-y">
            <Link
                v-for="payment in payments"
                :key="payment.id"
                :href="orderShow(payment.id)"
                class="flex flex-wrap items-center justify-between gap-x-4 gap-y-1 py-3 transition-colors duration-200 ease-out first:pt-0 hover:bg-muted/50"
            >
                <div class="min-w-0">
                    <div class="truncate font-medium tabular-nums">
                        {{ payment.order_number }}
                    </div>
                    <div class="truncate text-sm text-muted-foreground">
                        {{ payment.name }}
                    </div>
                </div>
                <div class="flex items-center gap-4 text-right">
                    <span class="text-sm text-muted-foreground">
                        {{ payment.waiting_for ?? '—' }}
                    </span>
                    <span class="font-medium tabular-nums">
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
        <Link
            :href="ordersIndex()"
            class="mt-4 inline-flex items-center gap-1.5 text-sm font-medium text-muted-foreground transition-colors duration-200 ease-out hover:text-foreground"
        >
            View all orders
            <ArrowRight class="size-3.5" />
        </Link>
    </section>
</template>
