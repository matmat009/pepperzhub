<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import {
    ArrowRight,
    HeartPulse,
    Quote,
    ShieldCheck,
    TestTube,
    Trophy,
} from '@lucide/vue';
import { computed, ref } from 'vue';
import ProductCard from '@/components/storefront/ProductCard.vue';
import type { Product } from '@/pages/admin/products/all-products/types';
import {
    reviews as reviewsPage,
    track as trackOrder,
} from '@/routes/storefront';
import { index as catalog } from '@/routes/storefront/products';
import { reviewDisplayName } from './review';
import type { StorefrontReview } from './review';

type HomepageReview = Pick<
    StorefrontReview,
    'id' | 'customer_name' | 'title' | 'description' | 'image_url'
>;

const props = defineProps<{
    featured: Product[];
    categories: string[];
    reviews: HomepageReview[];
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

const failedReviewImageIds = ref<Set<number>>(new Set());

const hasReviewPhoto = (review: HomepageReview) =>
    Boolean(review.image_url && !failedReviewImageIds.value.has(review.id));

const markReviewImageFailed = (reviewId: number) => {
    failedReviewImageIds.value = new Set([
        ...failedReviewImageIds.value,
        reviewId,
    ]);
};

const reviewTones = ['bg-sf-well-blue', 'bg-sf-surface', 'bg-sf-rose-tint/70'];

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
</script>

<template>
    <Head title="Peptides that work" />

    <div class="relative isolate">
        <!--
            One page-level wash spans the hero, feature strip, and catalog
            introduction. Anchoring the fade to the end of that content keeps
            the transition smooth even when the feature grid wraps on smaller
            screens, while the upward offset still carries it behind the nav.
        -->
        <div
            aria-hidden="true"
            class="home-background-wash pointer-events-none absolute inset-x-0 -top-24 -bottom-24 -z-10"
        />

        <section
            class="relative flex w-full flex-col items-center px-5 pt-16 pb-24 text-center sm:px-10"
        >
            <div
                class="mx-auto flex w-full max-w-[1680px] flex-col items-center"
            >
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
                    <span
                        class="hidden h-4.5 w-px bg-sf-line-strong sm:block"
                    />
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
                    :class="
                        index % 2 === 0 ? 'sm:border-r sm:border-sf-line' : ''
                    "
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
            class="mx-auto flex w-full max-w-[1680px] flex-col items-center px-5 pt-24 sm:px-10"
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
        </section>
    </div>

    <section
        class="mx-auto flex w-full max-w-[1680px] flex-col items-center px-5 pb-8 sm:px-10"
    >
        <div
            v-if="shown.length"
            class="mt-11 grid w-full grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4"
        >
            <ProductCard
                v-for="(product, i) in shown"
                :key="product.id"
                :product="product"
                :index="i"
                variant="catalog"
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

    <section
        v-if="reviews.length"
        class="mx-auto w-full max-w-[1680px] px-5 pt-16 sm:px-10"
    >
        <header
            class="flex flex-col items-start justify-between gap-5 border-b border-sf-line-strong pb-6 sm:flex-row sm:items-end"
        >
            <div>
                <p
                    class="text-[11px] font-semibold tracking-[0.3em] text-sf-primary uppercase"
                >
                    In their words
                </p>
                <h2
                    class="mt-3 font-display text-[clamp(2rem,4vw,2.5rem)] leading-tight font-medium tracking-[-0.025em] text-sf-ink"
                >
                    Why Choose PepperzzHub
                </h2>
            </div>

            <Link
                :href="reviewsPage()"
                class="inline-flex min-h-11 shrink-0 items-center gap-2.5 rounded-full border border-sf-primary/45 bg-white px-6 text-sm font-medium text-sf-primary transition-colors duration-200 ease-out hover:border-sf-primary hover:bg-sf-tint focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-sf-primary"
            >
                Read all reviews
                <ArrowRight class="size-4" aria-hidden="true" />
            </Link>
        </header>

        <div class="mt-7 grid grid-cols-1 gap-5 md:grid-cols-3">
            <article
                v-for="(review, index) in reviews"
                :key="review.id"
                class="flex min-w-0 flex-col overflow-hidden rounded-xl border border-sf-line-strong bg-white shadow-[0_8px_24px_rgba(30,35,60,0.035)]"
            >
                <div
                    class="grid aspect-[2.08/1] w-full place-items-center overflow-hidden p-3"
                    :class="reviewTones[index % reviewTones.length]"
                >
                    <img
                        v-if="hasReviewPhoto(review)"
                        :src="review.image_url ?? ''"
                        :alt="`Photo shared with ${reviewDisplayName(review)}'s review`"
                        class="size-full object-contain"
                        loading="lazy"
                        @error="markReviewImageFailed(review.id)"
                    />
                    <Quote
                        v-else
                        class="size-12 fill-sf-rose text-sf-rose"
                        aria-hidden="true"
                    />
                </div>

                <div class="flex min-h-48 flex-1 flex-col px-6 py-5">
                    <h3
                        class="font-display text-[18px] leading-snug font-semibold break-words text-sf-ink"
                    >
                        {{ review.title }}
                    </h3>
                    <p
                        class="mt-2 line-clamp-4 text-[14px] leading-[1.75] break-words text-sf-text"
                    >
                        {{ review.description }}
                    </p>
                    <p
                        class="mt-auto pt-6 text-[13px] leading-relaxed text-sf-primary italic"
                    >
                        — {{ reviewDisplayName(review) }}
                    </p>
                </div>
            </article>
        </div>
    </section>

    <section class="relative isolate mt-15 w-full py-12 sm:py-14 lg:py-18">
        <div
            aria-hidden="true"
            class="explore-cta-background pointer-events-none absolute inset-0 z-0"
        />

        <div class="relative z-10 mx-auto w-full max-w-[1680px] px-5 sm:px-10">
            <div
                class="grid items-center gap-10 px-7 sm:px-12 lg:grid-cols-[minmax(0,1fr)_clamp(17rem,27vw,23rem)] lg:px-16 xl:gap-14 xl:px-24"
            >
                <div class="flex min-w-0 flex-col items-center text-center">
                    <div
                        class="flex items-center justify-center gap-4 text-[11px] font-semibold tracking-[0.34em] text-sf-primary uppercase sm:text-xs"
                    >
                        <span class="h-px w-10 bg-sf-primary/40 sm:w-14" />
                        <span>Ready to explore</span>
                        <span class="h-px w-10 bg-sf-primary/40 sm:w-14" />
                    </div>

                    <h2
                        class="mt-7 font-display text-[clamp(2.45rem,5vw,4.75rem)] leading-[1.05] font-medium tracking-[-0.035em] text-balance text-sf-ink"
                    >
                        <span class="block">Find the right products</span>
                        <span class="mt-1 block text-sf-primary italic">
                            for your research.
                        </span>
                    </h2>

                    <p
                        class="mt-6 max-w-[720px] text-[15px] leading-[1.8] text-sf-muted sm:text-[17px]"
                    >
                        Browse peptide products, review product details, and
                        place your order through our simple checkout flow. You
                        can also track your order anytime after confirmation.
                    </p>

                    <div
                        class="mt-8 flex w-full flex-col items-stretch justify-center gap-3 sm:w-auto sm:flex-row sm:items-center sm:gap-4"
                    >
                        <Link
                            :href="catalog()"
                            class="inline-flex min-h-12 items-center justify-center gap-3 rounded-full bg-sf-primary px-8 py-3.5 text-[15px] font-medium text-white shadow-[0_8px_22px_rgba(50,70,160,0.22)] transition duration-200 ease-out hover:-translate-y-0.5 hover:bg-sf-primary-deep hover:shadow-[0_12px_28px_rgba(50,70,160,0.3)] focus-visible:outline-2 focus-visible:outline-offset-3 focus-visible:outline-sf-primary sm:min-w-56"
                        >
                            Browse Products
                            <ArrowRight class="size-4" aria-hidden="true" />
                        </Link>

                        <Link
                            :href="trackOrder()"
                            class="inline-flex min-h-12 items-center justify-center rounded-full border border-sf-primary bg-white/65 px-8 py-3.5 text-[15px] font-medium text-sf-primary transition duration-200 ease-out hover:-translate-y-0.5 hover:bg-white focus-visible:outline-2 focus-visible:outline-offset-3 focus-visible:outline-sf-primary sm:min-w-48"
                        >
                            Track Order
                        </Link>
                    </div>
                </div>

                <div
                    aria-hidden="true"
                    class="relative mx-auto h-[270px] w-full max-w-[320px] sm:h-[320px] lg:h-[370px] lg:max-w-none"
                >
                    <img
                        src="/images/storefront/research-vials.png"
                        alt=""
                        class="absolute inset-0 size-full object-contain object-bottom"
                    />
                </div>
            </div>
        </div>
    </section>
</template>

<style scoped>
.home-background-wash {
    background:
        linear-gradient(
            to bottom,
            transparent 0,
            transparent calc(100% - 30rem),
            rgb(255 255 255 / 0.18) calc(100% - 23rem),
            rgb(255 255 255 / 0.72) calc(100% - 10rem),
            white 100%
        ),
        linear-gradient(
            125deg,
            var(--sf-hero-blue) 0%,
            white 48%,
            var(--sf-hero-rose) 100%
        );
}

.explore-cta-background {
    background:
        linear-gradient(
            to bottom,
            white 0%,
            rgb(255 255 255 / 0.98) 18%,
            rgb(255 255 255 / 0.72) 48%,
            transparent 80%
        ),
        radial-gradient(
            ellipse 82% 92% at 0% 100%,
            var(--sf-hero-blue) 0%,
            transparent 72%
        ),
        radial-gradient(
            ellipse 82% 92% at 100% 100%,
            var(--sf-hero-rose) 0%,
            transparent 72%
        ),
        white;
}
</style>
