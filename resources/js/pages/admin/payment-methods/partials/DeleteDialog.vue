<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { Info } from '@lucide/vue';
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
import { destroy } from '@/routes/admin/payment-methods';
import type { PaymentMethod } from '../types';

/**
 * One dialog instance owned by Index.vue, retargeted per row.
 *
 * Unlike the categories dialog there is no blocking state: orders reference a
 * payment method through a nullOnDelete foreign key and snapshot its name and
 * details at checkout, so deletion cannot alter order history. Orders that
 * point at it are surfaced as context rather than as an obstacle — and the
 * everyday action is deactivating, which the note points at.
 */
const props = defineProps<{
    method: PaymentMethod | null;
}>();

const open = defineModel<boolean>('open', { default: false });

const processing = ref(false);

const confirm = () => {
    if (!props.method) {
        return;
    }

    processing.value = true;

    router.delete(destroy(props.method.id).url, {
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
                <AlertDialogTitle>Delete payment method?</AlertDialogTitle>
                <AlertDialogDescription>
                    <template v-if="method">
                        <span class="font-medium text-foreground">{{
                            method.name
                        }}</span>
                        will be permanently removed from checkout. This cannot
                        be undone.
                    </template>
                </AlertDialogDescription>
            </AlertDialogHeader>

            <div
                v-if="method && method.order_count > 0"
                class="flex items-start gap-2.5 rounded-lg border border-blue-600/20 bg-blue-500/10 px-3 py-2.5 text-sm text-blue-800 dark:border-blue-400/20 dark:text-blue-300"
            >
                <Info class="mt-0.5 size-4 shrink-0" />
                <span>
                    {{ method.order_count }}
                    {{ method.order_count === 1 ? 'order' : 'orders' }}
                    used this method. They keep their own copy of these details
                    and will still display correctly. To stop offering it
                    without deleting it, set it inactive instead.
                </span>
            </div>

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
