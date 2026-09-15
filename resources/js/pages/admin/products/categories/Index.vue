<script setup lang="ts">
import type { Table } from '@tanstack/vue-table';
import { Head } from '@inertiajs/vue3';
import { Plus, Search } from '@lucide/vue';
import { ref } from 'vue';
import DataTable from '@/components/DataTable.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import type { Features } from '@/components/features';
import { index } from '@/routes/admin/products/categories';
import { createCategoryColumns } from './columns';
import CategoryDialog from './partials/CategoryDialog.vue';
import DeleteDialog from './partials/DeleteDialog.vue';
import type { Category } from './types';

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Categories',
                href: index(),
            },
        ],
    },
});

defineProps<{
    categories: Category[];
}>();

type CategoryTable = Table<Features, Category>;

const editTarget = ref<Category | null>(null);
const formOpen = ref(false);
const deleteTarget = ref<Category | null>(null);
const deleteOpen = ref(false);

const openCreate = () => {
    editTarget.value = null;
    formOpen.value = true;
};

const openEdit = (category: Category) => {
    editTarget.value = category;
    formOpen.value = true;
};

const requestDelete = (category: Category) => {
    deleteTarget.value = category;
    deleteOpen.value = true;
};

const columns = createCategoryColumns({
    onEdit: openEdit,
    onDelete: requestDelete,
});

const searchValue = (table: CategoryTable): string =>
    (table.getColumn('name')?.getFilterValue() as string) ?? '';

const setSearch = (table: CategoryTable, value: string | number) => {
    table.getColumn('name')?.setFilterValue(String(value) || undefined);
};
</script>

<template>
    <Head title="Categories" />

    <div class="flex flex-1 flex-col gap-6 px-4 py-6 lg:px-6">
        <header>
            <div class="space-y-1">
                <h1 class="text-2xl font-semibold tracking-tight">
                    Categories
                </h1>
                <p class="text-sm text-muted-foreground">
                    Organize your product catalog.
                </p>
            </div>
        </header>

        <DataTable
            :data="categories"
            :columns="columns"
            empty-message="No categories match this search."
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
                                :model-value="
                                    searchValue(table as CategoryTable)
                                "
                                placeholder="Search categories..."
                                class="h-10 rounded-lg pl-9 shadow-none"
                                @update:model-value="
                                    (value) =>
                                        setSearch(table as CategoryTable, value)
                                "
                            />
                        </div>
                    </div>

                    <Button
                        class="w-full shrink-0 sm:w-auto sm:self-end xl:self-auto"
                        @click="openCreate"
                    >
                        <Plus />
                        Add Category
                    </Button>
                </div>
            </template>
        </DataTable>
    </div>

    <CategoryDialog v-model:open="formOpen" :category="editTarget" />
    <DeleteDialog v-model:open="deleteOpen" :category="deleteTarget" />
</template>
