<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ChevronLeft, Pencil } from '@lucide/vue';
import { computed, ref, watch } from 'vue';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import { index, update } from '@/routes/admin/products';
import ProductForm from './partials/ProductForm.vue';
import StatusBadge from './partials/StatusBadge.vue';
import { priceRange, toProductForm, toSubmitPayload } from './types';
import type { CategoryOption, Product, ProductFormFields } from './types';

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
    categories: CategoryOption[];
}>();

const form = useForm<ProductFormFields>(toProductForm(props.product));

/**
 * Readonly by default; `?edit=1` (used by the table's row menu) opens straight
 * into the editable state.
 */
const editing = ref(
    typeof window !== 'undefined' &&
        new URLSearchParams(window.location.search).get('edit') === '1',
);

// A successful save re-renders with fresh props; rebase the form on them so a
// later Cancel reverts to the saved values rather than the ones first loaded.
watch(
    () => props.product,
    (product) => {
        form.defaults(toProductForm(product));
        form.reset();
    },
);

const formatCount = computed(() => props.product.variants.length);

const startEditing = () => {
    editing.value = true;
};

/** Reverts fields, formats and images in one call. */
const cancel = () => {
    form.clearErrors();
    form.reset();
    editing.value = false;
};

const submit = () => {
    // FormData cannot ride a real PUT, so the update is spoofed over POST —
    // required as soon as image files are in the payload.
    form.transform((fields) => ({
        ...toSubmitPayload(fields),
        _method: 'put',
    })).post(update(props.product.id).url, {
        preserveScroll: true,
        onSuccess: () => {
            editing.value = false;
        },
    });
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
                        <ChevronLeft class="size-3.5" />
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
                    {{ product.category }} &middot; {{ formatCount }} format{{
                        formatCount === 1 ? '' : 's'
                    }}
                    &middot; {{ priceRange(product.variants) }}
                </p>
            </div>

            <div class="flex items-center gap-2">
                <template v-if="editing">
                    <Button
                        variant="outline"
                        :disabled="form.processing"
                        @click="cancel"
                    >
                        Cancel
                    </Button>
                    <Button :disabled="form.processing" @click="submit">
                        <Spinner v-if="form.processing" />
                        Save
                    </Button>
                </template>
                <Button v-else @click="startEditing">
                    <Pencil />
                    Edit
                </Button>
            </div>
        </header>

        <ProductForm
            :model-value="form"
            :categories="categories"
            :errors="form.errors as Record<string, string>"
            :readonly="!editing"
        />
    </div>
</template>
