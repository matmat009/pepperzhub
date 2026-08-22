<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import ChartAreaInteractive from '@/components/ChartAreaInteractive.vue';
import DocumentsTable from '@/components/DocumentsTable.vue';
import SectionCards from '@/components/SectionCards.vue';
import { dashboardDemoData } from '@/lib/dashboardDemoData';
import { dashboard } from '@/routes';

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Dashboard',
                href: dashboard(),
            },
        ],
    },
});
</script>

<template>
    <Head title="Dashboard" />

    <div class="flex flex-1 flex-col">
        <div class="@container/main flex flex-1 flex-col gap-2">
            <div class="flex flex-col gap-4 py-4 md:gap-6 md:py-6">
                <SectionCards />
                <div class="px-4 lg:px-6">
                    <ChartAreaInteractive />
                </div>
                <DocumentsTable :data="dashboardDemoData" />
            </div>
        </div>
    </div>
</template>
