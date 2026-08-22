<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Spinner } from '@/components/ui/spinner';
import { store, update } from '@/routes/admin/products/categories';
import { emptyCategoryForm, toCategoryForm } from '../types';
import type { Category, CategoryFormFields } from '../types';
import CategoryForm from './CategoryForm.vue';

/**
 * One dialog instance owned by Index.vue, serving both create and edit:
 * `category` null means create, a category means edit.
 */
const props = defineProps<{
    category: Category | null;
}>();

const open = defineModel<boolean>('open', { default: false });

const fields = ref<CategoryFormFields>(emptyCategoryForm());
const errors = ref<Partial<Record<keyof CategoryFormFields, string>>>({});
const processing = ref(false);

const isEdit = computed(() => props.category !== null);

// Reseed each time the dialog opens so a cancelled edit never leaks into the
// next one.
watch(open, (isOpen) => {
    if (!isOpen) {
        return;
    }

    fields.value = props.category
        ? toCategoryForm(props.category)
        : emptyCategoryForm();
    errors.value = {};
});

const submit = () => {
    processing.value = true;

    const options = {
        preserveScroll: true,
        onError: (formErrors: Record<string, string>) => {
            errors.value = formErrors as Partial<
                Record<keyof CategoryFormFields, string>
            >;
        },
        onSuccess: () => {
            open.value = false;
        },
        onFinish: () => {
            processing.value = false;
        },
    };

    if (props.category) {
        router.put(update(props.category.id).url, { ...fields.value }, options);

        return;
    }

    router.post(store().url, { ...fields.value }, options);
};
</script>

<template>
    <Dialog v-model:open="open">
        <DialogContent class="sm:max-w-lg">
            <DialogHeader>
                <DialogTitle>
                    {{ isEdit ? 'Edit category' : 'New category' }}
                </DialogTitle>
                <DialogDescription>
                    {{
                        isEdit
                            ? 'Update how this category is described in the catalog.'
                            : 'Group related products under a new category.'
                    }}
                </DialogDescription>
            </DialogHeader>

            <CategoryForm v-model="fields" :errors="errors" />

            <DialogFooter>
                <Button
                    variant="outline"
                    :disabled="processing"
                    @click="open = false"
                >
                    Cancel
                </Button>
                <Button :disabled="processing" @click="submit">
                    <Spinner v-if="processing" />
                    {{ isEdit ? 'Save changes' : 'Create category' }}
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
