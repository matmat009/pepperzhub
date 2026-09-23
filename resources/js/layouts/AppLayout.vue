<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';
import { watchEffect } from 'vue';
import { applyThemeScope } from '@/composables/useAppearance';
import AppLayout from '@/layouts/app/AppSidebarLayout.vue';
import type { BreadcrumbItem } from '@/types';

const { breadcrumbs = [] } = defineProps<{
    breadcrumbs?: BreadcrumbItem[];
}>();

const page = usePage();

watchEffect(() => {
    applyThemeScope(page.props.adminDarkModeEnabled ? 'admin' : 'forced-light');
});
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <slot />
    </AppLayout>
</template>
