<script setup lang="ts">
import { FlaskConical } from '@lucide/vue';
import { computed } from 'vue';
import type { Product } from '../types';

const props = withDefaults(
    defineProps<{
        product: Product;
        showDescription?: boolean;
        tableStyle?: boolean;
    }>(),
    {
        showDescription: true,
        tableStyle: false,
    },
);

/** The gallery's first image doubles as the list thumbnail. */
const thumbnail = computed(() => props.product.images[0]?.url ?? null);
</script>

<template>
    <div class="flex min-w-0 items-center gap-3">
        <div
            :class="[
                'flex size-10 shrink-0 items-center justify-center overflow-hidden rounded-lg border bg-muted/60',
                tableStyle &&
                    'size-11 rounded-xl border-sf-serenity-blue/30 bg-sf-serenity-blue/10',
            ]"
        >
            <img
                v-if="thumbnail"
                :src="thumbnail"
                :alt="product.name"
                class="size-full object-cover"
            />
            <FlaskConical
                v-else
                :class="[
                    'size-4 text-muted-foreground',
                    tableStyle && 'text-sf-primary-soft',
                ]"
            />
        </div>
        <div class="max-w-60 min-w-0">
            <div
                :class="[
                    'truncate text-sm leading-5 font-semibold',
                    tableStyle && 'text-sf-ink',
                ]"
            >
                {{ product.name }}
            </div>
            <div
                v-if="showDescription"
                class="line-clamp-2 text-xs leading-4 whitespace-normal text-muted-foreground"
            >
                {{ product.short_description }}
            </div>
        </div>
    </div>
</template>
