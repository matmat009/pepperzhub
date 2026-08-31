<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import { Check, FlaskConical, QrCode, Upload } from '@lucide/vue';
import { computed, ref, watch } from 'vue';
import type { CartLine } from '@/composables/useStorefrontCart';
import { formatPrice } from '@/pages/admin/products/all-products/types';
import { home } from '@/routes';
import { index as catalog } from '@/routes/storefront/products';
import { store as submitCheckout } from '@/routes/storefront/checkout';

/**
 * Couriers, regions and payment methods are seeded rows now, not hardcoded
 * arrays. Prices and rates displayed here are for information only — the server
 * recomputes every figure from the database when the order is placed.
 */
type Region = {
    id: number;
    name: string;
    note: string | null;
    rate: number;
};

type Courier = {
    id: number;
    name: string;
    regions: Region[];
};

type PaymentMethod = {
    id: number;
    name: string;
    details: { label: string; value: string }[];
    qr_code_url: string | null;
};

const props = defineProps<{
    lines: CartLine[];
    subtotal: number;
    couriers: Courier[];
    paymentMethods: PaymentMethod[];
}>();

const form = useForm<{
    name: string;
    social_handle: string;
    phone: string;
    street: string;
    barangay: string;
    city: string;
    province: string;
    zip: string;
    notes: string;
    shipping_region_id: number | null;
    payment_method_id: number | null;
    payment_proof: File | null;
}>({
    name: '',
    social_handle: '',
    phone: '',
    street: '',
    barangay: '',
    city: '',
    province: '',
    zip: '',
    notes: '',
    shipping_region_id: null,
    payment_method_id: null,
    payment_proof: null,
});

const courierId = ref<number | null>(null);
const proofName = ref('');

const selectedCourier = computed(() =>
    props.couriers.find((courier) => courier.id === courierId.value),
);

const selectedRegion = computed(() =>
    selectedCourier.value?.regions.find(
        (region) => region.id === form.shipping_region_id,
    ),
);

const selectedPayment = computed(() =>
    props.paymentMethods.find((method) => method.id === form.payment_method_id),
);

// Changing the courier invalidates whichever region was picked under the old one.
watch(courierId, () => {
    form.shipping_region_id = null;
});

const shipping = computed(() => selectedRegion.value?.rate ?? 0);
const total = computed(() => props.subtotal + shipping.value);

const requiredFilled = computed(() =>
    (
        [
            'name',
            'social_handle',
            'phone',
            'street',
            'barangay',
            'city',
            'province',
            'zip',
        ] as const
    ).every((key) => form[key].trim().length > 0),
);

const missing = computed(() => {
    const gaps: string[] = [];

    if (!requiredFilled.value) {
        gaps.push('required fields');
    }

    if (!courierId.value) {
        gaps.push('courier');
    } else if (!form.shipping_region_id) {
        gaps.push('shipping region');
    }

    if (!form.payment_method_id) {
        gaps.push('payment method');
    }

    if (!form.payment_proof) {
        gaps.push('proof of payment');
    }

    return gaps;
});

const ready = computed(
    () => missing.value.length === 0 && props.lines.length > 0,
);

const onProof = (event: Event) => {
    const input = event.target as HTMLInputElement;
    const file = input.files?.[0] ?? null;

    form.payment_proof = file;
    proofName.value = file?.name ?? '';
};

/**
 * A real submit now. Inertia switches to multipart automatically because the
 * payload carries a File, which is what carries the payment proof up.
 */
const placeOrder = () => {
    if (!ready.value) {
        return;
    }

    form.post(submitCheckout().url, { forceFormData: true });
};

const fieldClass =
    'w-full rounded-xl border border-sf-line-strong bg-white px-4 py-3 text-[15px] text-sf-ink outline-none transition-colors duration-200 ease-out focus:border-sf-primary';
</script>

<template>
    <Head title="Checkout" />

    <div class="mx-auto w-full max-w-[1680px] px-5 pt-8 pb-28 sm:px-10">
        <div class="flex items-center gap-2 text-sm text-sf-subtle">
            <Link
                :href="home()"
                class="transition-colors duration-200 ease-out hover:text-sf-primary"
                >Home</Link
            >
            <span>/</span>
            <span class="text-sf-ink">Checkout</span>
        </div>

        <h1
            class="mt-5 font-display text-[34px] font-medium tracking-[-0.02em] text-sf-ink"
        >
            Checkout
        </h1>

        <form
            class="mt-8 grid grid-cols-1 gap-16 lg:grid-cols-[1fr_420px]"
            @submit.prevent="placeOrder"
        >
            <div class="flex flex-col gap-10">
                <section>
                    <h2 class="font-display text-xl font-semibold text-sf-ink">
                        Customer Details
                    </h2>
                    <div class="mt-5 grid grid-cols-1 gap-5 sm:grid-cols-2">
                        <label class="flex flex-col gap-2">
                            <span class="text-sm font-medium text-sf-text"
                                >Full Name
                                <span class="text-sf-rose-deep">*</span></span
                            >
                            <input
                                v-model="form.name"
                                :class="fieldClass"
                                placeholder="Juan Dela Cruz"
                            />
                        </label>
                        <label class="flex flex-col gap-2">
                            <span class="text-sm font-medium text-sf-text"
                                >Facebook or WhatsApp Name
                                <span class="text-sf-rose-deep">*</span></span
                            >
                            <input
                                v-model="form.social_handle"
                                :class="fieldClass"
                                placeholder="fb.com/juandc or +63 917…"
                            />
                        </label>
                        <label class="flex flex-col gap-2 sm:col-span-2">
                            <span class="text-sm font-medium text-sf-text"
                                >Phone Number
                                <span class="text-sf-rose-deep">*</span></span
                            >
                            <input
                                v-model="form.phone"
                                :class="fieldClass"
                                placeholder="0917 123 4567"
                            />
                        </label>
                    </div>
                </section>

                <section>
                    <h2 class="font-display text-xl font-semibold text-sf-ink">
                        Shipping Address
                    </h2>
                    <div class="mt-5 grid grid-cols-1 gap-5 sm:grid-cols-2">
                        <label class="flex flex-col gap-2 sm:col-span-2">
                            <span class="text-sm font-medium text-sf-text"
                                >Street Address
                                <span class="text-sf-rose-deep">*</span></span
                            >
                            <input
                                v-model="form.street"
                                :class="fieldClass"
                                placeholder="Unit / house no., street"
                            />
                        </label>
                        <label class="flex flex-col gap-2">
                            <span class="text-sm font-medium text-sf-text"
                                >Barangay
                                <span class="text-sf-rose-deep">*</span></span
                            >
                            <input
                                v-model="form.barangay"
                                :class="fieldClass"
                                placeholder="Barangay"
                            />
                        </label>
                        <label class="flex flex-col gap-2">
                            <span class="text-sm font-medium text-sf-text"
                                >City
                                <span class="text-sf-rose-deep">*</span></span
                            >
                            <input
                                v-model="form.city"
                                :class="fieldClass"
                                placeholder="City / municipality"
                            />
                        </label>
                        <label class="flex flex-col gap-2">
                            <span class="text-sm font-medium text-sf-text"
                                >Province
                                <span class="text-sf-rose-deep">*</span></span
                            >
                            <input
                                v-model="form.province"
                                :class="fieldClass"
                                placeholder="Province"
                            />
                        </label>
                        <label class="flex flex-col gap-2">
                            <span class="text-sm font-medium text-sf-text"
                                >ZIP / Postal Code
                                <span class="text-sf-rose-deep">*</span></span
                            >
                            <input
                                v-model="form.zip"
                                :class="fieldClass"
                                placeholder="e.g. 1100"
                            />
                        </label>
                    </div>
                </section>

                <section>
                    <h2 class="font-display text-xl font-semibold text-sf-ink">
                        Select Courier
                    </h2>
                    <label class="mt-5 flex flex-col gap-2">
                        <span class="text-sm font-medium text-sf-text"
                            >Courier
                            <span class="text-sf-rose-deep">*</span></span
                        >
                        <select v-model="courierId" :class="fieldClass">
                            <option :value="null">Choose a courier…</option>
                            <option
                                v-for="courier in couriers"
                                :key="courier.id"
                                :value="courier.id"
                            >
                                {{ courier.name }}
                            </option>
                        </select>
                    </label>

                    <div v-if="selectedCourier" class="mt-6">
                        <div
                            class="font-display text-[15px] font-semibold text-sf-ink"
                        >
                            Choose Delivery Option
                        </div>
                        <div class="mt-3 flex flex-col gap-3">
                            <label
                                v-for="region in selectedCourier.regions"
                                :key="region.id"
                                class="flex cursor-pointer items-center justify-between gap-4 rounded-xl border px-5 py-4 transition-colors duration-200 ease-out"
                                :class="
                                    form.shipping_region_id === region.id
                                        ? 'border-sf-primary bg-sf-tint'
                                        : 'border-sf-line-strong bg-white hover:border-sf-primary/50'
                                "
                            >
                                <span class="flex items-center gap-3">
                                    <input
                                        v-model="form.shipping_region_id"
                                        type="radio"
                                        :value="region.id"
                                        class="size-4 accent-sf-primary"
                                    />
                                    <span>
                                        <span
                                            class="block text-[15px] font-medium text-sf-ink"
                                            >{{ region.name }}</span
                                        >
                                        <span
                                            class="block text-sm text-sf-subtle"
                                            >{{ region.note }}</span
                                        >
                                    </span>
                                </span>
                                <span
                                    class="font-display font-semibold text-sf-primary-soft"
                                    >{{ formatPrice(region.rate) }}</span
                                >
                            </label>
                        </div>
                    </div>
                </section>

                <section>
                    <h2 class="font-display text-xl font-semibold text-sf-ink">
                        Select Payment Method
                    </h2>
                    <label class="mt-5 flex flex-col gap-2">
                        <span class="text-sm font-medium text-sf-text"
                            >Payment Method
                            <span class="text-sf-rose-deep">*</span></span
                        >
                        <select
                            v-model="form.payment_method_id"
                            :class="fieldClass"
                        >
                            <option :value="null">
                                Choose a payment method…
                            </option>
                            <option
                                v-for="method in paymentMethods"
                                :key="method.id"
                                :value="method.id"
                            >
                                {{ method.name }}
                            </option>
                        </select>
                    </label>

                    <div
                        v-if="selectedPayment"
                        class="mt-6 grid grid-cols-1 gap-6 rounded-xl border border-sf-line bg-sf-tint p-6 sm:grid-cols-[1fr_auto]"
                    >
                        <dl class="flex flex-col gap-3 text-[15px]">
                            <div
                                v-for="detail in selectedPayment.details"
                                :key="detail.label"
                                class="flex justify-between gap-6"
                            >
                                <dt class="text-sf-muted">
                                    {{ detail.label }}
                                </dt>
                                <dd class="font-semibold text-sf-ink">
                                    {{ detail.value }}
                                </dd>
                            </div>
                        </dl>
                        <!--
                            qr_code_path is nullable and currently unset — the
                            placeholder is the expected state, not a failure.
                        -->
                        <div
                            v-if="selectedPayment?.qr_code_url"
                            class="size-32 shrink-0 overflow-hidden rounded-xl border border-sf-line bg-white"
                        >
                            <img
                                :src="selectedPayment.qr_code_url"
                                :alt="`${selectedPayment.name} payment QR`"
                                class="size-full object-contain p-1"
                            />
                        </div>
                        <div
                            v-else
                            class="grid size-32 shrink-0 place-items-center gap-1 rounded-xl border border-dashed border-sf-line-strong bg-white text-sf-subtle"
                        >
                            <QrCode class="size-10" />
                            <span class="text-xs">QR coming soon</span>
                        </div>
                    </div>
                </section>

                <section>
                    <h2 class="font-display text-xl font-semibold text-sf-ink">
                        Upload Proof of Payment
                        <span class="text-sf-rose-deep">*</span>
                    </h2>
                    <label
                        class="mt-5 flex cursor-pointer flex-col items-center gap-3 rounded-xl border-2 border-dashed px-6 py-12 text-center transition-colors duration-200 ease-out"
                        :class="
                            proofName
                                ? 'border-sf-success bg-sf-success/5 text-sf-success'
                                : 'border-sf-line-strong text-sf-subtle hover:border-sf-primary hover:text-sf-primary'
                        "
                    >
                        <component
                            :is="proofName ? Check : Upload"
                            class="size-8"
                        />
                        <span class="text-[15px]">
                            {{
                                proofName
                                    ? `${proofName} attached`
                                    : 'Click to upload screenshot — GCash / Bank transfer receipt'
                            }}
                        </span>
                        <input
                            type="file"
                            accept="image/*,.pdf"
                            class="sr-only"
                            @change="onProof"
                        />
                    </label>
                </section>

                <section>
                    <h2 class="font-display text-xl font-semibold text-sf-ink">
                        Notes
                    </h2>
                    <textarea
                        v-model="form.notes"
                        rows="4"
                        :class="fieldClass"
                        class="mt-5 resize-y"
                        placeholder="Delivery instructions, preferred contact time…"
                    />
                </section>
            </div>

            <aside class="lg:sticky lg:top-28 lg:self-start">
                <div class="rounded-2xl border border-sf-line bg-sf-tint p-7">
                    <h2 class="font-display text-xl font-semibold text-sf-ink">
                        Order Summary
                    </h2>

                    <div
                        v-if="lines.length === 0"
                        class="mt-5 text-[15px] text-sf-muted italic"
                    >
                        Your cart is empty —
                        <Link
                            :href="catalog()"
                            class="font-semibold text-sf-primary not-italic hover:text-sf-primary-hover"
                            >add a peptide</Link
                        >
                        before checking out.
                    </div>

                    <div v-else class="mt-5 flex flex-col gap-4">
                        <div
                            v-for="line in lines"
                            :key="line.variant_id"
                            class="flex items-center gap-3"
                        >
                            <span
                                class="relative block size-20 shrink-0 overflow-hidden rounded-lg border border-sf-line bg-white"
                            >
                                <img
                                    v-if="line.image_url"
                                    :src="line.image_url"
                                    :alt="line.product_name"
                                    class="absolute inset-0 size-full rounded-lg object-cover"
                                />
                                <FlaskConical
                                    v-else
                                    class="absolute inset-0 m-auto size-7 text-sf-primary/35"
                                />
                            </span>
                            <span class="min-w-0 flex-1">
                                <span
                                    class="block truncate text-[15px] font-medium text-sf-ink"
                                    >{{ line.product_name }}</span
                                >
                                <span class="block text-sm text-sf-subtle"
                                    >{{ line.variant_label }} ×
                                    {{ line.quantity }}</span
                                >
                            </span>
                            <span class="font-medium text-sf-ink">
                                {{ formatPrice(line.line_total) }}
                            </span>
                        </div>
                    </div>

                    <div
                        class="mt-6 flex flex-col gap-3 border-t border-sf-line-strong pt-5 text-[15px]"
                    >
                        <div class="flex justify-between text-sf-muted">
                            <span>Subtotal</span>
                            <span class="font-medium text-sf-ink">{{
                                formatPrice(subtotal)
                            }}</span>
                        </div>
                        <div class="flex justify-between text-sf-muted">
                            <span>Shipping</span>
                            <span
                                v-if="selectedRegion"
                                class="font-medium text-sf-ink"
                                >{{ formatPrice(shipping) }}</span
                            >
                            <span v-else class="italic">Select a region</span>
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
                            >{{ formatPrice(total) }}</span
                        >
                    </div>

                    <button
                        type="submit"
                        :disabled="!ready || form.processing"
                        class="mt-6 w-full rounded-full bg-sf-primary px-8 py-4 font-display text-base font-medium text-white transition-colors duration-200 ease-out hover:bg-sf-primary-deep focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-sf-primary disabled:cursor-not-allowed disabled:opacity-40"
                    >
                        {{ form.processing ? 'Placing order…' : 'Place order' }}
                    </button>

                    <p
                        v-if="missing.length"
                        class="mt-3 text-sm text-sf-subtle"
                    >
                        Still needed: {{ missing.join(', ') }}.
                    </p>
                    <p
                        v-for="(message, field) in form.errors"
                        :key="field"
                        role="alert"
                        class="mt-2 text-sm text-sf-rose-deep"
                    >
                        {{ message }}
                    </p>
                    <p class="mt-3 text-center text-xs text-sf-subtle italic">
                        Orders are verified manually before dispatch.
                    </p>
                </div>
            </aside>
        </form>
    </div>
</template>
