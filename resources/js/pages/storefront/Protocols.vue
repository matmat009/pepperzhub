<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { ClipboardList, FlaskConical, Package } from '@lucide/vue';
import { computed, ref } from 'vue';
import type { LabeledEntry } from '@/pages/admin/products/all-products/types';
import { home } from '@/routes';
import { show } from '@/routes/storefront/products';

/**
 * Payload from App\Http\Controllers\Storefront\ProtocolController.
 *
 * A slice of the product rather than the catalogue shape — this page shows no
 * price, image or format — so it is typed here instead of reusing `Product`.
 * Every entry has at least one protocol field set; the rest render nothing
 * rather than a placeholder line.
 */
type ProtocolProduct = {
    id: number;
    name: string;
    slug: string;
    category: string;
    dosage: string;
    frequency: string;
    duration: string;
    protocol_notes: string[];
    storage_instructions: LabeledEntry[];
};

const props = defineProps<{
    products: ProtocolProduct[];
    categories: string[];
}>();

const activeCategory = ref('All');

const categoryTabs = computed(() => ['All', ...props.categories]);

// Client-side, the same as the catalogue's category filter.
const filtered = computed(() =>
    activeCategory.value === 'All'
        ? props.products
        : props.products.filter(
              (product) => product.category === activeCategory.value,
          ),
);

/** Only the fields this product actually carries. */
const summary = (product: ProtocolProduct) =>
    [
        { label: 'Dosage', value: product.dosage },
        { label: 'Frequency', value: product.frequency },
        { label: 'Duration', value: product.duration },
    ].filter((row) => row.value !== '');

/**
 * Fixed page copy, not admin-editable — general handling guidance that applies
 * to every compound. Anything product-specific comes from the payload below.
 */
const INJECTION_GUIDELINES = [
    'Reconstitute with bacteriostatic water, running the stream down the vial wall rather than onto the powder.',
    'Swirl gently until dissolved. Never shake — agitation damages the peptide chain.',
    'Swab the vial stopper and the site with alcohol before every draw.',
    'Use a fresh sterile syringe each time, and dispose of it in a sharps container.',
    'Rotate sites so the same area is not used twice in succession.',
];

const STORAGE_GUIDELINES = [
    'Keep lyophilised (powder) vials frozen for long-term storage, away from light.',
    'Refrigerate at 2–8°C once reconstituted, and keep the vial upright.',
    'Avoid repeatedly freezing and thawing a reconstituted vial.',
    'Where a compound lists its own storage instructions below, those take precedence.',
];
</script>

<template>
    <Head title="Protocols" />

    <div class="mx-auto w-full max-w-[1680px] px-5 pt-8 pb-24 sm:px-10">
        <div class="flex items-center gap-2 text-sm text-sf-subtle">
            <Link
                :href="home()"
                class="transition-colors duration-200 ease-out hover:text-sf-primary"
                >Home</Link
            >
            <span>/</span>
            <span class="text-sf-ink">Protocols</span>
        </div>

        <div class="mt-5 border-b border-sf-line pb-6">
            <h1
                class="font-display text-[34px] font-medium tracking-[-0.02em] text-sf-ink"
            >
                Protocols
            </h1>
            <p class="mt-3 max-w-3xl text-[15px] leading-[1.7] text-sf-muted">
                Handling and dosage reference for the compounds we supply. Only
                products with published protocol details appear here.
            </p>
            <p class="mt-2 text-sm text-sf-subtle italic">
                For laboratory research use only. Not for human consumption.
            </p>
        </div>

        <div class="mt-8 grid gap-6 lg:grid-cols-2">
            <section
                class="rounded-2xl border border-sf-line bg-sf-tint/60 p-6 sm:p-7"
            >
                <div class="flex items-center gap-3">
                    <span
                        class="flex size-9 shrink-0 items-center justify-center rounded-full bg-sf-primary/10 text-sf-primary"
                        aria-hidden="true"
                    >
                        <FlaskConical class="size-4" />
                    </span>
                    <h2 class="font-display text-xl font-semibold text-sf-ink">
                        Injection Guidelines
                    </h2>
                </div>
                <ul class="mt-4 grid gap-3">
                    <li
                        v-for="line in INJECTION_GUIDELINES"
                        :key="line"
                        class="flex gap-3 text-[15px] leading-[1.7] text-sf-text"
                    >
                        <span
                            class="mt-2.5 size-1.5 shrink-0 rounded-full bg-sf-primary"
                            aria-hidden="true"
                        />
                        {{ line }}
                    </li>
                </ul>
            </section>

            <section
                class="rounded-2xl border border-sf-line bg-sf-tint/60 p-6 sm:p-7"
            >
                <div class="flex items-center gap-3">
                    <span
                        class="flex size-9 shrink-0 items-center justify-center rounded-full bg-sf-primary/10 text-sf-primary"
                        aria-hidden="true"
                    >
                        <Package class="size-4" />
                    </span>
                    <h2 class="font-display text-xl font-semibold text-sf-ink">
                        Storage Guidelines
                    </h2>
                </div>
                <ul class="mt-4 grid gap-3">
                    <li
                        v-for="line in STORAGE_GUIDELINES"
                        :key="line"
                        class="flex gap-3 text-[15px] leading-[1.7] text-sf-text"
                    >
                        <span
                            class="mt-2.5 size-1.5 shrink-0 rounded-full bg-sf-primary"
                            aria-hidden="true"
                        />
                        {{ line }}
                    </li>
                </ul>
            </section>
        </div>

        <div v-if="products.length" class="mt-12">
            <div class="flex flex-wrap items-center gap-2">
                <button
                    v-for="category in categoryTabs"
                    :key="category"
                    type="button"
                    class="rounded-full border px-4 py-2 text-sm transition-colors duration-200 ease-out focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-sf-primary"
                    :class="
                        activeCategory === category
                            ? 'border-sf-primary bg-sf-primary/10 font-semibold text-sf-primary'
                            : 'border-sf-rule text-sf-text hover:text-sf-primary'
                    "
                    @click="activeCategory = category"
                >
                    {{ category }}
                </button>
            </div>

            <div v-if="filtered.length" class="mt-6 grid gap-5">
                <article
                    v-for="product in filtered"
                    :key="product.id"
                    class="rounded-2xl border border-sf-line bg-white p-6 sm:p-7"
                >
                    <div
                        class="flex flex-wrap items-start justify-between gap-3"
                    >
                        <div>
                            <h3
                                class="font-display text-xl font-semibold text-sf-ink"
                            >
                                {{ product.name }}
                            </h3>
                            <p
                                v-if="product.category"
                                class="mt-1 text-sm text-sf-subtle"
                            >
                                {{ product.category }}
                            </p>
                        </div>
                        <Link
                            :href="show(product.slug)"
                            class="text-sm font-medium text-sf-primary transition-colors duration-200 ease-out hover:text-sf-primary-hover"
                        >
                            View product
                        </Link>
                    </div>

                    <dl
                        v-if="summary(product).length"
                        class="mt-5 grid gap-4 sm:grid-cols-3"
                    >
                        <div v-for="row in summary(product)" :key="row.label">
                            <dt
                                class="text-xs tracking-[0.08em] text-sf-subtle uppercase"
                            >
                                {{ row.label }}
                            </dt>
                            <dd class="mt-1 text-[15px] text-sf-ink">
                                {{ row.value }}
                            </dd>
                        </div>
                    </dl>

                    <div v-if="product.protocol_notes.length" class="mt-5">
                        <h4
                            class="font-display text-[15px] font-semibold text-sf-ink"
                        >
                            Protocol Notes
                        </h4>
                        <ul class="mt-2 grid gap-2">
                            <li
                                v-for="(note, index) in product.protocol_notes"
                                :key="index"
                                class="flex gap-3 text-[15px] leading-[1.7] text-sf-text"
                            >
                                <span
                                    class="mt-2.5 size-1.5 shrink-0 rounded-full bg-sf-rose"
                                    aria-hidden="true"
                                />
                                {{ note }}
                            </li>
                        </ul>
                    </div>

                    <div
                        v-if="product.storage_instructions.length"
                        class="mt-5"
                    >
                        <h4
                            class="font-display text-[15px] font-semibold text-sf-ink"
                        >
                            Storage
                        </h4>
                        <ul class="mt-2 grid gap-2">
                            <li
                                v-for="entry in product.storage_instructions"
                                :key="entry.id"
                                class="flex gap-3 text-[15px] leading-[1.7] text-sf-text"
                            >
                                <span
                                    class="mt-2.5 size-1.5 shrink-0 rounded-full bg-sf-rose"
                                    aria-hidden="true"
                                />
                                <span>
                                    <span
                                        v-if="entry.label"
                                        class="font-medium text-sf-ink"
                                    >
                                        {{ entry.label }}:
                                    </span>
                                    {{ entry.value }}
                                </span>
                            </li>
                        </ul>
                    </div>
                </article>
            </div>

            <div
                v-else
                class="mt-6 rounded-2xl border border-dashed border-sf-rule px-8 py-20 text-center"
            >
                <p class="font-display text-xl font-semibold text-sf-ink">
                    No protocols in this category yet.
                </p>
                <p class="mt-2 text-[15px] text-sf-muted italic">
                    Pick another category to see what is published.
                </p>
            </div>
        </div>

        <div
            v-else
            class="mt-12 rounded-2xl border border-dashed border-sf-rule px-8 py-20 text-center"
        >
            <p class="font-display text-xl font-semibold text-sf-ink">
                No product protocols published yet.
            </p>
            <p class="mt-2 text-[15px] text-sf-muted italic">
                The general guidance above applies to every compound we supply.
            </p>
        </div>

        <div class="mt-10 flex items-center gap-3 text-sm text-sf-subtle">
            <ClipboardList class="size-4 shrink-0" aria-hidden="true" />
            <p>
                Protocol details are published per product by PepperzzHub and
                are supplied for reference only.
            </p>
        </div>
    </div>
</template>
