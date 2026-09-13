<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import {
    AlertTriangle,
    ArrowRight,
    MessageSquareQuote,
    Quote,
} from '@lucide/vue';
import { computed, ref } from 'vue';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { formatDate } from '@/pages/admin/products/all-products/types';
import { index as catalog, show } from '@/routes/storefront/products';

/**
 * Payload from App\Http\Controllers\Storefront\ReviewController.
 *
 * Every active review arrives at once, so the three display filters stay
 * client-side. There is no rating or verified-purchase field in the model;
 * neither is inferred here.
 */
type StorefrontReview = {
    id: number;
    customer_name: string | null;
    title: string;
    description: string;
    created_at: string | null;
    image_url: string | null;
    /** Null for an untagged review, and for one whose product was deleted. */
    product_name: string | null;
    product_slug: string | null;
};

type ReviewFilter = 'all' | 'photos' | 'notes';

const props = defineProps<{
    reviews: StorefrontReview[];
}>();

const activeFilter = ref<ReviewFilter>('all');
const activeReview = ref<StorefrontReview | null>(null);
const reviewOpen = ref(false);
const failedImageIds = ref<Set<number>>(new Set());

const filters: { label: string; value: ReviewFilter }[] = [
    { label: 'All', value: 'all' },
    { label: 'With photos', value: 'photos' },
    { label: 'Notes only', value: 'notes' },
];

const hasPhoto = (review: StorefrontReview) =>
    Boolean(review.image_url && !failedImageIds.value.has(review.id));

const filteredReviews = computed(() => {
    if (activeFilter.value === 'photos') {
        return props.reviews.filter(hasPhoto);
    }

    if (activeFilter.value === 'notes') {
        return props.reviews.filter((review) => !hasPhoto(review));
    }

    return props.reviews;
});

const reviewCount = computed(() => {
    const count = filteredReviews.value.length;

    return `${count} ${count === 1 ? 'review' : 'reviews'}`;
});

const displayName = (review: StorefrontReview) =>
    review.customer_name?.trim() || 'Anonymous reviewer';

const initials = (review: StorefrontReview) => {
    const parts = displayName(review).split(/\s+/).filter(Boolean).slice(0, 2);

    return parts
        .map((part) => part.charAt(0))
        .join('')
        .toLocaleUpperCase();
};

const avatarTone = (index: number) => {
    if (index % 3 === 1) {
        return 'bg-sf-rose-tint text-sf-rose-deep';
    }

    if (index % 3 === 2) {
        return 'bg-sf-tint text-sf-muted';
    }

    return 'bg-sf-primary/10 text-sf-primary';
};

const mediaTone = (index: number) => {
    if (index % 3 === 1) {
        return 'bg-sf-surface';
    }

    if (index % 3 === 2) {
        return 'bg-sf-rose-tint/80';
    }

    return 'bg-sf-primary/8';
};

const markImageFailed = (reviewId: number) => {
    failedImageIds.value = new Set([...failedImageIds.value, reviewId]);
};

const openReview = (review: StorefrontReview) => {
    activeReview.value = review;
    reviewOpen.value = true;
};

const resetFilter = () => {
    activeFilter.value = 'all';
};
</script>

<template>
    <Head title="Reviews" />

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
                In their words
            </p>
            <h1
                class="mt-3 font-display text-[clamp(2.55rem,5vw,4rem)] leading-[1.08] font-medium tracking-[-0.025em] text-balance text-sf-ink"
            >
                Customer Reviews
            </h1>
            <p
                class="mt-4 max-w-[680px] text-[15px] leading-[1.75] text-pretty text-sf-muted italic sm:text-base"
            >
                Messages and notes shared with us by researchers working with
                our compounds.
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
                        For laboratory research use only
                    </p>
                    <p class="mt-1 text-[13px] leading-[1.6] text-sf-text">
                        Accounts shared here reflect individual research
                        experiences. Products are not for human consumption.
                    </p>
                </div>
            </div>
        </div>
    </section>

    <section class="bg-white px-5 py-12 sm:px-10 sm:py-14 lg:py-16">
        <div class="mx-auto w-full max-w-[1180px]">
            <div
                class="flex flex-col gap-5 border-b border-sf-line pb-5 sm:flex-row sm:items-center sm:justify-between"
            >
                <h2
                    class="font-display text-[24px] font-semibold tracking-[-0.02em] text-sf-ink"
                    aria-live="polite"
                >
                    {{ reviewCount }}
                </h2>

                <div
                    class="flex flex-wrap gap-2"
                    aria-label="Filter customer reviews"
                >
                    <button
                        v-for="filter in filters"
                        :key="filter.value"
                        type="button"
                        :aria-pressed="activeFilter === filter.value"
                        class="min-h-10 rounded-full border px-4 py-2 text-[13px] transition-colors duration-200 ease-out focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-sf-primary"
                        :class="
                            activeFilter === filter.value
                                ? 'border-sf-primary bg-sf-primary font-semibold text-white'
                                : 'border-sf-line-strong bg-white text-sf-text hover:border-sf-primary/40 hover:text-sf-primary'
                        "
                        @click="activeFilter = filter.value"
                    >
                        {{ filter.label }}
                    </button>
                </div>
            </div>

            <div
                v-if="filteredReviews.length"
                class="mt-5 grid grid-cols-1 items-stretch gap-5 md:grid-cols-2 lg:grid-cols-3"
            >
                <article
                    v-for="(review, index) in filteredReviews"
                    :key="review.id"
                    class="flex min-w-0 flex-col rounded-xl border border-sf-line-strong bg-white p-5 transition-colors duration-200 ease-out hover:border-sf-primary/30"
                >
                    <header class="flex min-w-0 items-start gap-3">
                        <span
                            class="grid size-9 shrink-0 place-items-center rounded-full text-[10px] font-semibold"
                            :class="avatarTone(index)"
                            aria-hidden="true"
                        >
                            {{ initials(review) }}
                        </span>
                        <div class="min-w-0 flex-1">
                            <p
                                class="text-[13px] leading-snug font-semibold break-words text-sf-ink"
                            >
                                {{ displayName(review) }}
                            </p>
                        </div>
                        <time
                            v-if="review.created_at"
                            :datetime="review.created_at"
                            class="shrink-0 pt-0.5 text-[10px] leading-snug text-sf-subtle"
                        >
                            {{ formatDate(review.created_at) }}
                        </time>
                    </header>

                    <h3
                        class="mt-4 font-display text-[17px] leading-snug font-semibold break-words text-sf-ink"
                    >
                        {{ review.title }}
                    </h3>

                    <div
                        class="mt-3 grid aspect-4/3 w-full place-items-center overflow-hidden rounded-md"
                        :class="mediaTone(index)"
                    >
                        <img
                            v-if="hasPhoto(review)"
                            :src="review.image_url ?? ''"
                            :alt="`Photo shared with ${displayName(review)}'s review`"
                            class="size-full object-contain"
                            loading="lazy"
                            @error="markImageFailed(review.id)"
                        />
                        <Quote
                            v-else
                            class="size-11 fill-sf-rose text-sf-rose"
                            aria-hidden="true"
                        />
                    </div>

                    <p
                        class="mt-4 line-clamp-4 flex-1 text-[13px] leading-[1.7] break-words text-sf-text"
                    >
                        {{ review.description }}
                    </p>

                    <footer
                        class="mt-4 flex min-h-11 flex-wrap items-center gap-x-3 gap-y-2 border-t border-sf-line pt-3"
                    >
                        <Link
                            v-if="review.product_name && review.product_slug"
                            :href="show(review.product_slug)"
                            class="min-w-0 text-[11px] break-words text-sf-muted transition-colors hover:text-sf-primary focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-sf-primary"
                        >
                            Product:
                            <span class="font-semibold text-sf-primary">
                                {{ review.product_name }}
                            </span>
                        </Link>
                        <button
                            type="button"
                            class="ml-auto inline-flex min-h-9 shrink-0 items-center gap-1.5 text-[11px] font-semibold text-sf-primary transition-colors hover:text-sf-primary-hover focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-sf-primary"
                            @click="openReview(review)"
                        >
                            Read review
                            <ArrowRight class="size-3.5" aria-hidden="true" />
                        </button>
                    </footer>
                </article>
            </div>

            <div
                v-else
                class="mt-5 flex flex-col items-center rounded-xl border border-dashed border-sf-rule px-6 py-16 text-center"
            >
                <span
                    class="grid size-12 place-items-center rounded-full bg-sf-tint text-sf-primary"
                    aria-hidden="true"
                >
                    <MessageSquareQuote class="size-5" />
                </span>
                <p class="mt-4 font-display text-xl font-semibold text-sf-ink">
                    {{
                        reviews.length
                            ? 'No reviews match this filter.'
                            : 'No reviews yet'
                    }}
                </p>
                <p
                    class="mx-auto mt-2 max-w-md text-[14px] leading-relaxed text-sf-muted"
                >
                    {{
                        reviews.length
                            ? 'Choose another review type to keep browsing.'
                            : 'Check back soon, or browse the catalogue in the meantime.'
                    }}
                </p>
                <button
                    v-if="reviews.length"
                    type="button"
                    class="mt-5 min-h-10 rounded-full border border-sf-primary px-5 py-2 text-sm font-semibold text-sf-primary transition-colors hover:bg-sf-tint focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-sf-primary"
                    @click="resetFilter"
                >
                    Show all reviews
                </button>
                <Link
                    v-else
                    :href="catalog()"
                    class="mt-6 inline-flex min-h-11 items-center rounded-full bg-sf-primary px-7 py-3 text-sm font-semibold text-white transition-colors hover:bg-sf-primary-deep focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-sf-primary"
                >
                    Browse peptides
                </Link>
            </div>

            <section
                class="mt-10 flex flex-col gap-6 rounded-xl bg-[linear-gradient(105deg,oklch(0.93_0.032_240)_0%,oklch(0.95_0.032_20)_100%)] px-6 py-7 sm:px-8 lg:flex-row lg:items-center lg:justify-between lg:px-10"
            >
                <div class="max-w-[620px]">
                    <h2
                        class="font-display text-[24px] font-semibold tracking-[-0.02em] text-sf-ink"
                    >
                        Worked with our compounds?
                    </h2>
                    <p
                        class="mt-2 text-[14px] leading-[1.7] text-sf-muted italic"
                    >
                        Send us a note, screenshot, or photo and include your
                        order number.
                    </p>
                </div>
                <a
                    href="mailto:support@pepperzhub.ph?subject=Share%20my%20PepperzzHub%20experience"
                    class="inline-flex min-h-12 shrink-0 items-center justify-center gap-2 rounded-full bg-sf-primary px-7 py-3 text-sm font-semibold text-white shadow-[0_8px_22px_rgba(50,70,160,0.22)] transition duration-200 ease-out hover:-translate-y-0.5 hover:bg-sf-primary-deep focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-sf-primary"
                >
                    Share your experience
                    <ArrowRight class="size-4" aria-hidden="true" />
                </a>
            </section>
        </div>
    </section>

    <Dialog v-model:open="reviewOpen">
        <DialogContent
            v-if="activeReview"
            class="max-h-[calc(100vh-2rem)] max-w-[720px] overflow-y-auto border-sf-line-strong bg-white p-0"
        >
            <div class="p-5 sm:p-7">
                <DialogHeader class="pr-8 text-left">
                    <DialogTitle
                        class="font-display text-[24px] leading-snug font-semibold tracking-[-0.02em] text-sf-ink"
                    >
                        {{ activeReview.title }}
                    </DialogTitle>
                    <DialogDescription
                        class="flex flex-wrap items-center gap-x-2 gap-y-1 pt-1 text-[13px] text-sf-muted"
                    >
                        <span>{{ displayName(activeReview) }}</span>
                        <span v-if="activeReview.created_at" aria-hidden="true">
                            ·
                        </span>
                        <time
                            v-if="activeReview.created_at"
                            :datetime="activeReview.created_at"
                        >
                            {{ formatDate(activeReview.created_at) }}
                        </time>
                    </DialogDescription>
                </DialogHeader>

                <div
                    v-if="hasPhoto(activeReview)"
                    class="mt-5 grid max-h-[55vh] min-h-56 w-full place-items-center overflow-hidden rounded-lg bg-sf-primary/8"
                >
                    <img
                        :src="activeReview.image_url ?? ''"
                        :alt="`Photo shared with ${displayName(activeReview)}'s review`"
                        class="max-h-[55vh] w-full object-contain"
                        @error="markImageFailed(activeReview.id)"
                    />
                </div>
                <div
                    v-else
                    class="mt-5 grid min-h-44 place-items-center rounded-lg bg-sf-rose-tint/80"
                >
                    <Quote
                        class="size-13 fill-sf-rose text-sf-rose"
                        aria-hidden="true"
                    />
                </div>

                <p
                    class="mt-5 text-[15px] leading-[1.8] break-words text-sf-text"
                >
                    {{ activeReview.description }}
                </p>

                <div
                    class="mt-6 flex flex-wrap items-center justify-between gap-4 border-t border-sf-line pt-5"
                >
                    <Link
                        v-if="
                            activeReview.product_name &&
                            activeReview.product_slug
                        "
                        :href="show(activeReview.product_slug)"
                        class="text-[13px] text-sf-muted transition-colors hover:text-sf-primary focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-sf-primary"
                    >
                        Product:
                        <span class="font-semibold text-sf-primary">
                            {{ activeReview.product_name }}
                        </span>
                    </Link>
                    <DialogClose
                        class="ml-auto min-h-10 rounded-full border border-sf-line-strong px-5 py-2 text-sm font-semibold text-sf-text transition-colors hover:border-sf-primary/40 hover:text-sf-primary focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-sf-primary"
                    >
                        Close review
                    </DialogClose>
                </div>
            </div>
        </DialogContent>
    </Dialog>
</template>
