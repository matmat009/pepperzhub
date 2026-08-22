<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { TriangleAlert } from '@lucide/vue';
import { computed, ref } from 'vue';
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
import { destroy } from '@/routes/admin/products/categories';
import { blockedReason } from '../types';
import type { Category } from '../types';

/**
 * One dialog instance owned by Index.vue, retargeted per row.
 *
 * A category still holding products cannot be deleted: the confirm button is
 * replaced by a blocking message. CategoryController enforces the same rule, so
 * the guard does not depend on the client.
 */
const props = defineProps<{
    category: Category | null;
}>();

const open = defineModel<boolean>('open', { default: false });

const processing = ref(false);

const blocked = computed(() => blockedReason(props.category));

const confirm = () => {
    if (!props.category || blocked.value) {
        return;
    }

    processing.value = true;

    router.delete(destroy(props.category.id).url, {
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
                <AlertDialogTitle>
                    {{
                        blocked ? 'Cannot delete category' : 'Delete category?'
                    }}
                </AlertDialogTitle>
                <AlertDialogDescription>
                    <template v-if="category && blocked">
                        <span class="font-medium text-foreground">{{
                            category.name
                        }}</span>
                        still has products assigned to it.
                    </template>
                    <template v-else-if="category">
                        <span class="font-medium text-foreground">{{
                            category.name
                        }}</span>
                        will be permanently removed. This cannot be undone.
                    </template>
                </AlertDialogDescription>
            </AlertDialogHeader>

            <div
                v-if="blocked"
                class="flex items-start gap-2.5 rounded-lg border border-amber-600/20 bg-amber-500/10 px-3 py-2.5 text-sm text-amber-800 dark:border-amber-400/20 dark:text-amber-300"
            >
                <TriangleAlert class="mt-0.5 size-4 shrink-0" />
                <span>{{ blocked }}</span>
            </div>

            <AlertDialogFooter>
                <AlertDialogCancel :disabled="processing">
                    {{ blocked ? 'Close' : 'Cancel' }}
                </AlertDialogCancel>
                <AlertDialogAction
                    v-if="!blocked"
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
