<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { Menu, ShoppingCart, X } from '@lucide/vue';
import { useMediaQuery, useWindowScroll } from '@vueuse/core';
import { computed, ref, watch } from 'vue';
import {
    Sheet,
    SheetClose,
    SheetContent,
    SheetDescription,
    SheetTitle,
    SheetTrigger,
} from '@/components/ui/sheet';
import { useCurrentUrl } from '@/composables/useCurrentUrl';
import { useStorefrontCart } from '@/composables/useStorefrontCart';
import { home } from '@/routes';
import { cart, faq, protocols, reviews, track } from '@/routes/storefront';
import { index as catalog } from '@/routes/storefront/products';

const { count } = useStorefrontCart();
const { currentUrl } = useCurrentUrl();

const menuOpen = ref(false);
const inlineNavigation = useMediaQuery('(min-width: 768px)');

/* Rotation into the inline layout must also release the Sheet's modal state. */
watch(inlineNavigation, (isInline) => {
    if (isInline) {
        menuOpen.value = false;
    }
});

/**
 * One passive scroll listener for the whole storefront. It drives only the
 * existing full-width-to-pill transition and remains position based.
 */
const { y } = useWindowScroll();
const scrolled = computed(() => y.value > 16);

/** The badge remounts only when the cart count increases, replaying its pop. */
const additions = ref(0);

watch(count, (next, previous) => {
    if (next > previous) {
        additions.value += 1;
    }
});

type NavLink = {
    label: string;
    href: ReturnType<typeof home>;
    section?: 'home' | 'products' | 'protocols' | 'reviews' | 'track' | 'faq';
};

const links = computed<NavLink[]>(() => [
    { label: 'Home', href: home(), section: 'home' },
    { label: 'Products', href: catalog(), section: 'products' },
    { label: 'Protocols', href: protocols(), section: 'protocols' },
    { label: 'Reviews', href: reviews(), section: 'reviews' },
    { label: 'Track Order', href: track(), section: 'track' },
    { label: 'FAQ', href: faq(), section: 'faq' },
]);

type AriaCurrent = 'page' | undefined;

/** Product details belong to the Products section; other links are exact. */
const navCurrent = (link: NavLink): AriaCurrent => {
    const path = currentUrl.value;

    switch (link.section) {
        case 'home':
            return path === home().url ? 'page' : undefined;
        case 'products':
            if (path === catalog().url) {
                return 'page';
            }

            return path.startsWith(`${catalog().url}/`) ? 'page' : undefined;
        case 'protocols':
            return path === protocols().url ? 'page' : undefined;
        case 'reviews':
            return path === reviews().url ? 'page' : undefined;
        case 'track':
            return path === track().url ? 'page' : undefined;
        case 'faq':
            return path === faq().url ? 'page' : undefined;
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
    <Sheet v-model:open="menuOpen" :modal="true">
        <!--
            The header keeps one fixed height in both scroll states. The Sheet
            portals its panel to body, so opening navigation cannot resize this
            header or move the page beneath it.
        -->
        <header
            class="sticky top-0 z-30 bg-transparent pt-3.5 pb-2 transition-[padding] duration-sf-ui ease-sf motion-reduce:transition-none"
            :class="scrolled ? 'px-5 sm:px-10' : 'px-0'"
        >
            <div class="mx-auto max-w-[1680px]">
                <div
                    class="sf-enter-down flex h-16 items-center justify-between gap-1 border pr-3 pl-5 transition-[border-radius,background-color,border-color,box-shadow,backdrop-filter] duration-sf-ui ease-sf motion-reduce:transition-none sm:gap-4 sm:pl-[22px] md:gap-1 md:px-2 lg:gap-2 lg:pr-3 lg:pl-5 xl:gap-4 xl:pl-[22px]"
                    :class="
                        scrolled
                            ? 'rounded-full border-sf-line-strong bg-white/96 shadow-[0_14px_36px_rgba(30,35,60,0.14)] backdrop-blur-[10px]'
                            : 'rounded-none border-transparent bg-transparent shadow-[0_14px_36px_rgba(30,35,60,0)] backdrop-blur-[0px]'
                    "
                >
                    <Link
                        :href="home()"
                        class="flex min-w-0 flex-none items-center gap-1 sm:flex-1 sm:gap-3 md:gap-1 lg:gap-2 xl:gap-3"
                        aria-label="PepperzzHub home"
                    >
                        <img
                            src="/images/branding/pepperzhub-navbar-logo.png"
                            alt="PepperzzHub"
                            width="1763"
                            height="892"
                            class="h-8 w-auto shrink-0 object-contain sm:h-11 md:h-8 lg:h-9 xl:h-11"
                        />
                        <span
                            class="font-display text-[14px] font-semibold tracking-[-0.02em] whitespace-nowrap sm:text-[18px] md:text-xs lg:text-sm xl:text-[18px]"
                        >
                            <span class="text-sf-rose-deep">Pepperzz</span
                            ><span class="text-sf-primary-deep">Hub</span>
                        </span>
                    </Link>

                    <nav
                        class="hidden items-center whitespace-nowrap md:flex md:gap-0 md:text-xs lg:gap-0.5 lg:text-sm xl:gap-1 xl:text-base"
                    >
                        <Link
                            v-for="link in links"
                            :key="link.label"
                            :href="link.href"
                            :aria-current="navCurrent(link)"
                            class="relative flex min-h-11 items-center justify-center rounded-full px-1.5 pt-2 pb-3 font-medium transition-colors duration-sf-ui ease-sf focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-sf-primary lg:px-2.5 xl:px-[18px]"
                            :class="
                                navCurrent(link)
                                    ? 'bg-sf-serenity-blue/15 font-semibold text-sf-primary-deep'
                                    : 'text-sf-text hover:text-sf-primary'
                            "
                        >
                            {{ link.label }}
                            <span
                                v-if="navCurrent(link)"
                                aria-hidden="true"
                                class="absolute bottom-1.5 left-1/2 h-0.5 w-8 -translate-x-1/2 rounded-full bg-linear-to-r from-sf-primary to-sf-rose"
                            />
                        </Link>
                    </nav>

                    <div
                        class="flex flex-none items-center justify-end gap-1 sm:flex-1 sm:gap-2"
                    >
                        <SheetTrigger :as-child="true">
                            <button
                                type="button"
                                :aria-label="
                                    menuOpen ? 'Close menu' : 'Open menu'
                                "
                                :aria-expanded="menuOpen"
                                aria-controls="storefront-navigation-drawer"
                                class="grid size-11 place-items-center rounded-full border border-sf-line-strong bg-white text-sf-ink transition-colors duration-sf-fast ease-sf hover:border-sf-primary hover:text-sf-primary focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-sf-primary md:hidden"
                            >
                                <component
                                    :is="menuOpen ? X : Menu"
                                    class="size-5"
                                />
                            </button>
                        </SheetTrigger>

                        <Link
                            :href="cart()"
                            :aria-label="cartLabel"
                            :aria-current="cartCurrent"
                            class="relative inline-flex h-11 min-w-11 items-center justify-center gap-2 overflow-visible rounded-full border border-sf-primary bg-white px-2.5 text-sf-ink shadow-[0_4px_12px_rgba(50,70,160,0.12)] transition duration-sf-fast ease-sf hover:bg-sf-tint hover:shadow-[0_6px_16px_rgba(50,70,160,0.16)] focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-sf-primary sm:px-4"
                        >
                            <ShoppingCart
                                class="size-5 shrink-0 text-sf-primary"
                                aria-hidden="true"
                            />
                            <span
                                class="hidden text-sm font-semibold sm:inline"
                            >
                                Cart
                            </span>
                            <span
                                v-if="count > 0"
                                :key="additions"
                                aria-hidden="true"
                                class="absolute -top-2 -right-1 inline-flex h-5 min-w-5 items-center justify-center rounded-full border-2 border-white bg-sf-rose-deep px-1 text-[10px] leading-none font-semibold text-white tabular-nums shadow-[0_2px_6px_rgba(135,40,65,0.24)]"
                                :class="
                                    additions > 0 ? 'sf-badge-pop' : undefined
                                "
                            >
                                {{ count }}
                            </span>
                        </Link>
                    </div>
                </div>
            </div>
        </header>

        <SheetContent
            id="storefront-navigation-drawer"
            side="right"
            class="storefront-nav-sheet z-[60] h-dvh w-[85vw] max-w-[22rem] gap-0 overflow-y-auto overscroll-contain border-sf-line-strong bg-white p-0 font-body shadow-[-18px_0_45px_rgba(30,35,60,0.16)] ease-sf data-[state=closed]:duration-sf-ui data-[state=open]:duration-sf-ui motion-reduce:data-[state=closed]:animate-none motion-reduce:data-[state=open]:animate-none md:hidden"
        >
            <SheetTitle class="sr-only">Storefront navigation</SheetTitle>
            <SheetDescription class="sr-only">
                Primary links for the PepperzzHub storefront.
            </SheetDescription>

            <div
                class="flex min-h-20 items-center justify-between gap-3 border-b border-sf-line px-5 py-4 sm:gap-4"
            >
                <div class="flex min-w-0 items-center gap-1 sm:gap-3">
                    <img
                        src="/images/branding/pepperzhub-navbar-logo.png"
                        alt=""
                        aria-hidden="true"
                        width="1763"
                        height="892"
                        class="h-8 w-auto shrink-0 object-contain sm:h-10"
                    />
                    <span
                        class="font-display text-base font-semibold tracking-[-0.02em] whitespace-nowrap sm:text-lg"
                    >
                        <span class="text-sf-rose-deep">Pepperzz</span
                        ><span class="text-sf-primary-deep">Hub</span>
                    </span>
                </div>

                <SheetClose :as-child="true">
                    <button
                        type="button"
                        aria-label="Close navigation menu"
                        class="relative z-10 grid size-11 shrink-0 place-items-center rounded-full border border-sf-line-strong bg-white text-sf-ink transition-colors duration-sf-fast ease-sf hover:border-sf-primary hover:text-sf-primary focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-sf-primary"
                    >
                        <X class="size-5" aria-hidden="true" />
                    </button>
                </SheetClose>
            </div>

            <nav
                class="flex flex-col gap-1 px-4 py-5"
                aria-label="Storefront navigation"
            >
                <SheetClose
                    v-for="link in links"
                    :key="link.label"
                    :as-child="true"
                >
                    <Link
                        :href="link.href"
                        :aria-current="navCurrent(link)"
                        class="relative flex min-h-12 items-center rounded-xl px-4 pt-2 pb-3 text-base font-medium transition-colors duration-sf-ui ease-sf hover:bg-sf-tint focus-visible:outline-2 focus-visible:-outline-offset-2 focus-visible:outline-sf-primary"
                        :class="
                            navCurrent(link)
                                ? 'bg-sf-serenity-blue/15 font-semibold text-sf-primary-deep'
                                : 'text-sf-text hover:text-sf-primary'
                        "
                    >
                        {{ link.label }}
                        <span
                            v-if="navCurrent(link)"
                            aria-hidden="true"
                            class="absolute bottom-1.5 left-4 h-0.5 w-8 rounded-full bg-linear-to-r from-sf-primary to-sf-rose"
                        />
                    </Link>
                </SheetClose>
            </nav>
        </SheetContent>
    </Sheet>
</template>

<style>
.storefront-nav-sheet[data-state='open'],
.storefront-nav-sheet[data-state='closed'],
body:has(.storefront-nav-sheet) [data-slot='sheet-overlay'] {
    --tw-duration: var(--sf-motion-ui);
    animation-duration: var(--sf-motion-ui);
    animation-timing-function: var(--sf-ease);
}

body:has(.storefront-nav-sheet) [data-slot='sheet-overlay'] {
    z-index: 50;
    background: rgb(20 25 45 / 0.48);
}

/* SheetContent ships its own close control; this drawer supplies a larger one. */
.storefront-nav-sheet > button {
    display: none;
}

@media (prefers-reduced-motion: reduce) {
    .storefront-nav-sheet,
    body:has(.storefront-nav-sheet) [data-slot='sheet-overlay'] {
        animation: none !important;
        transition: none !important;
    }
}
</style>
