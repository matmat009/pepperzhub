<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import {
    AlertTriangle,
    ArrowRight,
    ChevronDown,
    ClipboardList,
    Diamond,
    FlaskConical,
    Package,
    RefreshCcw,
    RotateCcw,
    Search,
    ShieldCheck,
    Snowflake,
    Syringe,
    Thermometer,
} from '@lucide/vue';
import { computed, ref } from 'vue';
import type { LabeledEntry } from '@/pages/admin/products/all-products/types';
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
const searchQuery = ref('');
const openProtocolId = ref<number | null>(props.products[0]?.id ?? null);

const categoryTabs = computed(() => ['All', ...props.categories]);

const filtered = computed(() => {
    const query = searchQuery.value.trim().toLocaleLowerCase();

    return props.products.filter((product) => {
        const matchesCategory =
            activeCategory.value === 'All' ||
            product.category === activeCategory.value;
        const matchesSearch =
            query === '' || product.name.toLocaleLowerCase().includes(query);

        return matchesCategory && matchesSearch;
    });
});

const protocolCount = computed(() => {
    const count = filtered.value.length;

    return `${count} ${count === 1 ? 'protocol' : 'protocols'}`;
});

const categoryCount = (category: string) =>
    category === 'All'
        ? props.products.length
        : props.products.filter((product) => product.category === category)
              .length;

const clearFilters = () => {
    activeCategory.value = 'All';
    searchQuery.value = '';
};

const toggleProtocol = (productId: number) => {
    openProtocolId.value =
        openProtocolId.value === productId ? null : productId;
};

/** Only the fields this product actually carries. */
const summary = (product: ProtocolProduct) =>
    [
        { label: 'Dosage', value: product.dosage },
        { label: 'Frequency', value: product.frequency },
        { label: 'Duration', value: product.duration },
    ].filter((row) => row.value !== '');

const inlineSummary = (product: ProtocolProduct) =>
    summary(product)
        .map((row) => row.value)
        .join(' · ');

/**
 * Gives real categories a stable brand treatment without assuming category
 * names or coupling the page to today's catalogue taxonomy.
 */
const categoryTone = (category: string) => {
    const index = Math.max(props.categories.indexOf(category), 0) % 3;

    if (index === 1) {
        return 'border-sf-rose-line bg-sf-rose-tint text-sf-rose-deep';
    }

    if (index === 2) {
        return 'border-sf-line-strong bg-sf-surface text-sf-muted';
    }

    return 'border-sf-primary/20 bg-sf-primary/8 text-sf-primary';
};

/**
 * Fixed page copy, not admin-editable — general handling guidance that applies
 * to every compound. Anything product-specific comes from the payload below.
 */
const INJECTION_GUIDELINES = [
    {
        title: 'Reconstitution',
        icon: FlaskConical,
        copy: 'Reconstitute with bacteriostatic water, running the stream down the vial wall rather than onto the powder.',
    },
    {
        title: 'Gentle handling',
        icon: RefreshCcw,
        copy: 'Swirl gently until dissolved. Never shake — agitation damages the peptide chain.',
    },
    {
        title: 'Sterile handling',
        icon: ShieldCheck,
        copy: 'Swab the vial stopper and the site with alcohol before every draw.',
    },
    {
        title: 'Single use',
        icon: Syringe,
        copy: 'Use a fresh sterile syringe each time, and dispose of it in a sharps container.',
    },
    {
        title: 'Site rotation',
        icon: RotateCcw,
        copy: 'Rotate sites so the same area is not used twice in succession.',
    },
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

    <section
        class="relative isolate flex w-full flex-col items-center px-5 pt-11 pb-13 text-center sm:px-10 sm:pt-14 sm:pb-15"
    >
        <div
            aria-hidden="true"
            class="pointer-events-none absolute inset-x-0 -top-24 -bottom-px -z-10 bg-[linear-gradient(125deg,var(--color-sf-hero-blue)_0%,#fff_48%,var(--color-sf-hero-rose)_100%)]"
        />

        <div class="mx-auto flex w-full max-w-[860px] flex-col items-center">
            <p
                class="text-[11px] font-semibold tracking-[0.3em] text-sf-primary uppercase"
            >
                Research reference
            </p>
            <h1
                class="mt-3 font-display text-[clamp(2.55rem,5vw,4rem)] leading-[1.08] font-medium tracking-[-0.025em] text-balance text-sf-ink"
            >
                Protocols
            </h1>
            <p
                class="mt-4 max-w-[720px] text-[15px] leading-[1.75] text-pretty text-sf-muted sm:text-base"
            >
                Handling and dosage reference for the compounds we supply. Only
                products with published protocol details appear here.
            </p>

            <div
                class="mt-7 flex w-full max-w-[760px] items-start gap-3 rounded-lg border border-sf-rose-line bg-sf-rose-tint/55 px-4 py-3.5 text-left sm:px-5"
                role="note"
            >
                <AlertTriangle
                    class="mt-0.5 size-4.5 shrink-0 text-sf-rose-deep"
                    aria-hidden="true"
                />
                <div>
                    <p class="text-sm font-semibold text-sf-ink">
                        Research use only
                    </p>
                    <p class="mt-1 text-[13px] leading-[1.6] text-sf-text">
                        For laboratory research use only. Not for human
                        consumption.
                    </p>
                </div>
            </div>
        </div>
    </section>

    <section class="bg-white px-5 py-12 sm:px-10 sm:py-14 lg:py-16">
        <div
            class="mx-auto grid w-full max-w-[1180px] gap-10 lg:grid-cols-[270px_minmax(0,1fr)] lg:items-start xl:gap-10"
        >
            <aside
                class="grid gap-4 lg:sticky lg:top-24"
                aria-label="Reference guidance"
            >
                <section
                    class="rounded-xl border border-sf-line-strong bg-white p-5"
                >
                    <h2
                        class="font-display text-[17px] font-semibold text-sf-ink"
                    >
                        Injection Guidelines
                    </h2>

                    <div class="mt-3 divide-y divide-sf-line">
                        <div
                            v-for="guideline in INJECTION_GUIDELINES"
                            :key="guideline.title"
                            class="grid grid-cols-[20px_minmax(0,1fr)] gap-3 py-3.5 first:pt-2 last:pb-1"
                        >
                            <component
                                :is="guideline.icon"
                                class="mt-0.5 size-4 text-sf-primary"
                                aria-hidden="true"
                            />
                            <div class="min-w-0">
                                <h3
                                    class="text-[13px] font-semibold text-sf-ink"
                                >
                                    {{ guideline.title }}
                                </h3>
                                <p
                                    class="mt-1 text-[12px] leading-[1.6] text-sf-muted"
                                >
                                    {{ guideline.copy }}
                                </p>
                            </div>
                        </div>
                    </div>
                </section>

                <section
                    class="rounded-xl border border-sf-line-strong bg-white p-5"
                >
                    <div class="flex items-center gap-2.5">
                        <Package
                            class="size-4 text-sf-primary"
                            aria-hidden="true"
                        />
                        <h2
                            class="font-display text-[17px] font-semibold text-sf-ink"
                        >
                            Storage Guidelines
                        </h2>
                    </div>

                    <div
                        class="mt-4 rounded-lg border border-sf-primary/20 bg-sf-primary/8 p-4"
                    >
                        <div class="flex items-center gap-2 text-sf-primary">
                            <Snowflake class="size-4" aria-hidden="true" />
                            <h3
                                class="text-[10px] font-semibold tracking-[0.18em] uppercase"
                            >
                                Lyophilised
                            </h3>
                        </div>
                        <p class="mt-2 text-[12px] leading-[1.65] text-sf-text">
                            {{ STORAGE_GUIDELINES[0] }}
                        </p>
                    </div>

                    <div
                        class="mt-3 rounded-lg border border-sf-rose-line bg-sf-rose-tint/70 p-4"
                    >
                        <div class="flex items-center gap-2 text-sf-rose-deep">
                            <Thermometer class="size-4" aria-hidden="true" />
                            <h3
                                class="text-[10px] font-semibold tracking-[0.18em] uppercase"
                            >
                                Reconstituted
                            </h3>
                        </div>
                        <ul class="mt-2 grid gap-2">
                            <li
                                v-for="line in STORAGE_GUIDELINES.slice(1, 3)"
                                :key="line"
                                class="text-[12px] leading-[1.65] text-sf-text"
                            >
                                {{ line }}
                            </li>
                        </ul>
                    </div>

                    <p
                        class="mt-4 border-t border-sf-line pt-3 text-[12px] leading-[1.6] text-sf-muted"
                    >
                        {{ STORAGE_GUIDELINES[3] }}
                    </p>
                </section>
            </aside>

            <div class="min-w-0">
                <div
                    class="flex flex-col gap-5 sm:flex-row sm:items-end sm:justify-between"
                >
                    <div>
                        <h2
                            class="font-display text-[26px] font-semibold tracking-[-0.02em] text-sf-ink"
                        >
                            Product protocols
                        </h2>
                        <p
                            class="mt-1 text-sm text-sf-subtle"
                            aria-live="polite"
                        >
                            {{ protocolCount }}
                        </p>
                    </div>

                    <label class="relative block w-full sm:w-[280px]">
                        <span class="sr-only">Search product protocols</span>
                        <Search
                            class="pointer-events-none absolute top-1/2 left-4 size-4 -translate-y-1/2 text-sf-subtle"
                            aria-hidden="true"
                        />
                        <input
                            v-model="searchQuery"
                            type="search"
                            placeholder="Search a product…"
                            class="h-11 w-full rounded-full border border-sf-line-strong bg-white pr-4 pl-11 text-sm text-sf-ink transition-colors outline-none placeholder:text-sf-subtle focus:border-sf-primary focus:ring-2 focus:ring-sf-primary/15"
                        />
                    </label>
                </div>

                <div
                    v-if="products.length"
                    class="mt-5 flex flex-wrap gap-2 border-b border-sf-line pb-5"
                    aria-label="Filter protocols by category"
                >
                    <button
                        v-for="category in categoryTabs"
                        :key="category"
                        type="button"
                        :aria-pressed="activeCategory === category"
                        class="inline-flex min-h-9 items-center gap-2 rounded-full border px-4 py-2 text-[13px] transition-colors duration-200 ease-out focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-sf-primary"
                        :class="
                            activeCategory === category
                                ? 'border-sf-primary bg-sf-primary font-semibold text-white'
                                : 'border-sf-line-strong bg-white text-sf-text hover:border-sf-primary/40 hover:text-sf-primary'
                        "
                        @click="activeCategory = category"
                    >
                        <span>{{ category }}</span>
                        <span
                            class="text-[11px]"
                            :class="
                                activeCategory === category
                                    ? 'text-white/80'
                                    : 'text-sf-subtle'
                            "
                        >
                            {{ categoryCount(category) }}
                        </span>
                    </button>
                </div>

                <div v-if="filtered.length" class="mt-5 grid gap-3">
                    <article
                        v-for="product in filtered"
                        :key="product.id"
                        class="overflow-hidden rounded-xl border bg-white transition-colors duration-200"
                        :class="
                            openProtocolId === product.id
                                ? 'border-sf-primary/70 shadow-[0_12px_32px_-24px_rgba(50,70,160,0.45)]'
                                : 'border-sf-line-strong hover:border-sf-primary/35'
                        "
                    >
                        <button
                            :id="`protocol-trigger-${product.id}`"
                            type="button"
                            class="flex w-full items-center gap-4 px-4 py-4 text-left focus-visible:outline-2 focus-visible:-outline-offset-2 focus-visible:outline-sf-primary sm:px-5"
                            :aria-expanded="openProtocolId === product.id"
                            :aria-controls="`protocol-panel-${product.id}`"
                            @click="toggleProtocol(product.id)"
                        >
                            <div class="min-w-0 flex-1">
                                <h3
                                    class="font-display text-[17px] leading-snug font-semibold text-sf-ink"
                                >
                                    {{ product.name }}
                                </h3>
                                <div
                                    class="mt-1.5 flex min-w-0 flex-wrap items-center gap-x-2 gap-y-1.5"
                                >
                                    <span
                                        v-if="product.category"
                                        class="rounded-full border px-2.5 py-1 text-[9px] font-semibold tracking-[0.14em] uppercase"
                                        :class="categoryTone(product.category)"
                                    >
                                        {{ product.category }}
                                    </span>
                                    <span
                                        v-if="inlineSummary(product)"
                                        class="min-w-0 text-[12px] leading-relaxed text-sf-muted"
                                    >
                                        {{ inlineSummary(product) }}
                                    </span>
                                </div>
                            </div>

                            <span
                                class="grid size-9 shrink-0 place-items-center rounded-full bg-sf-tint text-sf-primary transition-colors duration-200"
                                :class="
                                    openProtocolId === product.id
                                        ? 'bg-sf-primary text-white'
                                        : ''
                                "
                                aria-hidden="true"
                            >
                                <ChevronDown
                                    class="size-4 transition-transform duration-200"
                                    :class="
                                        openProtocolId === product.id
                                            ? 'rotate-180'
                                            : ''
                                    "
                                />
                            </span>
                        </button>

                        <div
                            v-if="openProtocolId === product.id"
                            :id="`protocol-panel-${product.id}`"
                            role="region"
                            :aria-labelledby="`protocol-trigger-${product.id}`"
                            class="border-t border-sf-primary/25 px-4 pt-4 pb-5 sm:px-5 sm:pt-5 sm:pb-6"
                        >
                            <dl
                                v-if="summary(product).length"
                                class="grid gap-3 sm:grid-cols-2 xl:grid-cols-3"
                            >
                                <div
                                    v-for="row in summary(product)"
                                    :key="row.label"
                                    class="rounded-lg border border-sf-line bg-sf-tint/65 px-4 py-3"
                                >
                                    <dt
                                        class="text-[10px] font-semibold tracking-[0.16em] text-sf-primary uppercase"
                                    >
                                        {{ row.label }}
                                    </dt>
                                    <dd
                                        class="mt-1.5 text-[13px] leading-relaxed font-medium text-sf-ink"
                                    >
                                        {{ row.value }}
                                    </dd>
                                </div>
                            </dl>

                            <div
                                v-if="product.protocol_notes.length"
                                :class="summary(product).length ? 'mt-5' : ''"
                            >
                                <h4
                                    class="text-[10px] font-semibold tracking-[0.16em] text-sf-muted uppercase"
                                >
                                    Protocol Notes
                                </h4>
                                <ul class="mt-2.5 grid gap-2">
                                    <li
                                        v-for="(
                                            note, index
                                        ) in product.protocol_notes"
                                        :key="index"
                                        class="flex min-w-0 gap-3 text-[13px] leading-[1.7] text-sf-text"
                                    >
                                        <Diamond
                                            class="mt-2 size-2.5 shrink-0 fill-sf-rose text-sf-rose"
                                            aria-hidden="true"
                                        />
                                        <span class="min-w-0 break-words">{{
                                            note
                                        }}</span>
                                    </li>
                                </ul>
                            </div>

                            <div
                                v-if="product.storage_instructions.length"
                                class="mt-5 rounded-lg border border-sf-primary/15 bg-sf-primary/6 p-4"
                            >
                                <div
                                    class="flex items-center gap-2 text-sf-primary"
                                >
                                    <Snowflake
                                        class="size-4 shrink-0"
                                        aria-hidden="true"
                                    />
                                    <h4
                                        class="text-[10px] font-semibold tracking-[0.16em] uppercase"
                                    >
                                        Storage
                                    </h4>
                                </div>
                                <ul class="mt-2 grid gap-2">
                                    <li
                                        v-for="entry in product.storage_instructions"
                                        :key="entry.id"
                                        class="min-w-0 text-[13px] leading-[1.65] text-sf-text"
                                    >
                                        <span
                                            v-if="entry.label"
                                            class="font-semibold text-sf-ink"
                                        >
                                            {{ entry.label }}:
                                        </span>
                                        <span class="break-words">
                                            {{ entry.value }}</span
                                        >
                                    </li>
                                </ul>
                            </div>

                            <Link
                                :href="show(product.slug)"
                                class="mt-5 inline-flex min-h-10 items-center gap-2 text-[13px] font-semibold text-sf-primary transition-colors duration-200 ease-out hover:text-sf-primary-hover focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-sf-primary"
                            >
                                View product
                                <ArrowRight class="size-4" aria-hidden="true" />
                            </Link>
                        </div>
                    </article>
                </div>

                <div
                    v-else
                    class="mt-5 rounded-xl border border-dashed border-sf-rule px-6 py-16 text-center"
                >
                    <span
                        class="mx-auto grid size-11 place-items-center rounded-full bg-sf-tint text-sf-primary"
                        aria-hidden="true"
                    >
                        <Search class="size-5" />
                    </span>
                    <p
                        class="mt-4 font-display text-xl font-semibold text-sf-ink"
                    >
                        {{
                            products.length
                                ? 'No matching protocols found.'
                                : 'No product protocols published yet.'
                        }}
                    </p>
                    <p
                        class="mx-auto mt-2 max-w-md text-[14px] leading-relaxed text-sf-muted"
                    >
                        {{
                            products.length
                                ? 'Try another product name or category.'
                                : 'The general guidance above applies to every compound we supply.'
                        }}
                    </p>
                    <button
                        v-if="products.length"
                        type="button"
                        class="mt-5 min-h-10 rounded-full border border-sf-primary px-5 py-2 text-sm font-semibold text-sf-primary transition-colors hover:bg-sf-tint focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-sf-primary"
                        @click="clearFilters"
                    >
                        Clear filters
                    </button>
                </div>

                <div
                    class="mt-8 flex items-start gap-3 border-t border-sf-line pt-5 text-[12px] leading-relaxed text-sf-subtle italic"
                >
                    <ClipboardList
                        class="mt-0.5 size-4 shrink-0"
                        aria-hidden="true"
                    />
                    <p>
                        Protocol details are published per product by
                        PepperzzHub and are supplied for reference only.
                    </p>
                </div>
            </div>
        </div>
    </section>
</template>
