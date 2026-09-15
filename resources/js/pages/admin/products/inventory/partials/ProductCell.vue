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
                :alt="item.product_name"
                class="size-full object-cover"
            />
            <FlaskConical v-else class="size-4 text-muted-foreground" />
        </div>
        <!--
            The format sits under the name rather than the category: a product
            has one row per format now, and the name alone would give two rows
            that read identically. The category still has its own column.
        -->
        <div class="min-w-0">
            <div class="truncate text-sm font-medium">
                {{ item.product_name }}
            </div>
            <div class="truncate text-xs text-muted-foreground">
                {{ item.variant_label }}
            </div>
        </div>
    </div>
</template>
