<script setup lang="ts">
import { FlaskConical } from '@lucide/vue';
import { computed } from 'vue';
import type { Product } from '../types';

const props = defineProps<{
    product: Product;
}>();

/** The gallery's first image doubles as the list thumbnail. */
const thumbnail = computed(() => props.product.images[0]?.url ?? null);
</script>

<template>
    <div class="flex min-w-0 items-center gap-3">
        <div
            class="flex size-9 shrink-0 items-center justify-center overflow-hidden rounded-md border bg-muted/60"
        >
            <img
                v-if="thumbnail"
                :src="thumbnail"
                :alt="product.name"
                class="size-full object-cover"
            />
            <FlaskConical v-else class="size-4 text-muted-foreground" />
        </div>
        <div class="min-w-0">
            <div class="truncate text-sm font-medium">{{ product.name }}</div>
            <div class="truncate text-xs text-muted-foreground">
                {{ product.short_description }}
            </div>
        </div>
    </div>
</template>
