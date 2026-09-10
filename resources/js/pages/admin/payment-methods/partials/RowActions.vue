<script setup lang="ts">
import { Pencil, Trash2 } from '@lucide/vue';
import { Button } from '@/components/ui/button';
import type { PaymentMethod } from '../types';

/**
 * Always-visible row actions, no dropdown.
 *
 * The row itself opens the read-only view, so hiding Edit and Delete behind a
 * menu would put three different intents at three different depths. They emit
 * exactly what the dropdown emitted; only the trigger changed.
 *
 * The actions column is marked `noRowClick`, so pressing either of these never
 * also opens the view dialog.
 */
defineProps<{
    method: PaymentMethod;
}>();

const emit = defineEmits<{
    edit: [method: PaymentMethod];
    remove: [method: PaymentMethod];
}>();
</script>

<template>
    <div class="flex items-center justify-end gap-1.5 whitespace-nowrap">
        <Button
            variant="outline"
            size="sm"
            class="rounded-full border-sf-serenity-blue/80 bg-sf-serenity-blue/10 px-3 text-sf-primary-soft hover:border-sf-serenity-blue hover:bg-sf-serenity-blue/20 hover:text-sf-primary-deep dark:border-sf-serenity-blue/50 dark:bg-sf-serenity-blue/10 dark:text-sf-serenity-blue dark:hover:bg-sf-serenity-blue/20"
            :aria-label="`Edit ${method.name}`"
            @click="emit('edit', method)"
        >
            <Pencil aria-hidden="true" />
            Edit
        </Button>
        <Button
            variant="destructive"
            size="sm"
            class="rounded-full px-3"
            :aria-label="`Delete ${method.name}`"
            @click="emit('remove', method)"
        >
            <Trash2 aria-hidden="true" />
            Delete
        </Button>
    </div>
</template>
