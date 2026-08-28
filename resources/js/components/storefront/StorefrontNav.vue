<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { Menu, ShoppingCart, X } from '@lucide/vue';
import { computed, ref } from 'vue';
import BrandWordmark from '@/components/storefront/BrandWordmark.vue';
import { useStorefrontCart } from '@/composables/useStorefrontCart';
import { home } from '@/routes';
import { cart, track } from '@/routes/storefront';
import { index as catalog } from '@/routes/storefront/products';

const { count } = useStorefrontCart();

const menuOpen = ref(false);

/**
 * Only destinations that actually resolve. The artboards also show FAQ,
 * Protocols and Reviews, but those were `href="#"` placeholders with no page
 * behind them — see the deviation note in the handoff report.
 */
const links = computed(() => [
    { label: 'Home', href: home() },
    { label: 'Products', href: catalog() },
    { label: 'Track Order', href: track() },
    { label: 'Contact', href: '#contact' },
]);
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
                        class="rounded-full px-[18px] py-2.5 font-medium text-sf-text transition-colors duration-200 ease-out hover:text-sf-primary"
                    >
                        {{ link.label }}
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
                        aria-label="Cart"
                        class="relative grid size-11 place-items-center rounded-full border border-sf-line-strong bg-white text-sf-ink transition-colors duration-200 ease-out hover:border-sf-primary hover:text-sf-primary"
                    >
                        <ShoppingCart class="size-5" />
                        <span
                            v-if="count > 0"
                            class="absolute -top-0.5 -right-0.5 flex h-5 min-w-5 items-center justify-center rounded-full border-2 border-white bg-sf-rose px-1.5 text-xs font-semibold text-white"
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
                    class="rounded-lg px-4 py-3.5 text-base font-medium text-sf-text transition-colors duration-200 ease-out hover:bg-sf-tint hover:text-sf-primary"
                    @click="menuOpen = false"
                >
                    {{ link.label }}
                </component>
            </div>
        </Transition>
    </header>
</template>
