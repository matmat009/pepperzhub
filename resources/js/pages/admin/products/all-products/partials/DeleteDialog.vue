<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { ref } from 'vue';
import {
    AlertDialog,
    AlertDialogAction,
    AlertDialogCancel,
    AlertDialogContent,
    AlertDialogDescription,
    AlertDialogFooter,
    AlertDialogHeader,
    AlertDialogTitle,
} from '@/components/ui/alert-dialog';
import { destroy } from '@/routes/admin/products';
import type { Product } from '../types';

/**
 * One dialog instance owned by Index.vue, retargeted per row, rather than one
 * mounted per table row.
 */
const props = defineProps<{
    product: Product | null;
}>();

const open = defineModel<boolean>('open', { default: false });

const processing = ref(false);

const confirm = () => {
    if (!props.product) {
        return;
    }

    processing.value = true;

    router.delete(destroy(props.product.id).url, {
        preserveScroll: true,
        onFinish: () => {
            processing.value = false;
            open.value = false;
        },
    });
};
</script>

<template>
    <AlertDialog v-model:open="open">
        <AlertDialogContent>
            <AlertDialogHeader>
                <AlertDialogTitle>Delete product?</AlertDialogTitle>
                <AlertDialogDescription>
                    <template v-if="product">
                        <span class="font-medium text-foreground">{{
                            product.name
                        }}</span>
                        will be permanently removed from the catalog. This
                        cannot be undone.
                    </template>
                </AlertDialogDescription>
            </AlertDialogHeader>
            <AlertDialogFooter>
                <AlertDialogCancel :disabled="processing">
                    Cancel
                </AlertDialogCancel>
                <AlertDialogAction
                    class="bg-destructive text-white hover:bg-destructive/90"
                    :disabled="processing"
                    @click.prevent="confirm"
                >
                    Delete
                </AlertDialogAction>
            </AlertDialogFooter>
        </AlertDialogContent>
    </AlertDialog>
</template>
