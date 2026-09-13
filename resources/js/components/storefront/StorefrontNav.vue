<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { Menu, ShoppingCart, X } from '@lucide/vue';
import { computed, ref } from 'vue';
import type { InertiaLinkProps } from '@inertiajs/vue3';
import BrandWordmark from '@/components/storefront/BrandWordmark.vue';
import { useCurrentUrl } from '@/composables/useCurrentUrl';
import { useSiteSettings } from '@/composables/useSiteSettings';
import { useStorefrontCart } from '@/composables/useStorefrontCart';
import { home } from '@/routes';
import { cart, protocols, reviews, track } from '@/routes/storefront';
import { index as catalog } from '@/routes/storefront/products';

const { count } = useStorefrontCart();
const { currentUrl } = useCurrentUrl();

const settings = useSiteSettings();

const menuOpen = ref(false);

type NavLink = {
    label: string;
    href: NonNullable<InertiaLinkProps['href']>;
    section?: 'home' | 'products' | 'protocols' | 'reviews' | 'track';
    /** Leaves the storefront, so a plain anchor into a new tab. */
    external?: boolean;
};

/**
 * Only destinations that actually resolve. FAQ has no page of its own — the
 * questions get asked and answered on the shop's Facebook, so that is where it
 * points, and it is absent entirely until that URL is set rather than sitting
 * in the bar going nowhere the way it used to.
 */
const links = computed<NavLink[]>(() => [
    { label: 'Home', href: home(), section: 'home' },
    { label: 'Products', href: catalog(), section: 'products' },
    { label: 'Protocols', href: protocols(), section: 'protocols' },
    { label: 'Reviews', href: reviews(), section: 'reviews' },
    { label: 'Track Order', href: track(), section: 'track' },
    ...(settings.value.facebook_url
        ? [
              {
                  label: 'FAQ',
                  href: settings.value.facebook_url,
                  external: true,
              },
          ]
        : []),
]);

type AriaCurrent = 'page' | 'location' | undefined;

/**
 * Exact section boundaries keep `/` from matching everything and prevent a
 * future `/products-*` route from lighting up the Products link. Product
 * details belong to that section, but the listing link is a location rather
 * than the current page there.
 */
const navCurrent = (link: NavLink): AriaCurrent => {
    const path = currentUrl.value;

    switch (link.section) {
        case 'home':
            return path === home().url ? 'page' : undefined;
        case 'products':
            if (path === catalog().url) {
                return 'page';
            }

            return path.startsWith(`${catalog().url}/`)
                ? 'location'
                : undefined;
        case 'protocols':
            return path === protocols().url ? 'page' : undefined;
        case 'reviews':
            return path === reviews().url ? 'page' : undefined;
        case 'track':
            return path === track().url ? 'page' : undefined;
        default:
            return undefined;
    }
};

const cartLabel = computed(
    () => `Cart, ${count.value} ${count.value === 1 ? 'item' : 'items'}`,
);

const cartCurrent = computed<AriaCurrent>(() =>
    currentUrl.value === cart().url ? 'page' : undefined,
);
</script>

<template>
    <header class="sticky top-0 z-30 px-5 pt-3.5 pb-2 sm:px-10">
        <div class="mx-auto max-w-[1680px]">
            <div
                class="flex h-16 items-center justify-between gap-4 rounded-full border border-sf-line bg-white/92 pr-3 pl-5 shadow-[0_10px_30px_rgba(30,35,60,0.08)] backdrop-blur-[10px] sm:pl-[22px]"
            >
                <Link
                    :href="home()"
                    class="flex min-w-0 flex-1 items-center"
                    aria-label="PepperzzHub home"
                >
                    <BrandWordmark :emblem="34" />
                </Link>

                <nav class="hidden items-center gap-1 lg:flex">
                    <component
                        :is="typeof link.href === 'string' ? 'a' : Link"
                        v-for="link in links"
                        :key="link.label"
                        :href="link.href"
                        :target="link.external ? '_blank' : undefined"
                        :rel="link.external ? 'noopener noreferrer' : undefined"
                        :aria-current="navCurrent(link)"
                        class="relative flex min-h-11 items-center rounded-full px-[18px] pt-2 pb-3 font-medium transition-colors duration-200 ease-out focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-sf-primary"
                        :class="
                            navCurrent(link)
                                ? 'font-semibold text-sf-primary-deep'
                                : 'text-sf-text hover:text-sf-primary'
                        "
                    >
                        {{ link.label }}
                        <span
                            v-if="navCurrent(link)"
                            aria-hidden="true"
                            class="absolute bottom-1.5 left-1/2 h-0.5 w-8 -translate-x-1/2 rounded-full bg-sf-primary"
                        >
                            <span
                                class="absolute top-1/2 -right-1 size-1 -translate-y-1/2 rounded-full bg-sf-rose"
                            />
                        </span>
                    </component>
                </nav>

                <div class="flex flex-1 items-center justify-end gap-2">
                    <button
                        type="button"
                        :aria-label="menuOpen ? 'Close menu' : 'Open menu'"
                        :aria-expanded="menuOpen"
                        class="grid size-11 place-items-center rounded-full border border-sf-line-strong bg-white text-sf-ink transition-colors duration-200 ease-out hover:border-sf-primary hover:text-sf-primary focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-sf-primary lg:hidden"
                        @click="menuOpen = !menuOpen"
                    >
                        <component :is="menuOpen ? X : Menu" class="size-5" />
                    </button>

                    <Link
                        :href="cart()"
                        :aria-label="cartLabel"
                        :aria-current="cartCurrent"
                        class="inline-flex h-11 min-w-11 items-center justify-center gap-1.5 rounded-full border border-sf-primary bg-white px-2.5 text-sf-ink shadow-[0_5px_14px_-8px_rgba(50,70,160,0.48)] transition duration-200 ease-out hover:bg-sf-tint hover:shadow-[0_8px_18px_-9px_rgba(50,70,160,0.52)] focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-sf-primary sm:gap-2 sm:px-4"
                    >
                        <ShoppingCart
                            class="size-5 shrink-0 text-sf-primary"
                            aria-hidden="true"
                        />
                        <span class="hidden text-sm font-semibold sm:inline">
                            Cart
                        </span>
                        <span
                            v-if="count > 0"
                            class="inline-flex h-7 min-w-7 shrink-0 items-center justify-center rounded-full bg-sf-rose-deep px-2 text-xs font-semibold text-white tabular-nums"
                        >
                            {{ count }}
                        </span>
                    </Link>
                </div>
            </div>
        </div>

        <Transition
            enter-active-class="transition duration-200 ease-out"
            enter-from-class="-translate-y-2 opacity-0"
            leave-active-class="transition duration-150 ease-out"
            leave-to-class="-translate-y-2 opacity-0"
        >
            <div
                v-if="menuOpen"
                class="mx-auto mt-2.5 flex max-w-[1680px] flex-col rounded-xl border border-sf-line bg-white p-2.5 shadow-[0_24px_56px_rgba(30,35,60,0.18)] lg:hidden"
            >
                <component
                    :is="typeof link.href === 'string' ? 'a' : Link"
                    v-for="link in links"
                    :key="link.label"
                    :href="link.href"
                    :target="link.external ? '_blank' : undefined"
                    :rel="link.external ? 'noopener noreferrer' : undefined"
                    :aria-current="navCurrent(link)"
                    class="rounded-lg px-4 py-3 text-base font-medium transition-colors duration-200 ease-out hover:bg-sf-tint focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-sf-primary"
                    :class="
                        navCurrent(link)
                            ? 'font-semibold text-sf-primary-deep'
                            : 'text-sf-text hover:text-sf-primary'
                    "
                    @click="menuOpen = false"
                >
                    <span class="relative inline-flex pb-1.5">
                        {{ link.label }}
                        <span
                            v-if="navCurrent(link)"
                            aria-hidden="true"
                            class="absolute bottom-0 left-0 h-0.5 w-8 rounded-full bg-sf-primary"
                        >
                            <span
                                class="absolute top-1/2 -right-1 size-1 -translate-y-1/2 rounded-full bg-sf-rose"
                            />
                        </span>
                    </span>
                </component>
            </div>
        </Transition>
    </header>
</template>
