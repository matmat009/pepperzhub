<script setup lang="ts">
import { Pencil, Trash2 } from '@lucide/vue';
import { Button } from '@/components/ui/button';
import type { Category } from '../types';

defineProps<{
    category: Category;
}>();

const emit = defineEmits<{
    edit: [category: Category];
    remove: [category: Category];
}>();
</script>

<template>
    <div class="flex items-center justify-end gap-1.5 whitespace-nowrap">
        <Button
            variant="outline"
            size="xs"
            class="border-sf-serenity-blue/80 bg-sf-serenity-blue/10 px-2 text-sf-primary-soft hover:border-sf-serenity-blue hover:bg-sf-serenity-blue/20 hover:text-sf-primary-deep sm:px-2.5 dark:border-sf-serenity-blue/50 dark:bg-sf-serenity-blue/10 dark:text-sf-serenity-blue dark:hover:bg-sf-serenity-blue/20"
            :aria-label="`Edit ${category.name}`"
            @click="emit('edit', category)"
        >
            <Pencil aria-hidden="true" />
            <span class="hidden sm:inline">Edit</span>
        </Button>
        <Button
            variant="destructive"
            size="xs"
            class="px-2 sm:px-2.5"
            :aria-label="`Delete ${category.name}`"
            @click="emit('remove', category)"
        >
            <Trash2 aria-hidden="true" />
            <span class="hidden sm:inline">Delete</span>
        </Button>
    </div>
</template>
