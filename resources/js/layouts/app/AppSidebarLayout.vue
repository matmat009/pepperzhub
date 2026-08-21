<script setup lang="ts">
import AppContent from '@/components/AppContent.vue';
import AppShell from '@/components/AppShell.vue';
import AppSidebar from '@/components/AppSidebar.vue';
import SiteHeader from '@/components/SiteHeader.vue';
import { Toaster } from '@/components/ui/sonner';
import type { BreadcrumbItem } from '@/types';

type Props = {
    breadcrumbs?: BreadcrumbItem[];
};

withDefaults(defineProps<Props>(), {
    breadcrumbs: () => [],
});
</script>

<template>
    <AppShell
        variant="sidebar"
        class="[--header-height:calc(var(--spacing)*12)] [--sidebar-width:calc(var(--spacing)*72)]"
    >
        <AppSidebar collapsible="offcanvas" variant="inset" />
        <AppContent variant="sidebar" class="overflow-x-hidden">
            <SiteHeader :breadcrumbs="breadcrumbs" />
            <slot />
        </AppContent>
        <Toaster />
    </AppShell>
</template>
