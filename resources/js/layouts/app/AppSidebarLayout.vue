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
        <AppSidebar collapsible="icon" variant="inset" />
        <!-- Keep the horizontal-overflow guard *below* the header: any ancestor
             with a non-visible overflow becomes the sticky containing block, and
             SidebarInset never scrolls (the document does), which would pin the
             header to a scrollport that never moves. -->
        <AppContent variant="sidebar" class="min-w-0">
            <SiteHeader :breadcrumbs="breadcrumbs" />
            <div
                class="flex w-full max-w-full min-w-0 flex-1 flex-col overflow-x-hidden"
            >
                <slot />
            </div>
        </AppContent>
        <Toaster />
    </AppShell>
</template>
