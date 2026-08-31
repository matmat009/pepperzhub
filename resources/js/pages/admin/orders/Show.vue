<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import {
    ArrowLeft,
    Ban,
    Check,
    ExternalLink,
    FileText,
    Package,
    Truck,
} from '@lucide/vue';
import { computed, ref } from 'vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { formatPrice } from '@/pages/admin/products/all-products/types';
import {
    cancel,
    complete,
    index,
    paymentProof,
    processing,
    rejectPayment,
    ship,
    verifyPayment,
} from '@/routes/admin/orders';
import ActionDialog from './partials/ActionDialog.vue';
import { formatDateTime, orderTone, paymentTone } from './types';
import type { OrderDetail } from './types';

const props = defineProps<{ order: OrderDetail }>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Orders', href: index() }],
    },
});

type DialogKind = 'reject' | 'cancel' | 'ship' | null;

const openDialog = ref<DialogKind>(null);

const isOpen = (kind: Exclude<DialogKind, null>) =>
    computed({
        get: () => openDialog.value === kind,
        set: (value: boolean) => (openDialog.value = value ? kind : null),
    });

const rejectOpen = isOpen('reject');
const cancelOpen = isOpen('cancel');
const shipOpen = isOpen('ship');

/*
 * Availability mirrors the server guards exactly. An action the guard would
 * refuse is not rendered at all — offering a button that always errors is worse
 * than not offering it.
 */
const canVerify = computed(() => props.order.payment_status === 'unverified');
const canReject = computed(() => props.order.payment_status === 'unverified');

const canProcess = computed(
    () =>
        props.order.payment_status === 'verified' &&
        props.order.order_status === 'pending',
);

const canShip = computed(() => props.order.order_status === 'processing');
const canComplete = computed(() => props.order.order_status === 'shipped');

const canCancel = computed(
    () => !['completed', 'cancelled'].includes(props.order.order_status),
);

/** Why "Prepare order" is absent while payment is still unverified. */
const processingBlockedReason = computed(() =>
    props.order.order_status === 'pending' &&
    props.order.payment_status === 'unverified'
        ? 'Verify the payment to start preparing this order.'
        : null,
);

const proofUrl = computed(() => paymentProof(props.order.id).url);

const proofIsImage = computed(() =>
    ['jpg', 'jpeg', 'png'].includes(props.order.payment_proof_extension ?? ''),
);

const post = (url: string) => router.post(url, {}, { preserveScroll: true });

const timeline = computed(() =>
    [
        { label: 'Placed', at: props.order.created_at },
        { label: 'Payment verified', at: props.order.payment_verified_at },
        { label: 'Preparing', at: props.order.processing_at },
        { label: 'Shipped', at: props.order.shipped_at },
        { label: 'Completed', at: props.order.completed_at },
        { label: 'Cancelled', at: props.order.cancelled_at },
    ].filter((entry) => entry.at),
);
</script>

<template>
    <Head :title="`Order ${order.order_number}`" />

    <div class="flex flex-col gap-6 p-4 md:p-6">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <div class="flex items-start gap-3">
                <Button variant="ghost" size="icon" as-child>
                    <Link :href="index()" aria-label="Back to orders">
                        <ArrowLeft class="size-4" />
                    </Link>
                </Button>
                <div>
                    <h1 class="text-2xl font-semibold tracking-tight">
                        {{ order.order_number }}
                    </h1>
                    <div class="mt-2 flex flex-wrap items-center gap-2">
                        <Badge
                            variant="outline"
                            class="rounded-md font-normal"
                            :class="paymentTone[order.payment_status]"
                        >
                            {{ order.payment_label }}
                        </Badge>
                        <Badge
                            variant="outline"
                            class="rounded-md font-normal"
                            :class="orderTone[order.order_status]"
                        >
                            {{ order.order_label }}
                        </Badge>
                        <span class="text-sm text-muted-foreground">
                            Placed {{ formatDateTime(order.created_at) }}
                        </span>
                    </div>
                </div>
            </div>

            <div class="flex flex-wrap items-center gap-2">
                <Button
                    v-if="canVerify"
                    @click="post(verifyPayment(order.id).url)"
                >
                    <Check class="size-4" />
                    Verify payment
                </Button>
                <Button
                    v-if="canReject"
                    variant="outline"
                    class="text-destructive"
                    @click="rejectOpen = true"
                >
                    <Ban class="size-4" />
                    Reject payment
                </Button>
                <Button
                    v-if="canProcess"
                    @click="post(processing(order.id).url)"
                >
                    <Package class="size-4" />
                    Prepare order
                </Button>
                <Button v-if="canShip" @click="shipOpen = true">
                    <Truck class="size-4" />
                    Mark shipped
                </Button>
                <Button
                    v-if="canComplete"
                    @click="post(complete(order.id).url)"
                >
                    <Check class="size-4" />
                    Mark completed
                </Button>
                <Button
                    v-if="canCancel"
                    variant="ghost"
                    class="text-destructive"
                    @click="cancelOpen = true"
                >
                    Cancel order
                </Button>
            </div>
        </div>

        <p
            v-if="processingBlockedReason"
            class="rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800"
        >
            {{ processingBlockedReason }}
        </p>

        <div
            v-if="order.order_status === 'cancelled'"
            class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800"
        >
            <span class="font-medium">Cancelled</span>
            {{ formatDateTime(order.cancelled_at) }}.
            {{ order.cancellation_reason ?? 'No reason recorded.' }}
        </div>

        <div class="grid grid-cols-1 gap-6 lg:grid-cols-[minmax(0,1fr)_360px]">
            <div class="flex flex-col gap-6">
                <section class="rounded-xl border bg-card p-5">
                    <h2 class="font-semibold">Items</h2>
                    <div class="mt-4 flex flex-col divide-y">
                        <div
                            v-for="item in order.items"
                            :key="item.id"
                            class="flex items-center justify-between gap-4 py-3"
                        >
                            <div class="min-w-0">
                                <div class="truncate font-medium">
                                    {{ item.product_name }}
                                </div>
                                <div class="text-sm text-muted-foreground">
                                    {{ item.variant_label }} ×
                                    {{ item.quantity }} @
                                    {{ formatPrice(item.unit_price) }}
                                </div>
                            </div>
                            <div class="font-medium tabular-nums">
                                {{ formatPrice(item.line_total) }}
                            </div>
                        </div>
                    </div>

                    <div class="mt-4 flex flex-col gap-2 border-t pt-4 text-sm">
                        <div class="flex justify-between text-muted-foreground">
                            <span>Subtotal</span>
                            <span class="text-foreground tabular-nums">
                                {{ formatPrice(order.subtotal) }}
                            </span>
                        </div>
                        <div class="flex justify-between text-muted-foreground">
                            <span>
                                Shipping · {{ order.shipping_region_label }}
                            </span>
                            <span class="text-foreground tabular-nums">
                                {{ formatPrice(order.shipping_fee) }}
                            </span>
                        </div>
                        <div
                            class="flex justify-between border-t pt-2 text-base font-semibold"
                        >
                            <span>Total</span>
                            <span class="tabular-nums">
                                {{ formatPrice(order.total) }}
                            </span>
                        </div>
                    </div>
                </section>

                <section class="rounded-xl border bg-card p-5">
                    <h2 class="font-semibold">Payment proof</h2>
                    <p class="mt-1 text-sm text-muted-foreground">
                        {{ order.payment_method_name }} · verified by hand.
                    </p>

                    <div v-if="order.has_payment_proof" class="mt-4">
                        <a
                            v-if="proofIsImage"
                            :href="proofUrl"
                            target="_blank"
                            rel="noopener"
                            class="block overflow-hidden rounded-lg border"
                        >
                            <img
                                :src="proofUrl"
                                :alt="`Payment proof for ${order.order_number}`"
                                class="max-h-[420px] w-full bg-muted object-contain"
                            />
                        </a>
                        <Button v-else variant="outline" as-child>
                            <a :href="proofUrl" target="_blank" rel="noopener">
                                <FileText class="size-4" />
                                Open receipt
                                <ExternalLink class="size-3.5" />
                            </a>
                        </Button>
                    </div>
                    <p v-else class="mt-4 text-sm text-muted-foreground">
                        No proof on file for this order.
                    </p>
                </section>
            </div>

            <div class="flex flex-col gap-6">
                <section class="rounded-xl border bg-card p-5">
                    <h2 class="font-semibold">Customer</h2>
                    <dl class="mt-4 flex flex-col gap-3 text-sm">
                        <div>
                            <dt class="text-muted-foreground">Name</dt>
                            <dd class="font-medium">{{ order.name }}</dd>
                        </div>
                        <div>
                            <dt class="text-muted-foreground">Phone</dt>
                            <dd class="font-medium">{{ order.phone }}</dd>
                        </div>
                        <div>
                            <dt class="text-muted-foreground">
                                Facebook / WhatsApp
                            </dt>
                            <dd class="font-medium">
                                {{ order.social_handle }}
                            </dd>
                        </div>
                        <div>
                            <dt class="text-muted-foreground">Ship to</dt>
                            <dd class="font-medium">
                                {{ order.street }}, {{ order.barangay }},
                                {{ order.city }}, {{ order.province }}
                                {{ order.zip }}
                            </dd>
                        </div>
                        <div v-if="order.notes">
                            <dt class="text-muted-foreground">Notes</dt>
                            <dd>{{ order.notes }}</dd>
                        </div>
                    </dl>
                </section>

                <!--
                    Snapshot fields, read straight off the order. No live join
                    to payment_methods or shipping_couriers — that is what makes
                    this still correct after either row is renamed or deleted.
                -->
                <section class="rounded-xl border bg-card p-5">
                    <h2 class="font-semibold">Payment &amp; shipping</h2>
                    <dl class="mt-4 flex flex-col gap-3 text-sm">
                        <div>
                            <dt class="text-muted-foreground">Method</dt>
                            <dd class="font-medium">
                                {{ order.payment_method_name }}
                            </dd>
                        </div>
                        <div
                            v-for="detail in order.payment_method_details"
                            :key="detail.label"
                            class="flex justify-between gap-4"
                        >
                            <dt class="text-muted-foreground">
                                {{ detail.label }}
                            </dt>
                            <dd class="font-medium">{{ detail.value }}</dd>
                        </div>
                        <div class="border-t pt-3">
                            <dt class="text-muted-foreground">
                                Courier at checkout
                            </dt>
                            <dd class="font-medium">
                                {{ order.shipping_courier_name }} ·
                                {{ order.shipping_region_label }}
                            </dd>
                        </div>
                        <div v-if="order.shipped_via">
                            <dt class="text-muted-foreground">Shipped via</dt>
                            <dd class="font-medium">
                                {{ order.shipped_via }}
                            </dd>
                        </div>
                        <div v-if="order.tracking_number">
                            <dt class="text-muted-foreground">Tracking</dt>
                            <dd class="font-medium tabular-nums">
                                {{ order.tracking_number }}
                            </dd>
                        </div>
                    </dl>
                </section>

                <section class="rounded-xl border bg-card p-5">
                    <h2 class="font-semibold">Timeline</h2>
                    <dl class="mt-4 flex flex-col gap-3 text-sm">
                        <div
                            v-for="entry in timeline"
                            :key="entry.label"
                            class="flex justify-between gap-4"
                        >
                            <dt class="text-muted-foreground">
                                {{ entry.label }}
                            </dt>
                            <dd class="text-right">
                                {{ formatDateTime(entry.at) }}
                            </dd>
                        </div>
                    </dl>
                </section>
            </div>
        </div>

        <ActionDialog
            v-model:open="rejectOpen"
            title="Reject payment"
            description="This cancels the order and puts its stock back. The reason is shown to the customer on Track Order."
            confirm-label="Reject payment"
            fields="reason"
            destructive
            :action="rejectPayment(order.id).url"
        />

        <ActionDialog
            v-model:open="cancelOpen"
            title="Cancel order"
            description="This puts the order's stock back. Use it when the customer asks to cancel, or when you cannot fulfil."
            confirm-label="Cancel order"
            fields="reason"
            destructive
            :action="cancel(order.id).url"
        />

        <ActionDialog
            v-model:open="shipOpen"
            title="Mark shipped"
            description="Record the tracking number so the customer can follow the parcel."
            confirm-label="Mark shipped"
            fields="shipping"
            :courier-name="order.shipping_courier_name"
            :action="ship(order.id).url"
        />
    </div>
</template>
