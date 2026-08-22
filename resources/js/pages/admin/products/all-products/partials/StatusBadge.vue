<script setup lang="ts">
import { Badge } from '@/components/ui/badge';
import type { ProductStatus } from '../types';

defineProps<{
    status: ProductStatus;
}>();

const tone: Record<ProductStatus, string> = {
    Active: 'border-emerald-600/20 bg-emerald-500/10 text-emerald-700 dark:border-emerald-400/20 dark:text-emerald-400',
    Draft: 'border-border bg-muted text-muted-foreground',
    Archived:
        'border-red-600/20 bg-red-500/10 text-red-700 dark:border-red-400/20 dark:text-red-400',
};

const dot: Record<ProductStatus, string> = {
    Active: 'bg-emerald-500',
    Draft: 'bg-muted-foreground/40',
    Archived: 'bg-red-500',
};
</script>

<template>
    <Badge
        variant="outline"
        :class="[
            'gap-1.5 rounded-full px-2 py-0.5 text-xs font-medium',
            tone[status],
        ]"
    >
        <span :class="['size-1.5 rounded-full', dot[status]]" />
        {{ status }}
    </Badge>
</template>
