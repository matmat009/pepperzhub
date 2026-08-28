<script setup lang="ts">
import { FlaskConical } from '@lucide/vue';
import { computed } from 'vue';
import type { Product } from '@/pages/admin/products/all-products/types';

/**
 * Product image with the same FlaskConical fallback the admin list uses, so a
 * product with an empty gallery still occupies its slot instead of collapsing.
 */
const props = withDefaults(
    defineProps<{
        product: Pick<Product, 'name' | 'images'>;
        /** Tailwind size class for the fallback glyph. */
        iconClass?: string;
    }>(),
    { iconClass: 'size-8' },
);

const image = computed(() => props.product.images[0] ?? null);
</script>

<template>
    <img
        v-if="image"
        :src="image.url"
        :alt="product.name"
        loading="lazy"
        class="size-full object-contain"
    />
    <span v-else class="grid size-full place-items-center">
        <FlaskConical :class="iconClass" class="text-sf-primary/35" />
    </span>
</template>
