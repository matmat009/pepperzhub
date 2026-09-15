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
        <header>
            <div class="space-y-1">
                <h1 class="text-2xl font-semibold tracking-tight">Reviews</h1>
                <p class="text-sm text-muted-foreground">
                    Customer testimonials shown on the storefront. Tag one to a
                    product to show it on that product's page too.
                </p>
            </div>
        </header>

        <DataTable
            :data="reviews"
            :columns="columns"
            empty-message="No reviews match this search."
        >
            <template #toolbar="{ table }">
                <div
                    class="flex flex-col gap-3 xl:flex-row xl:items-center xl:justify-between"
                >
                    <div
                        class="flex min-w-0 flex-wrap items-center gap-2 xl:flex-1"
                    >
                        <div class="relative w-full sm:w-72 xl:w-80 2xl:w-96">
                            <Search
                                class="pointer-events-none absolute top-1/2 left-2.5 size-4 -translate-y-1/2 text-muted-foreground"
                            />
                            <Input
                                :model-value="searchValue(table as ReviewTable)"
                                placeholder="Search reviews..."
                                class="h-10 rounded-lg pl-9 shadow-none"
                                @update:model-value="
                                    (value) =>
                                        setSearch(table as ReviewTable, value)
                                "
                            />
                        </div>
                    </div>

                    <Button
                        class="w-full shrink-0 sm:w-auto sm:self-end xl:self-auto"
                        @click="openCreate"
                    >
                        <Plus />
                        Add Review
                    </Button>
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
