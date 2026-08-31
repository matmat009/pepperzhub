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
    <!--
        `max-h/max-w-full` rather than `size-full`: a percentage height on a
        replaced element resolves against the box before object-fit runs, which
        lets a tall source render past the well's edge. Capping by the image's
        own intrinsic size instead means it can never exceed the box, whatever
        aspect ratio it arrives with.
    -->
    <span class="relative block size-full">
        <img
            v-if="image"
            :src="image.url"
            :alt="product.name"
            loading="lazy"
            class="absolute inset-0 size-full rounded-lg object-contain"
        />
        <FlaskConical
            v-else
            :class="iconClass"
            class="absolute inset-0 m-auto text-sf-primary/35"
        />
    </span>
</template>
