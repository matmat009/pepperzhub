<script setup lang="ts">
import { Head, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import AppearanceTabs from '@/components/AppearanceTabs.vue';
import Heading from '@/components/Heading.vue';
import { edit } from '@/routes/appearance';

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Appearance settings',
                href: edit(),
            },
        ],
    },
});

const page = usePage();
const adminDarkModeEnabled = computed(() => page.props.adminDarkModeEnabled);
</script>

<template>
    <Head title="Appearance settings" />

    <h1 class="sr-only">Appearance settings</h1>

    <div class="space-y-6">
        <Heading
            variant="small"
            title="Appearance settings"
            :description="
                adminDarkModeEnabled
                    ? 'Update the appearance settings for your account'
                    : 'Light mode is currently enforced by configuration'
            "
        />
        <AppearanceTabs v-if="adminDarkModeEnabled" />
    </div>
</template>
