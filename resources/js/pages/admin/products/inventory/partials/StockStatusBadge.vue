<script setup lang="ts">
import { Badge } from '@/components/ui/badge';
import type { StockStatus } from '../types';

/**
 * Classified server-side, from ProductVariant::LOW_STOCK_THRESHOLD. This used
 * to take a raw count and apply a threshold of its own, which is how the page
 * came to disagree with the dashboard about what "low" meant.
 */
defineProps<{
    status: StockStatus;
}>();

const tone: Record<StockStatus, string> = {
    'In Stock':
        'border-emerald-600/20 bg-emerald-500/10 text-emerald-700 dark:border-emerald-400/20 dark:text-emerald-400',
    'Low Stock':
        'border-amber-600/20 bg-amber-500/10 text-amber-700 dark:border-amber-400/20 dark:text-amber-400',
    'Out of Stock':
        'border-red-600/20 bg-red-500/10 text-red-700 dark:border-red-400/20 dark:text-red-400',
};

const dot: Record<StockStatus, string> = {
    'In Stock': 'bg-emerald-500',
    'Low Stock': 'bg-amber-500',
    'Out of Stock': 'bg-red-500',
};
</script>

<template>
    <Badge
        variant="outline"
        :class="[
            'gap-1.5 rounded-full px-2 py-0.5 text-xs font-medium whitespace-nowrap',
            tone[status],
        ]"
    >
        <span :class="['size-1.5 rounded-full', dot[status]]" />
        {{ status }}
    </Badge>
</template>
