<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { MessageSquareQuote } from '@lucide/vue';
import { home } from '@/routes';
import { index as catalog, show } from '@/routes/storefront/products';

/**
 * Payload from App\Http\Controllers\Storefront\ReviewController.
 *
 * Every active review, whether or not it names a product. No filtering
 * controls: unlike protocols, a review is not reliably about a product — let
 * alone one category — so there is nothing consistent to group it under.
 *
 * No rating field anywhere, by design.
 */
type StorefrontReview = {
    id: number;
    customer_name: string | null;
    title: string;
    description: string;
    image_url: string | null;
    /** Null for an untagged review, and for one whose product was deleted. */
    product_name: string | null;
    product_slug: string | null;
};

defineProps<{
    reviews: StorefrontReview[];
}>();
</script>

<template>
    <Head title="Reviews" />

    <div class="mx-auto w-full max-w-[1680px] px-5 pt-8 pb-24 sm:px-10">
        <div class="flex items-center gap-2 text-sm text-sf-subtle">
            <Link
                :href="home()"
                class="transition-colors duration-200 ease-out hover:text-sf-primary"
                >Home</Link
            >
            <span>/</span>
            <span class="text-sf-ink">Reviews</span>
        </div>

        <div class="mt-5 max-w-2xl">
            <h1
                class="font-display text-[34px] font-medium tracking-[-0.02em] text-sf-ink"
            >
                Customer reviews
            </h1>
            <p class="mt-3 text-[15px] leading-[1.7] text-sf-muted">
                What researchers have told us about working with our compounds.
                For laboratory research use only. Not for human consumption.
            </p>
        </div>

        <div
            v-if="!reviews.length"
            class="mt-10 flex flex-col items-center rounded-2xl border border-dashed border-sf-line-strong px-8 py-24 text-center"
        >
            <span
                class="grid size-20 place-items-center rounded-full bg-sf-tint text-sf-primary"
            >
                <MessageSquareQuote class="size-9" />
            </span>
            <p class="mt-6 font-display text-2xl font-semibold text-sf-ink">
                No reviews yet
            </p>
            <p class="mt-2 max-w-md text-[15px] leading-[1.7] text-sf-muted">
                Check back soon, or browse the catalogue in the meantime.
            </p>
            <Link
                :href="catalog()"
                class="mt-8 inline-flex items-center gap-2.5 rounded-full bg-sf-primary px-9 py-3.5 font-display text-base font-medium text-white transition-colors duration-200 ease-out hover:bg-sf-primary-deep"
            >
                Browse peptides
            </Link>
        </div>

        <div
            v-else
            class="mt-10 grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-3"
        >
            <article
                v-for="review in reviews"
                :key="review.id"
                class="flex flex-col overflow-hidden rounded-2xl border border-sf-line bg-white transition-colors duration-200 ease-out hover:border-sf-line-strong"
            >
                <img
                    v-if="review.image_url"
                    :src="review.image_url"
                    :alt="review.title"
                    class="aspect-4/3 w-full object-cover"
                />
                <div class="flex flex-1 flex-col p-6">
                    <!--
                        Only when a product is still tagged. An untagged review
                        renders no chip rather than an empty one.
                    -->
                    <Link
                        v-if="review.product_name && review.product_slug"
                        :href="show(review.product_slug)"
                        class="mb-3 inline-flex w-fit rounded-full bg-sf-tint px-3 py-1 text-[11px] font-semibold tracking-[0.14em] text-sf-primary uppercase transition-colors duration-200 ease-out hover:bg-sf-primary hover:text-white"
                    >
                        {{ review.product_name }}
                    </Link>

                    <h2 class="font-display text-lg font-semibold text-sf-ink">
                        {{ review.title }}
                    </h2>
                    <p
                        class="mt-2 flex-1 text-[15px] leading-[1.7] text-sf-muted"
                    >
                        {{ review.description }}
                    </p>
                    <p
                        v-if="review.customer_name"
                        class="mt-4 text-sm font-medium text-sf-subtle"
                    >
                        — {{ review.customer_name }}
                    </p>
                </div>
            </article>
        </div>
    </div>
</template>
