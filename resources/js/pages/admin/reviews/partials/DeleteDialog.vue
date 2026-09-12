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
import { destroy } from '@/routes/admin/reviews';
import type { Review } from '../types';

/**
 * One dialog instance owned by Index.vue, retargeted per row.
 *
 * No blocking state, for the same reason payment methods have none: nothing
 * downstream depends on a review surviving. The everyday action is still
 * deactivating, which the description points at.
 */
const props = defineProps<{
    review: Review | null;
}>();

const open = defineModel<boolean>('open', { default: false });

const processing = ref(false);

const confirm = () => {
    if (!props.review) {
        return;
    }

    processing.value = true;

    router.delete(destroy(props.review.id).url, {
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
                <AlertDialogTitle>Delete review?</AlertDialogTitle>
                <AlertDialogDescription>
                    <template v-if="review">
                        <span class="font-medium text-foreground">{{
                            review.title
                        }}</span>
                        will be permanently removed, along with its photo. To
                        take it off the storefront without losing it, set it
                        inactive instead.
                    </template>
                </AlertDialogDescription>
            </AlertDialogHeader>

            <AlertDialogFooter>
                <AlertDialogCancel :disabled="processing">
                    Cancel
                </AlertDialogCancel>
                <AlertDialogAction
                    variant="destructive"
                    :loading="processing"
                    @click.prevent="confirm"
                >
                    Delete
                </AlertDialogAction>
            </AlertDialogFooter>
        </AlertDialogContent>
    </AlertDialog>
</template>
