<script setup lang="ts">
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { ChevronLeft } from '@lucide/vue';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import { index, store } from '@/routes/admin/products';
import ProductForm from './partials/ProductForm.vue';
import { emptyProductForm, toSubmitPayload } from './types';
import type { CategoryOption, ProductFormFields } from './types';

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Products',
                href: index(),
            },
            {
                title: 'New product',
                href: '#',
            },
        ],
    },
});

defineProps<{
    categories: CategoryOption[];
}>();

const form = useForm<ProductFormFields>(emptyProductForm());

/** Abandons the draft and returns to the list. Nothing is persisted yet. */
const discard = () => router.visit(index().url);

const submit = () => {
    form.transform(toSubmitPayload).post(store().url, {
        preserveScroll: true,
    });
};
</script>

<template>
    <Head title="New product" />

    <div class="flex flex-1 flex-col gap-6 px-4 py-6 lg:px-6">
        <header class="flex items-center justify-between gap-4">
            <div class="flex items-center gap-3">
                <Button as-child variant="outline" size="icon" class="shrink-0">
                    <Link :href="index()">
                        <ChevronLeft class="size-4" />
                        <span class="sr-only">Back to products</span>
                    </Link>
                </Button>
                <h1 class="text-2xl font-semibold tracking-tight">
                    Add Products
                </h1>
            </div>

            <div class="flex items-center gap-2">
                <Button
                    variant="outline"
                    :disabled="form.processing"
                    @click="discard"
                >
                    Discard
                </Button>
                <Button :disabled="form.processing" @click="submit">
                    <Spinner v-if="form.processing" />
                    Publish
                </Button>
            </div>
        </header>

        <!--
            `form` carries the field values directly, so it doubles as the
            model. ProductForm only ever mutates nested properties, never
            reassigns the object, so no update listener is needed.
        -->
        <ProductForm
            :model-value="form"
            :categories="categories"
            :errors="form.errors as Record<string, string>"
        />
    </div>
</template>
