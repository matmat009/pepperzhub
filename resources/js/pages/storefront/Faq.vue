<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import {
    IconBrandMessenger,
    IconBrandWhatsapp,
    IconMail,
} from '@tabler/icons-vue';
import { ChevronDown, Search } from '@lucide/vue';
import { computed, ref, watch } from 'vue';
import { useSiteSettings } from '@/composables/useSiteSettings';
import { vReveal } from '@/lib/scrollReveal';
import { FAQ_CATEGORIES, FAQ_ITEMS } from './faqContent';
import type { FaqCategory } from './faqContent';

const settings = useSiteSettings();
const search = ref('');
const activeCategory = ref<FaqCategory>('All');
const openQuestion = ref<string | null>(FAQ_ITEMS[0]?.id ?? null);

const filteredQuestions = computed(() => {
    const term = search.value.trim().toLowerCase();

    return FAQ_ITEMS.filter((item) => {
        const inCategory =
            activeCategory.value === 'All' ||
            item.category === activeCategory.value;
        const matchesSearch =
            !term ||
            `${item.question} ${item.answer}`.toLowerCase().includes(term);

        return inCategory && matchesSearch;
    });
});

watch(filteredQuestions, (questions) => {
    if (!questions.some((question) => question.id === openQuestion.value)) {
        openQuestion.value = questions[0]?.id ?? null;
    }
});

const toggleQuestion = (id: string) => {
    openQuestion.value = openQuestion.value === id ? null : id;
};

const resetFilters = () => {
    search.value = '';
    activeCategory.value = 'All';
    openQuestion.value = FAQ_ITEMS[0]?.id ?? null;
};

/** Messenger addresses a Facebook page by the final segment of its URL. */
const messengerLink = computed(() => {
    const stored = settings.value.facebook_url;

    if (!stored) {
        return null;
    }

    try {
        const username =
            new URL(stored).pathname.replace(/\/+$/, '').split('/').pop() ?? '';

        return username ? `https://m.me/${username}` : null;
    } catch {
        return null;
    }
});

/** wa.me requires digits in international format rather than a local 09xx. */
const whatsappLink = computed(() => {
    const digits = (settings.value.contact_phone ?? '').replace(/\D/g, '');

    if (!digits) {
        return null;
    }

    const number = digits.startsWith('0') ? `63${digits.slice(1)}` : digits;

    return `https://wa.me/${number}`;
});
</script>

<template>
    <Head title="Frequently Asked Questions" />

    <section
        class="relative isolate flex w-full flex-col items-center px-5 pt-11 pb-19 text-center sm:px-10 sm:pt-14 sm:pb-20"
    >
        <div
            aria-hidden="true"
            class="pointer-events-none absolute inset-x-0 -top-24 -bottom-px -z-10 bg-[linear-gradient(125deg,var(--color-sf-hero-blue)_0%,#fff_48%,var(--color-sf-hero-rose)_100%)]"
        />

        <div
            v-reveal="'stagger'"
            class="mx-auto flex w-full max-w-[860px] flex-col items-center"
        >
            <p
                class="text-[11px] font-semibold tracking-[0.32em] text-sf-primary uppercase"
            >
                Help centre
            </p>
            <h1
                class="mt-4 font-display text-[clamp(2.5rem,5vw,4rem)] leading-[1.08] font-medium tracking-[-0.025em] text-balance text-sf-ink"
            >
                Frequently asked questions
            </h1>
            <p
                class="mt-4 max-w-[680px] text-[15px] leading-[1.75] text-pretty text-sf-muted italic sm:text-base"
            >
                Ordering, payment, shipping and product handling — answered.
            </p>

            <label class="relative mt-8 w-full max-w-[470px]">
                <span class="sr-only">Search frequently asked questions</span>
                <Search
                    aria-hidden="true"
                    class="pointer-events-none absolute top-1/2 left-5 size-4 -translate-y-1/2 text-sf-subtle"
                />
                <input
                    v-model="search"
                    type="search"
                    placeholder="Search a question..."
                    class="h-12 w-full rounded-full border border-sf-rule bg-white/95 pr-5 pl-12 text-sm text-sf-ink shadow-[0_6px_20px_rgba(30,35,60,0.04)] transition-colors duration-sf-ui ease-sf outline-none placeholder:text-sf-subtle focus:border-sf-primary focus:ring-2 focus:ring-sf-primary/15"
                />
            </label>
        </div>
    </section>

    <section class="bg-white px-5 pt-12 pb-24 sm:px-10 sm:pt-14 sm:pb-28">
        <!--
            Revealed once, on the wrapper rather than on the list: the list is
            behind a v-if, and re-mounting it on every search would re-hide
            content the customer is actively reading.
        -->
        <div v-reveal="'stagger'" class="mx-auto w-full max-w-[990px]">
            <div
                class="flex flex-wrap items-center justify-center gap-2"
                aria-label="FAQ categories"
            >
                <button
                    v-for="category in FAQ_CATEGORIES"
                    :key="category"
                    type="button"
                    class="min-h-10 rounded-full border px-5 py-2 text-sm font-medium transition-colors duration-sf-ui ease-sf focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-sf-primary"
                    :class="
                        activeCategory === category
                            ? 'border-sf-primary bg-sf-primary text-white'
                            : 'border-sf-rule bg-white text-sf-text hover:border-sf-primary hover:text-sf-primary'
                    "
                    :aria-pressed="activeCategory === category"
                    @click="activeCategory = category"
                >
                    {{ category }}
                </button>
            </div>

            <div v-if="filteredQuestions.length" class="mt-9 grid gap-3">
                <article
                    v-for="item in filteredQuestions"
                    :key="item.id"
                    class="overflow-hidden rounded-xl border bg-white transition-colors duration-sf-ui ease-sf"
                    :class="
                        openQuestion === item.id
                            ? 'border-sf-primary shadow-[0_8px_28px_rgba(50,70,160,0.07)]'
                            : 'border-sf-line-strong'
                    "
                >
                    <h2>
                        <button
                            :id="`faq-heading-${item.id}`"
                            type="button"
                            class="flex min-h-20 w-full items-center justify-between gap-5 px-6 py-4 text-left focus-visible:outline-2 focus-visible:-outline-offset-2 focus-visible:outline-sf-primary sm:px-7"
                            :aria-expanded="openQuestion === item.id"
                            :aria-controls="`faq-answer-${item.id}`"
                            @click="toggleQuestion(item.id)"
                        >
                            <span class="min-w-0">
                                <span
                                    class="block text-[10px] font-semibold tracking-[0.22em] text-sf-primary uppercase"
                                >
                                    {{ item.category }}
                                </span>
                                <span
                                    class="mt-1 block font-display text-base leading-snug font-semibold text-sf-ink sm:text-[17px]"
                                >
                                    {{ item.question }}
                                </span>
                            </span>
                            <span
                                aria-hidden="true"
                                class="grid size-8 shrink-0 place-items-center rounded-full transition-colors duration-sf-ui ease-sf"
                                :class="
                                    openQuestion === item.id
                                        ? 'bg-sf-primary text-white'
                                        : 'bg-sf-tint text-sf-muted'
                                "
                            >
                                <ChevronDown
                                    class="size-4 transition-transform duration-sf-ui ease-sf"
                                    :class="
                                        openQuestion === item.id
                                            ? 'rotate-180'
                                            : undefined
                                    "
                                />
                            </span>
                        </button>
                    </h2>

                    <!--
                        A grid row from 0fr to 1fr is the only way to reach the
                        answer's real height without measuring it, and the
                        `visibility` flip in .sf-collapse is what keeps a closed
                        answer out of the tab order and the accessibility tree.
                        `aria-expanded` above and `aria-controls` still point at
                        the region itself, which never moves.
                    -->
                    <div
                        class="sf-collapse"
                        :data-open="openQuestion === item.id ? '' : undefined"
                    >
                        <div>
                            <div
                                :id="`faq-answer-${item.id}`"
                                role="region"
                                :aria-labelledby="`faq-heading-${item.id}`"
                                class="border-t border-sf-line-strong px-6 py-5 sm:px-7"
                            >
                                <p
                                    class="max-w-[880px] text-[15px] leading-[1.75] text-sf-muted"
                                >
                                    {{ item.answer }}
                                </p>
                            </div>
                        </div>
                    </div>
                </article>
            </div>

            <div
                v-else
                class="sf-fade mt-9 rounded-xl border border-sf-line-strong bg-sf-surface px-6 py-14 text-center"
                role="status"
            >
                <h2 class="font-display text-xl font-semibold text-sf-ink">
                    No questions found
                </h2>
                <p class="mt-2 text-[15px] leading-relaxed text-sf-muted">
                    Try another search or reset the FAQ filters.
                </p>
                <button
                    type="button"
                    class="mt-5 inline-flex min-h-11 items-center justify-center rounded-full bg-sf-primary px-6 text-sm font-semibold text-white transition-colors duration-sf-fast ease-sf hover:bg-sf-primary-hover focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-sf-primary"
                    @click="resetFilters"
                >
                    Reset filters
                </button>
            </div>

            <section
                class="mt-16 grid gap-8 rounded-2xl bg-[linear-gradient(110deg,var(--color-sf-hero-blue)_0%,#fff_50%,var(--color-sf-hero-rose)_100%)] px-7 py-10 sm:px-10 lg:grid-cols-[1fr_252px] lg:items-center lg:gap-14"
                aria-labelledby="faq-support-heading"
            >
                <div>
                    <h2
                        id="faq-support-heading"
                        class="font-display text-[clamp(1.65rem,3vw,2rem)] leading-tight font-medium tracking-[-0.02em] text-sf-ink"
                    >
                        Do you have questions?
                    </h2>
                    <p
                        class="mt-3 text-[15px] leading-relaxed text-sf-muted italic"
                    >
                        Reach us on any of these — we reply Monday to Saturday.
                    </p>
                </div>

                <div class="grid gap-3">
                    <a
                        v-if="messengerLink"
                        :href="messengerLink"
                        target="_blank"
                        rel="noopener noreferrer"
                        class="flex min-h-13 items-center gap-3 rounded-full border border-sf-line-strong bg-white/95 px-5 text-sm font-medium text-sf-ink transition-colors duration-sf-fast ease-sf hover:border-sf-primary hover:text-sf-primary focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-sf-primary"
                    >
                        <span
                            class="grid size-8 shrink-0 place-items-center rounded-full bg-sf-serenity-blue/20 text-sf-primary"
                        >
                            <IconBrandMessenger class="size-[18px]" />
                        </span>
                        Facebook Messenger
                    </a>
                    <a
                        v-if="whatsappLink"
                        :href="whatsappLink"
                        target="_blank"
                        rel="noopener noreferrer"
                        class="flex min-h-13 items-center gap-3 rounded-full border border-sf-line-strong bg-white/95 px-5 text-sm font-medium text-sf-ink transition-colors duration-sf-fast ease-sf hover:border-sf-primary hover:text-sf-primary focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-sf-primary"
                    >
                        <span
                            class="grid size-8 shrink-0 place-items-center rounded-full bg-sf-rose-quartz/35 text-sf-rose-deep"
                        >
                            <IconBrandWhatsapp class="size-[18px]" />
                        </span>
                        WhatsApp
                    </a>
                    <a
                        v-if="settings.contact_email"
                        :href="`mailto:${settings.contact_email}`"
                        class="flex min-h-13 min-w-0 items-center gap-3 rounded-full border border-sf-line-strong bg-white/95 px-5 text-sm font-medium text-sf-ink transition-colors duration-sf-fast ease-sf hover:border-sf-primary hover:text-sf-primary focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-sf-primary"
                    >
                        <span
                            class="grid size-8 shrink-0 place-items-center rounded-full bg-sf-tint text-sf-muted"
                        >
                            <IconMail class="size-[18px]" />
                        </span>
                        <span class="min-w-0 break-all">
                            {{ settings.contact_email }}
                        </span>
                    </a>
                </div>
            </section>
        </div>
    </section>
</template>
