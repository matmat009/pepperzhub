<script setup lang="ts">
import StorefrontFooter from '@/components/storefront/StorefrontFooter.vue';
import StorefrontNav from '@/components/storefront/StorefrontNav.vue';

/**
 * Public shell: pill navbar, page content, footer. No admin sidebar.
 *
 * The storefront is a light-only surface, so the wrapper paints white and sets
 * the brand body face explicitly rather than inheriting whatever the admin
 * appearance toggle left on <html>.
 */
</script>

<template>
    <div class="flex min-h-screen flex-col bg-white font-body text-sf-text">
        <StorefrontNav />
        <main class="flex flex-1 flex-col">
            <slot />
        </main>
        <StorefrontFooter />
    </div>
</template>
