<script setup lang="ts">
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
    <div class="flex items-center justify-end gap-1">
        <Button
            variant="ghost"
            size="sm"
            class="h-8 px-2 text-muted-foreground hover:text-foreground"
            @click="emit('edit', method)"
        >
            Edit
        </Button>
        <Button
            variant="ghost"
            size="sm"
            class="h-8 px-2 text-muted-foreground hover:text-destructive"
            @click="emit('remove', method)"
        >
            Delete
        </Button>
    </div>
</template>
