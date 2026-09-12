<script setup lang="ts">
import { Pencil, Trash2 } from '@lucide/vue';
import { Button } from '@/components/ui/button';
import type { Review } from '../types';

/**
 * Always-visible row actions, matching the payment-methods screen: the row
 * itself opens the read-only view, so hiding Edit and Delete behind a menu
 * would put three intents at three different depths.
 *
 * The actions column is marked `noRowClick`, so pressing either of these never
 * also opens the view dialog.
 */
defineProps<{
    review: Review;
}>();

const emit = defineEmits<{
    edit: [review: Review];
    remove: [review: Review];
}>();
</script>

<template>
    <div class="flex items-center justify-end gap-1.5 whitespace-nowrap">
        <Button
            variant="outline"
            size="xs"
            class="border-sf-serenity-blue/80 bg-sf-serenity-blue/10 px-2 text-sf-primary-soft hover:border-sf-serenity-blue hover:bg-sf-serenity-blue/20 hover:text-sf-primary-deep sm:px-2.5 dark:border-sf-serenity-blue/50 dark:bg-sf-serenity-blue/10 dark:text-sf-serenity-blue dark:hover:bg-sf-serenity-blue/20"
            :aria-label="`Edit ${review.title}`"
            @click="emit('edit', review)"
        >
            <Pencil aria-hidden="true" />
            <span class="hidden sm:inline">Edit</span>
        </Button>
        <Button
            variant="destructive"
            size="xs"
            class="px-2 sm:px-2.5"
            :aria-label="`Delete ${review.title}`"
            @click="emit('remove', review)"
        >
            <Trash2 aria-hidden="true" />
            <span class="hidden sm:inline">Delete</span>
        </Button>
    </div>
</template>
