<script setup lang="ts">
import type { Table } from '@tanstack/vue-table';
import { Head } from '@inertiajs/vue3';
import { Plus, Search } from '@lucide/vue';
import { ref } from 'vue';
import DataTable from '@/components/DataTable.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import type { Features } from '@/components/features';
import { index } from '@/routes/admin/reviews';
import { createReviewColumns } from './columns';
import DeleteDialog from './partials/DeleteDialog.vue';
import ReviewDialog from './partials/ReviewDialog.vue';
import type { Review, ReviewProductOption } from './types';

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Reviews',
                href: index(),
            },
        ],
    },
});

defineProps<{
    reviews: Review[];
    products: ReviewProductOption[];
}>();

type ReviewTable = Table<Features, Review>;

const editTarget = ref<Review | null>(null);
const formOpen = ref(false);
const deleteTarget = ref<Review | null>(null);
const deleteOpen = ref(false);

const openCreate = () => {
    editTarget.value = null;
    formOpen.value = true;
};

const openEdit = (review: Review) => {
    editTarget.value = review;
    formOpen.value = true;
};

const requestDelete = (review: Review) => {
    deleteTarget.value = review;
    deleteOpen.value = true;
};

const columns = createReviewColumns({
    onEdit: openEdit,
    onDelete: requestDelete,
});

const searchValue = (table: ReviewTable): string =>
    (table.getColumn('title')?.getFilterValue() as string) ?? '';

const setSearch = (table: ReviewTable, value: string | number) => {
    table.getColumn('title')?.setFilterValue(String(value) || undefined);
};
</script>

<template>
    <Head title="Reviews" />

    <div class="flex flex-1 flex-col gap-6 px-4 py-6 lg:px-6">
        <header class="flex flex-wrap items-start justify-between gap-4">
            <div class="space-y-1">
                <h1 class="text-2xl font-semibold tracking-tight">Reviews</h1>
                <p class="text-sm text-muted-foreground">
                    Customer testimonials shown on the storefront. Tag one to a
                    product to show it on that product's page too.
                </p>
            </div>
            <Button @click="openCreate">
                <Plus />
                Add Review
            </Button>
        </header>

        <DataTable
            :data="reviews"
            :columns="columns"
            empty-message="No reviews match this search."
        >
            <template #toolbar="{ table }">
                <div class="flex flex-wrap items-center gap-2">
                    <div class="relative flex-1 sm:max-w-xs">
                        <Search
                            class="pointer-events-none absolute top-1/2 left-2.5 size-4 -translate-y-1/2 text-muted-foreground"
                        />
                        <Input
                            :model-value="searchValue(table as ReviewTable)"
                            placeholder="Search reviews..."
                            class="h-9 pl-8"
                            @update:model-value="
                                (value) =>
                                    setSearch(table as ReviewTable, value)
                            "
                        />
                    </div>
                </div>
            </template>
        </DataTable>
    </div>

    <ReviewDialog
        v-model:open="formOpen"
        :review="editTarget"
        :products="products"
    />
    <DeleteDialog v-model:open="deleteOpen" :review="deleteTarget" />
</template>
