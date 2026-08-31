<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import {
    ArrowRight,
    HeartPulse,
    ShieldCheck,
    TestTube,
    Trophy,
} from '@lucide/vue';
import { computed, ref } from 'vue';
import ProductCard from '@/components/storefront/ProductCard.vue';
import type { Product } from '@/pages/admin/products/all-products/types';
import { index as catalog } from '@/routes/storefront/products';

const props = defineProps<{
    featured: Product[];
    categories: string[];
}>();

const activeTab = ref('All');

const tabs = computed(() => ['All', ...props.categories]);

const shown = computed(() =>
    activeTab.value === 'All'
        ? props.featured
        : props.featured.filter(
              (product) => product.category === activeTab.value,
          ),
);

const usps = [
    {
        icon: ShieldCheck,
        title: 'Premium Quality',
        copy: 'Only the highest grade peptides.',
        tone: 'text-sf-rose-deep',
    },
    {
        icon: TestTube,
        title: 'Lab Tested & Verified',
        copy: 'Purity, potency and safety you can trust.',
        tone: 'text-sf-primary',
    },
    {
        icon: Trophy,
        title: 'Trusted Source',
        copy: 'Reliable products from a trusted network.',
        tone: 'text-sf-rose-deep',
    },
    {
        icon: HeartPulse,
        title: 'Supporting Your Best Version',
        copy: 'Quality peptides for your wellness journey.',
        tone: 'text-sf-primary',
    },
];

const email = ref('');
const subscribed = ref(false);

const subscribe = () => {
    if (email.value.trim()) {
        subscribed.value = true;
        email.value = '';
    }
};
</script>

<template>
    <Head title="Peptides that work" />

    <section
        class="relative isolate flex w-full flex-col items-center px-5 pt-16 pb-24 text-center sm:px-10"
    >
        <!--
            The wash runs behind the navbar as well as the hero, which is why
            it is a layer offset upward rather than a background on the
            section: the header lives in StorefrontLayout, above this in the
            DOM. -top-24 clears the header's 14px + 64px pill + 8px padding
            with room to spare, and the sticky pill (z-30) still paints over it.
        -->
        <div
            aria-hidden="true"
            class="pointer-events-none absolute inset-x-0 -top-24 -bottom-px -z-10 bg-[linear-gradient(125deg,var(--color-sf-hero-blue)_0%,#fff_48%,var(--color-sf-hero-rose)_100%)]"
        />

        <div class="mx-auto flex w-full max-w-[1680px] flex-col items-center">
            <img
                src="/images/branding/pepperzhub-emblem.png"
                alt="PepperzzHub"
                class="w-[260px] max-w-full"
            />

            <div class="mt-7 flex flex-col items-center gap-4">
                <div
                    class="font-display text-5xl leading-none font-medium tracking-[-0.015em]"
                >
                    <span class="text-sf-rose">Pepperzz</span
                    ><span class="text-sf-primary">Hub</span>
                </div>

                <div class="flex w-[300px] max-w-full items-center gap-3.5">
                    <span class="h-px flex-1 bg-sf-rule" />
                    <span class="size-2.5 rotate-45 bg-sf-rose" />
                    <span class="h-px flex-1 bg-sf-rule" />
                </div>

                <div
                    class="font-body text-sm font-medium tracking-[0.42em] text-sf-muted uppercase"
                >
                    Peptide Solutions
                </div>
            </div>

            <h1
                class="mt-11 font-display text-[clamp(2.5rem,5.5vw,5rem)] leading-[1.08] font-medium tracking-[-0.02em] text-balance text-sf-ink"
            >
                Better Science.
                <span class="text-sf-primary italic">Better you.</span>
            </h1>
            <p class="mt-5 text-2xl leading-[1.6] text-sf-muted italic">
                Peptides that work. Results that matter.
            </p>

            <Link
                :href="catalog()"
                class="mt-10 inline-flex items-center gap-3 rounded-full bg-sf-primary px-10 py-4 text-[17px] font-medium text-white shadow-[0_8px_22px_rgba(50,70,160,0.28)] transition duration-200 ease-out hover:-translate-y-0.5 hover:bg-sf-primary-deep hover:shadow-[0_12px_30px_rgba(50,70,160,0.38)]"
            >
                Browse peptides
                <ArrowRight class="size-[17px]" />
            </Link>

            <div
                class="mt-9 flex flex-wrap items-center justify-center gap-x-6 gap-y-3 text-[15px] text-sf-muted"
            >
                <span class="flex items-center gap-2.5">
                    <ShieldCheck class="size-[17px] text-sf-primary" />
                    Premium Quality
                </span>
                <span class="hidden h-4.5 w-px bg-sf-line-strong sm:block" />
                <span class="flex items-center gap-2.5">
                    <TestTube class="size-[17px] text-sf-primary" />
                    Lab Tested
                </span>
            </div>
        </div>
    </section>

    <section
        class="relative z-5 mx-auto -mt-12 w-full max-w-[1680px] px-5 sm:px-10"
    >
        <div
            class="grid grid-cols-1 gap-11 rounded-2xl border border-sf-line bg-white px-6 py-11 shadow-[0_18px_44px_rgba(30,35,60,0.09)] sm:grid-cols-2 xl:grid-cols-4"
        >
            <div
                v-for="(usp, index) in usps"
                :key="usp.title"
                class="flex flex-col items-center gap-4 px-7 text-center xl:border-r xl:border-sf-line xl:last:border-r-0"
                :class="index % 2 === 0 ? 'sm:border-r sm:border-sf-line' : ''"
            >
                <component
                    :is="usp.icon"
                    class="size-13 stroke-[1.5]"
                    :class="usp.tone"
                />
                <div
                    class="font-display text-base font-semibold tracking-[0.05em] uppercase"
                    :class="usp.tone"
                >
                    {{ usp.title }}
                </div>
                <div class="text-[15px] leading-[1.55] text-sf-text italic">
                    {{ usp.copy }}
                </div>
            </div>
        </div>
    </section>

    <section
        class="mx-auto flex w-full max-w-[1680px] flex-col items-center px-5 pt-24 pb-8 sm:px-10"
    >
        <h2
            class="text-center font-display text-[42px] font-medium tracking-[-0.02em] text-sf-ink"
        >
            Explore <span class="text-sf-primary italic">Our Peptides</span>
        </h2>
        <p class="mt-3.5 text-center text-[17px] text-sf-muted italic">
            High purity. Lab verified. Trusted by professionals.
        </p>

        <div
            v-if="tabs.length > 1"
            class="mt-8 flex flex-wrap justify-center gap-2"
        >
            <button
                v-for="tab in tabs"
                :key="tab"
                type="button"
                class="rounded-full border px-[22px] py-2.5 font-display text-[15px] transition duration-200 ease-out hover:border-sf-primary focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-sf-primary"
                :class="
                    activeTab === tab
                        ? 'border-sf-primary bg-sf-primary font-semibold text-white'
                        : 'border-sf-line-strong bg-white font-normal text-sf-text'
                "
                @click="activeTab = tab"
            >
                {{ tab }}
            </button>
        </div>

        <div
            v-if="shown.length"
            class="mt-11 grid w-full grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4"
        >
            <ProductCard
                v-for="(product, i) in shown"
                :key="product.id"
                :product="product"
                :index="i"
            />
        </div>
        <p v-else class="mt-11 text-[15px] text-sf-muted italic">
            No featured products in this category yet.
        </p>

        <Link
            :href="catalog()"
            class="mt-11 inline-flex items-center gap-2.5 rounded-full border-2 border-sf-primary bg-white px-9 py-3.5 font-display text-base font-medium text-sf-primary transition-colors duration-200 ease-out hover:bg-sf-tint"
        >
            View all products
            <ArrowRight class="size-4" />
        </Link>
    </section>

    <section class="mx-auto w-full max-w-[1680px] px-5 pt-16 sm:px-10">
        <div
            class="flex flex-wrap items-center gap-13 rounded-2xl bg-[linear-gradient(105deg,oklch(0.93_0.032_240)_0%,oklch(0.95_0.032_20)_100%)] px-14 py-13"
        >
            <div class="min-w-[280px] flex-1">
                <div class="text-base text-sf-primary italic">
                    Why Choose PepperzzHub?
                </div>
                <h2
                    class="mt-3 font-display text-4xl leading-[1.25] font-medium tracking-[-0.02em] text-sf-ink"
                >
                    Science you can trust.<br />Results you can feel.
                </h2>
            </div>
            <div
                class="grid min-w-[320px] flex-[1.4] grid-cols-[repeat(auto-fit,minmax(130px,1fr))] gap-2"
            >
                <div
                    v-for="stat in [
                        {
                            icon: ShieldCheck,
                            value: '100%',
                            label: 'Lab Tested',
                        },
                        { icon: TestTube, value: '99%+', label: 'Purity' },
                        { icon: Trophy, value: '5★', label: 'Rated Service' },
                        { icon: HeartPulse, value: '24/7', label: 'Support' },
                    ]"
                    :key="stat.label"
                    class="flex flex-col items-center gap-2.5 px-3 py-2.5 text-center"
                >
                    <component :is="stat.icon" class="size-6 text-sf-primary" />
                    <span
                        class="font-display text-[26px] font-semibold text-sf-ink"
                    >
                        {{ stat.value }}
                    </span>
                    <span class="text-sm text-sf-text">{{ stat.label }}</span>
                </div>
            </div>
        </div>
    </section>

    <section class="mx-auto w-full max-w-[1680px] px-5 pt-15 pb-24 sm:px-10">
        <div
            class="grid grid-cols-1 overflow-hidden rounded-2xl border border-sf-line bg-white shadow-[0_16px_40px_rgba(30,35,60,0.08)] lg:grid-cols-2"
        >
            <div
                class="grid min-h-[280px] place-items-center bg-[linear-gradient(135deg,oklch(0.94_0.02_240),oklch(0.96_0.025_14))]"
            >
                <img
                    src="/images/branding/pepperzhub-emblem.png"
                    alt=""
                    aria-hidden="true"
                    class="w-[220px] max-w-[70%] opacity-90"
                />
            </div>
            <div
                class="flex flex-col items-start justify-center gap-3 px-13 py-12"
            >
                <h2
                    class="font-display text-3xl font-semibold tracking-[-0.02em] text-sf-ink"
                >
                    Stay Updated
                </h2>
                <p class="text-base leading-[1.6] text-sf-muted italic">
                    Get the latest updates, new product drops, and exclusive
                    offers.
                </p>
                <form
                    class="mt-3 flex w-full flex-wrap gap-2.5"
                    @submit.prevent="subscribe"
                >
                    <input
                        v-model="email"
                        type="email"
                        required
                        placeholder="Enter your email"
                        class="min-w-[220px] flex-1 rounded-full border border-sf-line-strong px-[22px] py-3.5 text-[15px] text-sf-ink transition-colors duration-200 ease-out outline-none focus:border-sf-primary"
                    />
                    <button
                        type="submit"
                        class="inline-flex items-center gap-2.5 rounded-full bg-sf-primary px-[30px] py-3.5 text-[15px] font-medium text-white transition-colors duration-200 ease-out hover:bg-sf-primary-deep focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-sf-primary"
                    >
                        Subscribe
                        <ArrowRight class="size-[15px]" />
                    </button>
                </form>
                <span
                    v-if="subscribed"
                    class="text-sm text-sf-success italic"
                    role="status"
                >
                    Thanks — you're on the list.
                </span>
            </div>
        </div>
    </section>
</template>
