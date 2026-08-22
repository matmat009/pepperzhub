<script setup lang="ts">
import { FlaskConical } from '@lucide/vue';
import type { InventoryItem } from '../types';

defineProps<{
    item: InventoryItem;
}>();
</script>

<template>
    <div class="flex min-w-0 items-center gap-3">
        <div
            class="flex size-9 shrink-0 items-center justify-center overflow-hidden rounded-md border bg-muted/60"
        >
            <img
                v-if="item.thumbnail"
                :src="item.thumbnail"
                :alt="item.name"
                class="size-full object-cover"
            />
            <FlaskConical v-else class="size-4 text-muted-foreground" />
        </div>
        <div class="min-w-0">
            <div class="truncate text-sm font-medium">{{ item.name }}</div>
            <div class="truncate text-xs text-muted-foreground">
                {{ item.category }}
            </div>
        </div>
    </div>
</template>
