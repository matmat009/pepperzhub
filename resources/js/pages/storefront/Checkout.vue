<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { Check, FlaskConical, QrCode, Upload } from '@lucide/vue';
import { computed, reactive, ref, watch } from 'vue';
import { useStorefrontCart } from '@/composables/useStorefrontCart';
import { formatPrice } from '@/pages/admin/products/all-products/types';
import { home } from '@/routes';
import { confirmation } from '@/routes/storefront';
import { index as catalog } from '@/routes/storefront/products';

const { lines, subtotal, isEmpty } = useStorefrontCart();

/**
 * Courier → region and payment method → account details are both dependent
 * selects: picking the parent reveals the child, changing it resets the child.
 * Rates and account numbers come from the design bundle.
 */
const couriers = [
    {
        id: 'jnt',
        name: 'J&T Express',
        regions: [
            {
                id: 'luzvis',
                name: 'J&T – Luzon & Visayas',
                note: 'Standard pouch',
                rate: 150,
            },
            {
                id: 'mindanao-s',
                name: 'J&T – Mindanao (Small)',
                note: 'Max. 2 kits',
                rate: 100,
            },
            {
                id: 'mindanao-l',
                name: 'J&T – Mindanao (Large Pouch)',
                note: 'Min. 5 kits',
                rate: 200,
            },
        ],
    },
] as const;

const paymentMethods = [
    {
        id: 'gotyme',
        name: 'GOtyme Bank',
        details: [
            { label: 'Bank', value: 'GOtyme Bank' },
            { label: 'Account Number', value: '0012 3456 7890' },
            { label: 'Account Name', value: 'PepperzzHub Trading' },
        ],
    },
] as const;

const form = reactive({
    name: '',
    social: '',
    phone: '',
    street: '',
    barangay: '',
    city: '',
    province: '',
    zip: '',
    notes: '',
});

const courierId = ref('');
const regionId = ref('');
const paymentId = ref('');
const proofName = ref('');
const attempted = ref(false);

const selectedCourier = computed(() =>
    couriers.find((courier) => courier.id === courierId.value),
);

const selectedRegion = computed(() =>
    selectedCourier.value?.regions.find(
        (region) => region.id === regionId.value,
    ),
);

const selectedPayment = computed(() =>
    paymentMethods.find((method) => method.id === paymentId.value),
);

// Changing the courier invalidates whichever region was picked under the old one.
watch(courierId, () => {
    regionId.value = '';
});

const shipping = computed(() => selectedRegion.value?.rate ?? 0);
const total = computed(() => subtotal.value + shipping.value);

const requiredFilled = computed(() =>
    (
        [
            'name',
            'social',
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
    } else if (!regionId.value) {
        gaps.push('shipping region');
    }

    if (!paymentId.value) {
        gaps.push('payment method');
    }

    if (!proofName.value) {
        gaps.push('proof of payment');
    }

    return gaps;
});

const ready = computed(() => missing.value.length === 0 && !isEmpty.value);

const onProof = (event: Event) => {
    const input = event.target as HTMLInputElement;
    proofName.value = input.files?.[0]?.name ?? '';
};

/**
 * Nothing is persisted — there is no order schema yet. The form validates and
 * hands off to the confirmation screen, which renders its own dummy order.
 */
const placeOrder = () => {
    attempted.value = true;

    if (!ready.value) {
        return;
    }

    router.visit(confirmation().url);
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
                                v-model="form.social"
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
                        Select Courier Provider
                    </h2>
                    <label class="mt-5 flex flex-col gap-2">
                        <span class="text-sm font-medium text-sf-text"
                            >Courier
                            <span class="text-sf-rose-deep">*</span></span
                        >
                        <select v-model="courierId" :class="fieldClass">
                            <option value="">Choose a courier…</option>
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
                            Choose Shipping Region
                        </div>
                        <div class="mt-3 flex flex-col gap-3">
                            <label
                                v-for="region in selectedCourier.regions"
                                :key="region.id"
                                class="flex cursor-pointer items-center justify-between gap-4 rounded-xl border px-5 py-4 transition-colors duration-200 ease-out"
                                :class="
                                    regionId === region.id
                                        ? 'border-sf-primary bg-sf-tint'
                                        : 'border-sf-line-strong bg-white hover:border-sf-primary/50'
                                "
                            >
                                <span class="flex items-center gap-3">
                                    <input
                                        v-model="regionId"
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
                        <select v-model="paymentId" :class="fieldClass">
                            <option value="">Choose a payment method…</option>
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
                        <div
                            class="grid size-32 shrink-0 place-items-center gap-1 rounded-xl border border-dashed border-sf-line-strong bg-white text-sf-subtle"
                        >
                            <QrCode class="size-10" />
                            <span class="text-xs">Payment QR</span>
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
                        v-if="isEmpty"
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
                            :key="line.key"
                            class="flex items-center gap-3"
                        >
                            <span
                                class="grid size-14 shrink-0 place-items-center overflow-hidden rounded-lg border border-sf-line bg-white"
                            >
                                <img
                                    v-if="line.imageUrl"
                                    :src="line.imageUrl"
                                    :alt="line.productName"
                                    class="size-full object-contain"
                                />
                                <FlaskConical
                                    v-else
                                    class="size-5 text-sf-primary/35"
                                />
                            </span>
                            <span class="min-w-0 flex-1">
                                <span
                                    class="block truncate text-[15px] font-medium text-sf-ink"
                                    >{{ line.productName }}</span
                                >
                                <span class="block text-sm text-sf-subtle"
                                    >{{ line.variantLabel }} ×
                                    {{ line.quantity }}</span
                                >
                            </span>
                            <span class="font-medium text-sf-ink">
                                {{
                                    formatPrice(line.unitPrice * line.quantity)
                                }}
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
                        :disabled="!ready"
                        class="mt-6 w-full rounded-full bg-sf-primary px-8 py-4 font-display text-base font-medium text-white transition-colors duration-200 ease-out hover:bg-sf-primary-deep focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-sf-primary disabled:cursor-not-allowed disabled:opacity-40"
                    >
                        Place order
                    </button>

                    <p
                        v-if="attempted && missing.length"
                        role="alert"
                        class="mt-3 text-sm text-sf-rose-deep"
                    >
                        Still needed: {{ missing.join(', ') }}.
                    </p>
                    <p class="mt-3 text-center text-xs text-sf-subtle italic">
                        Orders are verified manually before dispatch.
                    </p>
                </div>
            </aside>
        </form>
    </div>
</template>
