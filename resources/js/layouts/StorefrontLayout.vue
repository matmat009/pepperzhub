<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';
import { computed, onBeforeUnmount, onMounted } from 'vue';
import AddedToCartDialog from '@/components/storefront/AddedToCartDialog.vue';
import StorefrontFooter from '@/components/storefront/StorefrontFooter.vue';
import StorefrontNav from '@/components/storefront/StorefrontNav.vue';
import { applyThemeScope } from '@/composables/useAppearance';
import { vReveal } from '@/lib/scrollReveal';

/**
 * Public shell: pill navbar, page content, footer. No admin sidebar.
 *
 * The storefront is a light-only surface, so the wrapper paints white and sets
 * the brand body face explicitly rather than inheriting whatever the admin
 * appearance toggle left on <html>.
 */

const page = usePage();

applyThemeScope('forced-light');

/**
 * Inertia keeps this layout mounted across a visit — the nav, the footer and
 * the cart popover deliberately survive navigation — so the page transition is
 * keyed on the destination instead. Re-keying swaps the wrapper in a single
 * patch: no `<Transition>`, so there is never a frame with two pages in the
 * document, no exit to wait on before the next page renders, and nothing for
 * Inertia's own scroll restoration to land in an empty <main> for.
 *
 * The query string is deliberately dropped. Cart mutations, checkout
 * validation and the Track Order lookup all come back to the URL they left
 * from, and none of them should replay a page animation.
 */
const pageKey = computed(() => `${page.component}:${page.url.split('?')[0]}`);

/*
 * The switch the whole motion system hangs off. Until this lands, every
 * reveal selector in app.css fails to match and the storefront renders at full
 * opacity — which is exactly what should happen if the bundle never boots.
 *
 * Removed again on the way out so an admin page never inherits it.
 */
onMounted(() => {
    document.documentElement.setAttribute('data-sf-motion', '');
});

onBeforeUnmount(() => {
    document.documentElement.removeAttribute('data-sf-motion');
});
</script>

<template>
    <div
        class="flex min-h-screen flex-col overflow-x-clip bg-white font-body text-sf-text"
    >
        <StorefrontNav />
        <main class="flex flex-1 flex-col">
            <div :key="pageKey" class="sf-page flex flex-1 flex-col">
                <slot />
            </div>
        </main>
        <StorefrontFooter v-reveal="'fade-in'" />

        <!-- Mounted once here so a card on any page can raise it. -->
        <AddedToCartDialog />
    </div>
</template>
