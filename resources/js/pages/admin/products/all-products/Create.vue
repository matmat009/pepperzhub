<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { ArrowLeft } from '@lucide/vue';
import { ref } from 'vue';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import { index, store } from '@/routes/admin/products';
import ProductForm from './partials/ProductForm.vue';
import { emptyProductForm } from './types';
import type { ProductFormFields } from './types';

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

const fields = ref<ProductFormFields>(emptyProductForm());
const errors = ref<Partial<Record<keyof ProductFormFields, string>>>({});
const processing = ref(false);

const submit = () => {
    processing.value = true;

    router.post(
        store().url,
        { ...fields.value },
        {
            preserveScroll: true,
            onError: (formErrors) => {
                errors.value = formErrors as Partial<
                    Record<keyof ProductFormFields, string>
                >;
            },
            onFinish: () => {
                processing.value = false;
            },
        },
    );
};
</script>

<template>
    <Head title="New product" />

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
                <h1 class="text-2xl font-semibold tracking-tight">
                    New product
                </h1>
                <p class="text-sm text-muted-foreground">
                    Add a product to your catalog.
                </p>
            </div>
            <div class="flex items-center gap-2">
                <Button
                    variant="outline"
                    :disabled="processing"
                    @click="router.visit(index().url)"
                >
                    Cancel
                </Button>
                <Button :disabled="processing" @click="submit">
                    <Spinner v-if="processing" />
                    Save product
                </Button>
            </div>
        </header>

        <div class="max-w-4xl">
            <ProductForm v-model="fields" :errors="errors" />
        </div>
    </div>
</template>
