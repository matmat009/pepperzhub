<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import {
    CalendarDays,
    CalendarX2,
    Clock,
    Mail,
    MapPin,
    Phone,
} from '@lucide/vue';
import { computed, ref } from 'vue';
import BrandWordmark from '@/components/storefront/BrandWordmark.vue';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { useSiteSettings } from '@/composables/useSiteSettings';
import { home } from '@/routes';
import { index as catalog } from '@/routes/storefront/products';

const settings = useSiteSettings();

/*
 * Each block drops out when everything inside it is unset. A heading over an
 * empty column is as much dead furniture as an icon linking nowhere, so the
 * per-field v-ifs below are not enough on their own.
 */
const hasContact = computed(
    () =>
        Boolean(settings.value.contact_email) ||
        Boolean(settings.value.contact_phone) ||
        Boolean(settings.value.contact_address),
);

const hasSocials = computed(
    () =>
        Boolean(settings.value.facebook_url) ||
        Boolean(settings.value.instagram_url) ||
        Boolean(settings.value.tiktok_url),
);

const legalOpen = ref(false);
const legalTitle = ref('Privacy Policy');

const openLegal = (title: string) => {
    legalTitle.value = title;
    legalOpen.value = true;
};
</script>

<template>
    <footer id="contact" class="border-t border-sf-line bg-sf-surface">
        <div
            class="mx-auto grid max-w-[1680px] grid-cols-[repeat(auto-fit,minmax(240px,1fr))] gap-12 px-5 pt-16 pb-14 sm:px-10"
        >
            <div class="max-w-[360px]">
                <BrandWordmark :emblem="42" size="md" />
                <p class="mt-5 text-[15px] leading-[1.7] text-sf-muted">
                    Peptides that work. Results that matter.
                </p>
                <!--
                    Inline rather than from @lucide/vue: Lucide dropped its
                    brand glyphs, so these are the artboards' own paths.
                -->
                <div v-if="hasSocials" class="mt-6 flex gap-3">
                    <a
                        v-if="settings.facebook_url"
                        :href="settings.facebook_url"
                        target="_blank"
                        rel="noopener noreferrer"
                        aria-label="Facebook"
                        class="grid size-11 place-items-center rounded-full border border-sf-line-strong bg-white text-sf-primary transition-colors duration-sf-fast ease-sf hover:border-sf-primary hover:text-sf-primary-hover"
                    >
                        <svg
                            class="size-[19px]"
                            viewBox="0 0 24 24"
                            fill="currentColor"
                            aria-hidden="true"
                        >
                            <path
                                d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z"
                            />
                        </svg>
                    </a>
                    <a
                        v-if="settings.instagram_url"
                        :href="settings.instagram_url"
                        target="_blank"
                        rel="noopener noreferrer"
                        aria-label="Instagram"
                        class="grid size-11 place-items-center rounded-full border border-sf-line-strong bg-white text-sf-primary transition-colors duration-sf-fast ease-sf hover:border-sf-primary hover:text-sf-primary-hover"
                    >
                        <svg
                            class="size-[19px]"
                            viewBox="0 0 24 24"
                            fill="none"
                            stroke="currentColor"
                            stroke-width="2"
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            aria-hidden="true"
                        >
                            <rect x="2" y="2" width="20" height="20" rx="5" />
                            <path
                                d="M16 11.37A4 4 0 1 1 12.63 8 4 4 0 0 1 16 11.37z"
                            />
                            <line x1="17.5" y1="6.5" x2="17.51" y2="6.5" />
                        </svg>
                    </a>
                    <a
                        v-if="settings.tiktok_url"
                        :href="settings.tiktok_url"
                        target="_blank"
                        rel="noopener noreferrer"
                        aria-label="TikTok"
                        class="grid size-11 place-items-center rounded-full border border-sf-line-strong bg-white text-sf-primary transition-colors duration-sf-fast ease-sf hover:border-sf-primary hover:text-sf-primary-hover"
                    >
                        <svg
                            class="size-[18px]"
                            viewBox="0 0 24 24"
                            fill="currentColor"
                            aria-hidden="true"
                        >
                            <path
                                d="M19.59 6.69a4.83 4.83 0 0 1-3.77-4.25V2h-3.45v13.67a2.89 2.89 0 0 1-5.2 1.74 2.89 2.89 0 0 1 2.31-4.64 2.93 2.93 0 0 1 .88.13V9.4a6.84 6.84 0 0 0-1-.05A6.33 6.33 0 0 0 5 20.1a6.34 6.34 0 0 0 10.86-4.43v-7a8.16 8.16 0 0 0 4.77 1.52v-3.4a4.85 4.85 0 0 1-1-.1z"
                            />
                        </svg>
                    </a>
                </div>
            </div>

            <div>
                <div class="font-display text-lg font-semibold text-sf-ink">
                    Quick Links
                </div>
                <div class="mt-5 flex flex-col gap-3.5 text-[15px]">
                    <Link
                        :href="home()"
                        class="text-sf-muted transition-colors duration-sf-fast ease-sf hover:text-sf-primary"
                        >Home</Link
                    >
                    <Link
                        :href="catalog()"
                        class="text-sf-muted transition-colors duration-sf-fast ease-sf hover:text-sf-primary"
                        >Products</Link
                    >
                    <a
                        v-if="settings.contact_email"
                        :href="`mailto:${settings.contact_email}`"
                        class="text-sf-muted transition-colors duration-sf-fast ease-sf hover:text-sf-primary"
                        >Contact</a
                    >
                </div>
            </div>

            <div v-if="hasContact">
                <div class="font-display text-lg font-semibold text-sf-ink">
                    Contact Us
                </div>
                <div class="mt-5 flex flex-col gap-4 text-[15px]">
                    <a
                        v-if="settings.contact_email"
                        :href="`mailto:${settings.contact_email}`"
                        class="flex items-center gap-3 text-sf-muted transition-colors duration-sf-fast ease-sf hover:text-sf-primary"
                    >
                        <Mail class="size-[17px] shrink-0 text-sf-primary" />
                        {{ settings.contact_email }}
                    </a>
                    <span
                        v-if="settings.contact_phone"
                        class="flex items-center gap-3 text-sf-muted"
                    >
                        <Phone class="size-[17px] shrink-0 text-sf-rose-mid" />
                        {{ settings.contact_phone }}
                    </span>
                    <span
                        v-if="settings.contact_address"
                        class="flex items-center gap-3 text-sf-muted"
                    >
                        <MapPin class="size-[17px] shrink-0 text-sf-primary" />
                        {{ settings.contact_address }}
                    </span>
                </div>
            </div>

            <div>
                <div class="font-display text-lg font-semibold text-sf-ink">
                    Order Support Hours
                </div>
                <div class="mt-5 flex flex-col gap-4 text-[15px]">
                    <span class="flex items-center gap-3 text-sf-muted">
                        <Clock class="size-[17px] shrink-0 text-sf-primary" />
                        9:00 AM – 6:00 PM
                    </span>
                    <span class="flex items-center gap-3 text-sf-muted">
                        <CalendarDays
                            class="size-[17px] shrink-0 text-sf-rose-mid"
                        />
                        Monday – Saturday
                    </span>
                    <span class="flex items-center gap-3 text-sf-muted">
                        <CalendarX2
                            class="size-[17px] shrink-0 text-sf-rose-mid"
                        />
                        Closed: Sundays &amp; holidays
                    </span>
                </div>
            </div>
        </div>

        <div class="border-t border-sf-line">
            <div
                class="mx-auto flex max-w-[1680px] flex-wrap items-center justify-between gap-5 px-5 py-5 text-sm text-sf-subtle sm:px-10"
            >
                <span>
                    © 2026
                    <span class="font-semibold">
                        <span class="text-sf-rose">Pepperzz</span
                        ><span class="text-sf-primary">Hub</span>
                    </span>
                    . All rights reserved.
                </span>
                <span class="flex items-center gap-[18px]">
                    <button
                        type="button"
                        class="text-sf-subtle transition-colors duration-sf-fast ease-sf hover:text-sf-primary focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-sf-primary"
                        @click="openLegal('Privacy Policy')"
                    >
                        Privacy Policy
                    </button>
                    <span class="size-1 rounded-full bg-sf-line-strong" />
                    <button
                        type="button"
                        class="text-sf-subtle transition-colors duration-sf-fast ease-sf hover:text-sf-primary focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-sf-primary"
                        @click="openLegal('Terms of Service')"
                    >
                        Terms of Service
                    </button>
                    <span class="size-1 rounded-full bg-sf-line-strong" />
                    <button
                        type="button"
                        class="text-sf-subtle transition-colors duration-sf-fast ease-sf hover:text-sf-primary focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-sf-primary"
                        @click="openLegal('Shipping Policy')"
                    >
                        Shipping Policy
                    </button>
                </span>
            </div>
        </div>

        <Dialog v-model:open="legalOpen">
            <DialogContent class="sf-dialog max-w-[520px]">
                <DialogHeader>
                    <DialogTitle
                        class="font-display text-[22px] font-semibold tracking-[-0.02em] text-sf-ink"
                    >
                        {{ legalTitle }}
                    </DialogTitle>
                    <DialogDescription
                        class="pt-2 text-[15px] leading-[1.7] text-sf-muted"
                    >
                        Our {{ legalTitle }} is being finalized and will be
                        published here soon.
                    </DialogDescription>
                </DialogHeader>
                <p
                    v-if="settings.contact_email"
                    class="text-[15px] leading-[1.7] text-sf-muted"
                >
                    Questions in the meantime? Reach us at
                    <a
                        :href="`mailto:${settings.contact_email}`"
                        class="font-semibold text-sf-primary transition-colors duration-sf-fast ease-sf hover:text-sf-primary-hover"
                        >{{ settings.contact_email }}</a
                    >.
                </p>
            </DialogContent>
        </Dialog>
    </footer>
</template>
