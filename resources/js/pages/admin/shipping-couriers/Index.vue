<script setup lang="ts">
import type { Table } from '@tanstack/vue-table';
import { Head } from '@inertiajs/vue3';
import { Plus, Search } from '@lucide/vue';
import { ref } from 'vue';
import DataTable from '@/components/DataTable.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import type { Features } from '@/components/features';
import { index } from '@/routes/admin/shipping-couriers';
import { createCourierColumns } from './columns';
import CourierDialog from './partials/CourierDialog.vue';
import DeleteDialog from './partials/DeleteDialog.vue';
import ViewDialog from './partials/ViewDialog.vue';
import type { ShippingCourier } from './types';

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Shipping',
                href: index(),
            },
        ],
    },
});

defineProps<{
    couriers: ShippingCourier[];
}>();

type CourierTable = Table<Features, ShippingCourier>;

const editTarget = ref<ShippingCourier | null>(null);
const formOpen = ref(false);
const deleteTarget = ref<ShippingCourier | null>(null);
const deleteOpen = ref(false);
const viewTarget = ref<ShippingCourier | null>(null);
const viewOpen = ref(false);

/** Row click and Enter/Space both land here; see DataTable's rowClickable. */
const openView = (courier: ShippingCourier) => {
    viewTarget.value = courier;
    viewOpen.value = true;
};

const openCreate = () => {
    editTarget.value = null;
    formOpen.value = true;
};

const openEdit = (courier: ShippingCourier) => {
    editTarget.value = courier;
    formOpen.value = true;
};

const requestDelete = (courier: ShippingCourier) => {
    deleteTarget.value = courier;
    deleteOpen.value = true;
};

const columns = createCourierColumns({
    onEdit: openEdit,
    onDelete: requestDelete,
});

const searchValue = (table: CourierTable): string =>
    (table.getColumn('name')?.getFilterValue() as string) ?? '';

const setSearch = (table: CourierTable, value: string | number) => {
    table.getColumn('name')?.setFilterValue(String(value) || undefined);
};
</script>

<template>
    <Head title="Shipping" />

    <div class="flex flex-1 flex-col gap-6 px-4 py-6 lg:px-6">
        <header class="flex flex-wrap items-start justify-between gap-4">
            <div class="space-y-1">
                <h1 class="text-2xl font-semibold tracking-tight">Shipping</h1>
                <p class="text-sm text-muted-foreground">
                    Couriers and their flat regional rates. Set one inactive to
                    stop offering it without losing it.
                </p>
            </div>
            <Button @click="openCreate">
                <Plus />
                Add Courier
            </Button>
        </header>

        <DataTable
            :data="couriers"
            :columns="columns"
            row-clickable
            empty-message="No couriers match this search."
            @row-click="openView"
        >
            <template #toolbar="{ table }">
                <div class="flex flex-wrap items-center gap-2">
                    <div class="relative flex-1 sm:max-w-xs">
                        <Search
                            class="pointer-events-none absolute top-1/2 left-2.5 size-4 -translate-y-1/2 text-muted-foreground"
                        />
                        <Input
                            :model-value="searchValue(table as CourierTable)"
                            placeholder="Search couriers..."
                            class="h-9 pl-8"
                            @update:model-value="
                                (value) =>
                                    setSearch(table as CourierTable, value)
                            "
                        />
                    </div>
                </div>
            </template>
        </DataTable>
    </div>

    <ViewDialog
        v-model:open="viewOpen"
        :courier="viewTarget"
        @edit="openEdit"
    />
    <CourierDialog v-model:open="formOpen" :courier="editTarget" />
    <DeleteDialog v-model:open="deleteOpen" :courier="deleteTarget" />
</template>
