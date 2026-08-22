<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { ArrowLeft, Pencil } from '@lucide/vue';
import { ref, watch } from 'vue';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import { index, update } from '@/routes/admin/products';
import ProductForm from './partials/ProductForm.vue';
import StatusBadge from './partials/StatusBadge.vue';
import { formatPrice, toProductForm } from './types';
import type { Product, ProductFormFields } from './types';

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Products',
                href: index(),
            },
            {
                title: 'Product',
                href: '#',
            },
        ],
    },
});

const props = defineProps<{
    product: Product;
}>();

const fields = ref<ProductFormFields>(toProductForm(props.product));
const errors = ref<Partial<Record<keyof ProductFormFields, string>>>({});
const processing = ref(false);

/**
 * Readonly by default; `?edit=1` (used by the table's row menu) opens straight
 * into the editable state.
 */
const editing = ref(
    typeof window !== 'undefined' &&
        new URLSearchParams(window.location.search).get('edit') === '1',
);

// A successful save re-renders with fresh props; rebase the fields on them so a
// later Cancel reverts to the saved values rather than the ones first loaded.
watch(
    () => props.product,
    (product) => {
        fields.value = toProductForm(product);
    },
);

const startEditing = () => {
    editing.value = true;
};

const cancel = () => {
    fields.value = toProductForm(props.product);
    errors.value = {};
    editing.value = false;
};

const submit = () => {
    processing.value = true;

    router.put(
        update(props.product.id).url,
        { ...fields.value },
        {
            preserveScroll: true,
            onError: (formErrors) => {
                errors.value = formErrors as Partial<
                    Record<keyof ProductFormFields, string>
                >;
            },
            onSuccess: () => {
                errors.value = {};
                editing.value = false;
            },
            onFinish: () => {
                processing.value = false;
            },
        },
    );
};
</script>

<template>
    <Head :title="product.name" />

    <div class="flex flex-1 flex-col gap-6 px-4 py-6 lg:px-6">
        <header class="flex flex-wrap items-start justify-between gap-4">
            <div class="space-y-1">
                <Button
                    as-child
                    variant="ghost"
                    size="sm"
                    class="-ml-2 h-7 px-2 text-muted-foreground"
                >
                    <Link :href="index()">
                        <ArrowLeft class="size-3.5" />
                        Products
                    </Link>
                </Button>
                <div class="flex flex-wrap items-center gap-3">
                    <h1 class="text-2xl font-semibold tracking-tight">
                        {{ product.name }}
                    </h1>
                    <StatusBadge :status="product.status" />
                </div>
                <p class="text-sm text-muted-foreground">
                    {{ product.type }} &middot; {{ product.category }} &middot;
                    {{ formatPrice(product.price) }}
                </p>
            </div>

            <div class="flex items-center gap-2">
                <template v-if="editing">
                    <Button
                        variant="outline"
                        :disabled="processing"
                        @click="cancel"
                    >
                        Cancel
                    </Button>
                    <Button :disabled="processing" @click="submit">
                        <Spinner v-if="processing" />
                        Save changes
                    </Button>
                </template>
                <Button v-else @click="startEditing">
                    <Pencil />
                    Edit
                </Button>
            </div>
        </header>

        <div class="max-w-4xl">
            <ProductForm
                v-model="fields"
                :errors="errors"
                :readonly="!editing"
            />
        </div>
    </div>
</template>
