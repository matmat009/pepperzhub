<script setup lang="ts">
import type { Table } from '@tanstack/vue-table';
import { Head } from '@inertiajs/vue3';
import { Plus, Search } from '@lucide/vue';
import { ref } from 'vue';
import DataTable from '@/components/DataTable.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import type { Features } from '@/components/features';
import { index } from '@/routes/admin/payment-methods';
import { createPaymentMethodColumns } from './columns';
import DeleteDialog from './partials/DeleteDialog.vue';
import PaymentMethodDialog from './partials/PaymentMethodDialog.vue';
import ViewDialog from './partials/ViewDialog.vue';
import type { PaymentMethod } from './types';

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Payments',
                href: index(),
            },
        ],
    },
});

defineProps<{
    paymentMethods: PaymentMethod[];
}>();

type PaymentMethodTable = Table<Features, PaymentMethod>;

const editTarget = ref<PaymentMethod | null>(null);
const formOpen = ref(false);
const deleteTarget = ref<PaymentMethod | null>(null);
const deleteOpen = ref(false);
const viewTarget = ref<PaymentMethod | null>(null);
const viewOpen = ref(false);

/** Row click and Enter/Space both land here; see DataTable's rowClickable. */
const openView = (method: PaymentMethod) => {
    viewTarget.value = method;
    viewOpen.value = true;
};

const openCreate = () => {
    editTarget.value = null;
    formOpen.value = true;
};

const openEdit = (method: PaymentMethod) => {
    editTarget.value = method;
    formOpen.value = true;
};

const requestDelete = (method: PaymentMethod) => {
    deleteTarget.value = method;
    deleteOpen.value = true;
};

const columns = createPaymentMethodColumns({
    onEdit: openEdit,
    onDelete: requestDelete,
});

const searchValue = (table: PaymentMethodTable): string =>
    (table.getColumn('name')?.getFilterValue() as string) ?? '';

const setSearch = (table: PaymentMethodTable, value: string | number) => {
    table.getColumn('name')?.setFilterValue(String(value) || undefined);
};
</script>

<template>
    <Head title="Payments" />

    <div class="flex flex-1 flex-col gap-6 px-4 py-6 lg:px-6">
        <header class="flex flex-wrap items-start justify-between gap-4">
            <div class="space-y-1">
                <h1 class="text-2xl font-semibold tracking-tight">Payments</h1>
                <p class="text-sm text-muted-foreground">
                    How customers pay you at checkout. Set a method inactive to
                    stop offering it without losing it.
                </p>
            </div>
            <Button @click="openCreate">
                <Plus />
                Add Method
            </Button>
        </header>

        <DataTable
            :data="paymentMethods"
            :columns="columns"
            row-clickable
            empty-message="No payment methods match this search."
            @row-click="openView"
        >
            <template #toolbar="{ table }">
                <div class="flex flex-wrap items-center gap-2">
                    <div class="relative flex-1 sm:max-w-xs">
                        <Search
                            class="pointer-events-none absolute top-1/2 left-2.5 size-4 -translate-y-1/2 text-muted-foreground"
                        />
                        <Input
                            :model-value="
                                searchValue(table as PaymentMethodTable)
                            "
                            placeholder="Search methods..."
                            class="h-9 pl-8"
                            @update:model-value="
                                (value) =>
                                    setSearch(
                                        table as PaymentMethodTable,
                                        value,
                                    )
                            "
                        />
                    </div>
                </div>
            </template>
        </DataTable>
    </div>

    <ViewDialog v-model:open="viewOpen" :method="viewTarget" @edit="openEdit" />
    <PaymentMethodDialog v-model:open="formOpen" :method="editTarget" />
    <DeleteDialog v-model:open="deleteOpen" :method="deleteTarget" />
</template>
