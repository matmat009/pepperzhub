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
import { destroy } from '@/routes/admin/shipping-couriers';
import type { ShippingCourier } from '../types';

/**
 * One dialog instance owned by Index.vue, retargeted per row.
 *
 * No blocking state: regions cascade with the courier, and orders reference
 * both through nullOnDelete foreign keys while snapshotting the courier name
 * and region label at checkout, so deletion cannot alter order history.
 */
const props = defineProps<{
    courier: ShippingCourier | null;
}>();

const open = defineModel<boolean>('open', { default: false });

const processing = ref(false);

const confirm = () => {
    if (!props.courier) {
        return;
    }

    processing.value = true;

    router.delete(destroy(props.courier.id).url, {
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
                <AlertDialogTitle>Delete courier?</AlertDialogTitle>
                <AlertDialogDescription>
                    <template v-if="courier">
                        <span class="font-medium text-foreground">{{
                            courier.name
                        }}</span>
                        will be permanently removed from checkout. This cannot
                        be undone.
                    </template>
                </AlertDialogDescription>
            </AlertDialogHeader>

            <div
                v-if="courier"
                class="flex items-start gap-2.5 rounded-lg border border-blue-600/20 bg-blue-500/10 px-3 py-2.5 text-sm text-blue-800 dark:border-blue-400/20 dark:text-blue-300"
            >
                <Info class="mt-0.5 size-4 shrink-0" />
                <span>
                    <template v-if="courier.regions.length">
                        Its
                        {{ courier.regions.length }}
                        {{
                            courier.regions.length === 1 ? 'region' : 'regions'
                        }}
                        will be removed too.
                    </template>
                    Past orders keep their own copy of the courier and region
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
