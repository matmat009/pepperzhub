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
import { bulkDestroy } from '@/routes/admin/products';

const props = defineProps<{
    ids: number[];
}>();

const emit = defineEmits<{
    deleted: [];
}>();

const open = defineModel<boolean>('open', { default: false });

const processing = ref(false);

const confirm = () => {
    if (!props.ids.length) {
        return;
    }

    processing.value = true;

    router.delete(bulkDestroy().url, {
        data: { ids: props.ids },
        preserveScroll: true,
        onSuccess: () => {
            emit('deleted');
        },
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
                <AlertDialogTitle>Delete selected products?</AlertDialogTitle>
                <AlertDialogDescription>
                    {{ ids.length }}
                    {{ ids.length === 1 ? 'product' : 'products' }} will be
                    permanently removed from the catalog. This cannot be undone.
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
                    Delete {{ ids.length }}
                </AlertDialogAction>
            </AlertDialogFooter>
        </AlertDialogContent>
    </AlertDialog>
</template>
