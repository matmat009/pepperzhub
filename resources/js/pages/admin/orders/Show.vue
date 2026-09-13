<script setup lang="ts">
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import {
    ArrowLeft,
    Ban,
    Check,
    CircleAlert,
    Clock3,
    ExternalLink,
    FileText,
    FlaskConical,
    ImageIcon,
    Package,
    Pencil,
    Truck,
    UserRound,
} from '@lucide/vue';
import { computed, ref, watch } from 'vue';
import InputError from '@/components/InputError.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { formatPrice } from '@/pages/admin/products/all-products/types';
import {
    cancel,
    complete,
    index,
    paymentProof,
    processing,
    rejectPayment,
    ship,
    updateContact,
    verifyPayment,
} from '@/routes/admin/orders';
import ActionDialog from './partials/ActionDialog.vue';
import {
    canCancel as canCancelOrder,
    canEditContact,
    canMarkCompleted,
    canMarkProcessing,
    canMarkShipped,
    canRejectPayment,
    canVerifyPayment,
    formatDateTime,
    orderTone,
    paymentTone,
} from './types';
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
 * than not offering it. The predicates live in ./types.ts so there is one
 * client-side statement of the machine rather than five that can drift.
 */
const canVerify = computed(() => canVerifyPayment(props.order));
const canReject = computed(() => canRejectPayment(props.order));
const canProcess = computed(() => canMarkProcessing(props.order));
const canShip = computed(() => canMarkShipped(props.order));
const canComplete = computed(() => canMarkCompleted(props.order));
const canCancel = computed(() => canCancelOrder(props.order));
const canEditDetails = computed(() => canEditContact(props.order));

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

/*
 * Contact/shipping editing, scoped to the Customer section alone.
 *
 * Same shape as the product editor on admin/products/all-products/Show.vue: a
 * local `editing` flag, a form seeded from props, and a watch that rebases the
 * form on the props a successful save re-renders with — without which Cancel
 * would revert to the values the page first loaded rather than the last saved
 * ones. Deliberately local to this section: the header's status buttons are a
 * separate concern and stay untouched.
 */
type ContactFields = {
    name: string;
    social_handle: string;
    phone: string;
    street: string;
    barangay: string;
    city: string;
    province: string;
    zip: string;
    notes: string;
};

/** notes is nullable server-side; the textarea wants a string either way. */
const toContactForm = (order: OrderDetail): ContactFields => ({
    name: order.name,
    social_handle: order.social_handle,
    phone: order.phone,
    street: order.street,
    barangay: order.barangay,
    city: order.city,
    province: order.province,
    zip: order.zip,
    notes: order.notes ?? '',
});

const contactForm = useForm<ContactFields>(toContactForm(props.order));

const editingContact = ref(false);

watch(
    () => props.order,
    (order) => {
        contactForm.defaults(toContactForm(order));
        contactForm.reset();
    },
);

const startEditingContact = () => {
    editingContact.value = true;
};

const cancelContactEdit = () => {
    contactForm.clearErrors();
    contactForm.reset();
    editingContact.value = false;
};

const submitContact = () => {
    contactForm.put(updateContact(props.order.id).url, {
        preserveScroll: true,
        onSuccess: () => {
            editingContact.value = false;
        },
    });
};

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

    <div
        class="flex min-h-full flex-col gap-5 overflow-x-hidden bg-[linear-gradient(135deg,rgba(146,168,209,0.08)_0%,transparent_32%,transparent_68%,rgba(247,202,201,0.08)_100%)] p-4 md:p-6"
    >
        <div class="flex flex-wrap items-start justify-between gap-4">
            <div class="flex items-start gap-3">
                <Button
                    variant="ghost"
                    size="icon"
                    class="rounded-full bg-sf-serenity-blue/12 text-sf-primary-deep hover:bg-sf-serenity-blue/22 hover:text-sf-primary-deep dark:bg-sf-serenity-blue/15 dark:text-sf-serenity-blue"
                    as-child
                >
                    <Link :href="index()" aria-label="Back to orders">
                        <ArrowLeft aria-hidden="true" class="size-5" />
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

            <div class="flex max-w-full flex-wrap items-center gap-2">
                <Button
                    v-if="canVerify"
                    class="border-sf-primary bg-sf-primary text-white shadow-sm hover:border-sf-primary-hover hover:bg-sf-primary-hover"
                    @click="post(verifyPayment(order.id).url)"
                >
                    <Check aria-hidden="true" class="size-4" />
                    Verify payment
                </Button>
                <Button
                    v-if="canReject"
                    variant="destructive"
                    @click="rejectOpen = true"
                >
                    <Ban class="size-4" />
                    Reject payment
                </Button>
                <Button
                    v-if="canProcess"
                    class="border-sf-primary bg-sf-primary text-white shadow-sm hover:border-sf-primary-hover hover:bg-sf-primary-hover"
                    @click="post(processing(order.id).url)"
                >
                    <Package aria-hidden="true" class="size-4" />
                    Prepare order
                </Button>
                <Button
                    v-if="canShip"
                    class="border-sf-primary bg-sf-primary text-white shadow-sm hover:border-sf-primary-hover hover:bg-sf-primary-hover"
                    @click="shipOpen = true"
                >
                    <Truck aria-hidden="true" class="size-4" />
                    Mark shipped
                </Button>
                <Button
                    v-if="canComplete"
                    class="border-sf-primary bg-sf-primary text-white shadow-sm hover:border-sf-primary-hover hover:bg-sf-primary-hover"
                    @click="post(complete(order.id).url)"
                >
                    <Check aria-hidden="true" class="size-4" />
                    Mark completed
                </Button>
                <Button
                    v-if="canCancel"
                    variant="destructive"
                    @click="cancelOpen = true"
                >
                    Cancel order
                </Button>
            </div>
        </div>

        <p
            v-if="processingBlockedReason"
            class="flex items-start gap-3 rounded-xl border border-amber-200 bg-amber-50/90 px-4 py-3 text-sm text-amber-800 shadow-xs"
        >
            <CircleAlert
                aria-hidden="true"
                class="mt-0.5 size-5 shrink-0 fill-amber-500 [stroke:white] text-amber-500"
            />
            <span>{{ processingBlockedReason }}</span>
        </p>

        <div
            v-if="order.order_status === 'cancelled'"
            class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800"
        >
            <span class="font-medium">Cancelled</span>
            {{ formatDateTime(order.cancelled_at) }}.
            {{ order.cancellation_reason ?? 'No reason recorded.' }}
        </div>

        <div
            class="flex flex-col gap-5 xl:grid xl:grid-cols-[minmax(0,2.15fr)_minmax(300px,1fr)] xl:items-start"
        >
            <div class="contents xl:flex xl:flex-col xl:gap-5">
                <section
                    class="order-1 rounded-2xl border border-sf-serenity-blue/20 bg-card/95 p-5 shadow-sm xl:order-none"
                >
                    <div class="flex items-center gap-3">
                        <span
                            class="flex size-11 shrink-0 items-center justify-center rounded-full bg-sf-serenity-blue/15 text-sf-primary-deep dark:text-sf-serenity-blue"
                        >
                            <Package aria-hidden="true" class="size-5" />
                        </span>
                        <h2 class="text-base font-semibold">Items</h2>
                    </div>

                    <div class="mt-4 flex flex-col gap-3">
                        <div
                            v-for="item in order.items"
                            :key="item.id"
                            class="flex min-w-0 items-start justify-between gap-4 rounded-xl border border-sf-serenity-blue/16 bg-background/80 p-3 shadow-xs"
                        >
                            <div class="flex min-w-0 items-start gap-3">
                                <!--
                                    Same well and FlaskConical fallback the
                                    products list uses, so a line whose variant
                                    has been deleted looks like every other
                                    missing image in the admin.
                                -->
                                <div
                                    class="flex size-12 shrink-0 items-center justify-center overflow-hidden rounded-lg border border-sf-serenity-blue/20 bg-sf-serenity-blue/8"
                                >
                                    <img
                                        v-if="item.image_url"
                                        :src="item.image_url"
                                        :alt="item.product_name"
                                        class="size-full object-contain p-1"
                                    />
                                    <FlaskConical
                                        v-else
                                        class="size-4 text-muted-foreground"
                                    />
                                </div>
                                <div class="min-w-0">
                                    <div class="font-medium break-words">
                                        {{ item.product_name }}
                                    </div>
                                    <div
                                        class="text-sm break-words text-muted-foreground"
                                    >
                                        {{ item.variant_label }} ×
                                        {{ item.quantity }} @
                                        {{ formatPrice(item.unit_price) }}
                                    </div>
                                    <!--
                                        Snapshotted at order time, so this is
                                        the packing list as it stood then —
                                        null on orders that predate the
                                        snapshot, which renders as nothing.
                                    -->
                                    <ul
                                        v-if="
                                            item.is_kit &&
                                            item.kit_inclusions?.length
                                        "
                                        class="mt-1 flex flex-col gap-0.5"
                                    >
                                        <li
                                            v-for="inclusion in item.kit_inclusions"
                                            :key="inclusion"
                                            class="flex items-start gap-2 text-sm text-muted-foreground"
                                        >
                                            <span
                                                class="mt-2 size-1 shrink-0 rounded-full bg-muted-foreground/50"
                                            />
                                            <span>{{ inclusion }}</span>
                                        </li>
                                    </ul>
                                </div>
                            </div>
                            <div
                                class="shrink-0 text-right font-semibold tabular-nums"
                            >
                                {{ formatPrice(item.line_total) }}
                            </div>
                        </div>
                    </div>

                    <div class="mt-4 flex flex-col gap-2 text-sm">
                        <div
                            class="flex items-start justify-between gap-4 px-3 text-muted-foreground"
                        >
                            <span>Subtotal</span>
                            <span
                                class="shrink-0 text-right text-foreground tabular-nums"
                            >
                                {{ formatPrice(order.subtotal) }}
                            </span>
                        </div>
                        <div
                            class="flex items-start justify-between gap-4 px-3 text-muted-foreground"
                        >
                            <span class="min-w-0 break-words">
                                Shipping · {{ order.shipping_region_label }}
                            </span>
                            <span
                                class="shrink-0 text-right text-foreground tabular-nums"
                            >
                                {{ formatPrice(order.shipping_fee) }}
                            </span>
                        </div>
                        <div
                            class="flex items-center justify-between gap-4 rounded-xl bg-sf-serenity-blue/12 px-3 py-2.5 text-base font-semibold text-sf-primary-soft dark:text-sf-serenity-blue"
                        >
                            <span>Total</span>
                            <span class="shrink-0 text-right tabular-nums">
                                {{ formatPrice(order.total) }}
                            </span>
                        </div>
                    </div>
                </section>

                <section
                    class="order-3 rounded-2xl border border-sf-serenity-blue/20 bg-card/95 p-5 shadow-sm xl:order-none"
                >
                    <div class="flex items-start justify-between gap-3">
                        <div class="flex items-center gap-3">
                            <span
                                class="flex size-11 shrink-0 items-center justify-center rounded-full bg-sf-rose-tint text-sf-rose-deep"
                            >
                                <UserRound aria-hidden="true" class="size-5" />
                            </span>
                            <h2 class="text-base font-semibold">Customer</h2>
                        </div>

                        <div
                            v-if="editingContact"
                            class="flex items-center gap-2"
                        >
                            <Button
                                variant="outline"
                                size="xs"
                                :disabled="contactForm.processing"
                                @click="cancelContactEdit"
                            >
                                Cancel
                            </Button>
                            <Button
                                size="xs"
                                :loading="contactForm.processing"
                                @click="submitContact"
                            >
                                Save
                            </Button>
                        </div>
                        <Button
                            v-else-if="canEditDetails"
                            variant="outline"
                            size="sm"
                            class="border-sf-serenity-blue/25 bg-sf-serenity-blue/12 text-sf-primary-deep shadow-none hover:bg-sf-serenity-blue/20 hover:text-sf-primary-deep"
                            @click="startEditingContact"
                        >
                            <Pencil aria-hidden="true" class="size-3.5" />
                            Edit
                        </Button>
                    </div>

                    <form
                        v-if="editingContact"
                        class="mt-5 grid gap-4 sm:grid-cols-2"
                        @submit.prevent="submitContact"
                    >
                        <div class="grid gap-2">
                            <Label for="contact-name">Name</Label>
                            <Input
                                id="contact-name"
                                v-model="contactForm.name"
                                autocomplete="off"
                            />
                            <InputError :message="contactForm.errors.name" />
                        </div>

                        <div class="grid gap-2">
                            <Label for="contact-phone">Phone</Label>
                            <Input
                                id="contact-phone"
                                v-model="contactForm.phone"
                                autocomplete="off"
                            />
                            <InputError :message="contactForm.errors.phone" />
                        </div>

                        <div class="grid gap-2">
                            <Label for="contact-social"
                                >Facebook / WhatsApp</Label
                            >
                            <Input
                                id="contact-social"
                                v-model="contactForm.social_handle"
                                autocomplete="off"
                            />
                            <InputError
                                :message="contactForm.errors.social_handle"
                            />
                        </div>

                        <div class="grid gap-2">
                            <Label for="contact-street">Street</Label>
                            <Input
                                id="contact-street"
                                v-model="contactForm.street"
                                autocomplete="off"
                            />
                            <InputError :message="contactForm.errors.street" />
                        </div>

                        <div class="grid gap-2">
                            <Label for="contact-barangay">Barangay</Label>
                            <Input
                                id="contact-barangay"
                                v-model="contactForm.barangay"
                                autocomplete="off"
                            />
                            <InputError
                                :message="contactForm.errors.barangay"
                            />
                        </div>

                        <div class="grid gap-2">
                            <Label for="contact-city">City</Label>
                            <Input
                                id="contact-city"
                                v-model="contactForm.city"
                                autocomplete="off"
                            />
                            <InputError :message="contactForm.errors.city" />
                        </div>
                        <div class="grid gap-2">
                            <Label for="contact-province">Province</Label>
                            <Input
                                id="contact-province"
                                v-model="contactForm.province"
                                autocomplete="off"
                            />
                            <InputError
                                :message="contactForm.errors.province"
                            />
                        </div>

                        <div class="grid gap-2">
                            <Label for="contact-zip">ZIP</Label>
                            <Input
                                id="contact-zip"
                                v-model="contactForm.zip"
                                autocomplete="off"
                            />
                            <InputError :message="contactForm.errors.zip" />
                        </div>

                        <div class="grid gap-2 sm:col-span-2">
                            <Label for="contact-notes">
                                Notes
                                <span class="font-normal text-muted-foreground">
                                    (Optional)
                                </span>
                            </Label>
                            <Textarea
                                id="contact-notes"
                                v-model="contactForm.notes"
                                :rows="3"
                            />
                            <InputError :message="contactForm.errors.notes" />
                        </div>
                    </form>

                    <dl
                        v-else
                        class="mt-4 grid gap-y-4 border-t pt-4 text-sm sm:grid-cols-2 lg:grid-cols-3 2xl:grid-cols-[0.7fr_0.8fr_1.1fr_1.8fr_1fr]"
                    >
                        <div
                            class="min-w-0 2xl:border-l 2xl:pl-5 2xl:first:border-l-0 2xl:first:pl-0"
                        >
                            <dt class="text-muted-foreground">Name</dt>
                            <dd class="font-medium break-words">
                                {{ order.name }}
                            </dd>
                        </div>
                        <div class="min-w-0 2xl:border-l 2xl:pl-5">
                            <dt class="text-muted-foreground">Phone</dt>
                            <dd class="font-medium break-words">
                                {{ order.phone }}
                            </dd>
                        </div>
                        <div class="min-w-0 2xl:border-l 2xl:pl-5">
                            <dt class="text-muted-foreground">
                                Facebook / WhatsApp
                            </dt>
                            <dd class="font-medium break-words">
                                {{ order.social_handle }}
                            </dd>
                        </div>
                        <div class="min-w-0 2xl:border-l 2xl:pl-5">
                            <dt class="text-muted-foreground">Ship to</dt>
                            <dd class="font-medium break-words">
                                {{ order.street }}, {{ order.barangay }},
                                {{ order.city }}, {{ order.province }}
                                {{ order.zip }}
                            </dd>
                        </div>
                        <div
                            v-if="order.notes"
                            class="min-w-0 2xl:border-l 2xl:pl-5"
                        >
                            <dt class="text-muted-foreground">Notes</dt>
                            <dd class="break-words whitespace-pre-wrap">
                                {{ order.notes }}
                            </dd>
                        </div>
                    </dl>
                </section>

                <!--
                    Snapshot fields, read straight off the order. No live join
                    to payment_methods or shipping_couriers — that is what makes
                    this still correct after either row is renamed or deleted.
                -->
                <section
                    class="order-4 rounded-2xl border border-sf-serenity-blue/20 bg-card/95 p-5 shadow-sm xl:order-none"
                >
                    <div class="flex items-center gap-3">
                        <span
                            class="flex size-11 shrink-0 items-center justify-center rounded-full bg-sf-serenity-blue/15 text-sf-primary-deep dark:text-sf-serenity-blue"
                        >
                            <Truck aria-hidden="true" class="size-5" />
                        </span>
                        <h2 class="text-base font-semibold">
                            Payment &amp; shipping
                        </h2>
                    </div>
                    <dl
                        class="mt-4 grid gap-y-4 border-t pt-4 text-sm sm:grid-cols-2 lg:grid-cols-3 2xl:grid-cols-5"
                    >
                        <div
                            class="min-w-0 2xl:border-l 2xl:pl-5 2xl:first:border-l-0 2xl:first:pl-0"
                        >
                            <dt class="text-muted-foreground">Method</dt>
                            <dd class="font-medium break-words">
                                {{ order.payment_method_name }}
                            </dd>
                        </div>
                        <div
                            v-for="detail in order.payment_method_details"
                            :key="detail.label"
                            class="min-w-0 2xl:border-l 2xl:pl-5"
                        >
                            <dt class="text-muted-foreground">
                                {{ detail.label }}
                            </dt>
                            <dd class="font-medium break-words">
                                {{ detail.value }}
                            </dd>
                        </div>
                        <div
                            class="min-w-0 sm:col-span-2 2xl:col-span-1 2xl:border-l 2xl:pl-5"
                        >
                            <dt class="text-muted-foreground">
                                Courier at checkout
                            </dt>
                            <dd class="font-medium break-words">
                                {{ order.shipping_courier_name }} ·
                                {{ order.shipping_region_label }}
                            </dd>
                        </div>
                        <div
                            v-if="order.shipped_via"
                            class="min-w-0 2xl:border-l 2xl:pl-5"
                        >
                            <dt class="text-muted-foreground">Shipped via</dt>
                            <dd class="font-medium break-words">
                                {{ order.shipped_via }}
                            </dd>
                        </div>
                        <div
                            v-if="order.tracking_number"
                            class="min-w-0 2xl:border-l 2xl:pl-5"
                        >
                            <dt class="text-muted-foreground">Tracking</dt>
                            <dd class="font-medium tabular-nums">
                                {{ order.tracking_number }}
                            </dd>
                        </div>
                    </dl>
                </section>
            </div>

            <div class="contents xl:flex xl:flex-col xl:gap-5">
                <section
                    class="order-2 rounded-2xl border border-sf-serenity-blue/20 bg-card/95 p-5 shadow-sm xl:order-none"
                >
                    <div class="flex items-center gap-3">
                        <span
                            class="flex size-11 shrink-0 items-center justify-center rounded-full bg-sf-serenity-blue/15 text-sf-primary-deep dark:text-sf-serenity-blue"
                        >
                            <ImageIcon aria-hidden="true" class="size-5" />
                        </span>
                        <div class="min-w-0">
                            <h2 class="text-base font-semibold">
                                Payment proof
                            </h2>
                            <p class="mt-0.5 text-sm text-muted-foreground">
                                {{ order.payment_method_name }} · verified by
                                hand.
                            </p>
                        </div>
                    </div>

                    <div v-if="order.has_payment_proof" class="mt-4">
                        <a
                            v-if="proofIsImage"
                            :href="proofUrl"
                            target="_blank"
                            rel="noopener"
                            class="flex min-h-64 items-center justify-center overflow-hidden rounded-xl border border-sf-serenity-blue/16 bg-muted/55 p-3 transition-colors hover:border-sf-serenity-blue/45 focus-visible:ring-3 focus-visible:ring-sf-primary/25 focus-visible:outline-none"
                        >
                            <img
                                :src="proofUrl"
                                :alt="`Payment proof for ${order.order_number}`"
                                class="max-h-[520px] max-w-full rounded-lg object-contain"
                            />
                        </a>
                        <Button v-else variant="outline" as-child>
                            <a :href="proofUrl" target="_blank" rel="noopener">
                                <FileText aria-hidden="true" class="size-4" />
                                Open receipt
                                <ExternalLink
                                    aria-hidden="true"
                                    class="size-3.5"
                                />
                            </a>
                        </Button>
                    </div>
                    <p v-else class="mt-4 text-sm text-muted-foreground">
                        No proof on file for this order.
                    </p>
                </section>

                <section
                    class="order-5 rounded-2xl border border-sf-serenity-blue/20 bg-card/95 p-5 shadow-sm xl:order-none"
                >
                    <div class="flex items-center gap-3 border-b pb-4">
                        <span
                            class="flex size-11 shrink-0 items-center justify-center rounded-full bg-sf-rose-tint text-sf-rose-deep"
                        >
                            <Clock3 aria-hidden="true" class="size-5" />
                        </span>
                        <h2 class="text-base font-semibold">Timeline</h2>
                    </div>
                    <dl class="text-sm">
                        <div
                            v-for="entry in timeline"
                            :key="entry.label"
                            class="flex items-start justify-between gap-4 border-b py-3 last:border-b-0 last:pb-0"
                        >
                            <dt class="text-muted-foreground">
                                {{ entry.label }}
                            </dt>
                            <dd class="text-right font-medium break-words">
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
