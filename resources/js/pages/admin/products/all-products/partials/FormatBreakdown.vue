<script setup lang="ts">
import { Package } from '@lucide/vue';
import { Badge } from '@/components/ui/badge';
import { formatPrice } from '../types';
import type { Product } from '../types';

/**
 * Per-format detail for an expanded row in the products table.
 *
 * Deliberately not a nested <table>: it reads as detail hanging off the row
 * above, not as a second grid. Styling matches the Formats & Pricing card so
 * the same data looks the same in both places.
 */
defineProps<{
    product: Product;
}>();
</script>

<template>
    <div class="py-1 pr-4 pl-14">
        <ul class="divide-y divide-border/60">
            <li
                v-for="variant in product.variants"
                :key="variant.id"
                class="flex items-center gap-4 py-2.5"
            >
                <div class="min-w-0 flex-1">
                    <div class="flex flex-wrap items-center gap-2">
                        <span class="text-sm font-medium">
                            {{ variant.label }}
                        </span>
                        <Badge
                            v-if="variant.is_kit"
                            variant="outline"
                            class="gap-1 rounded-md border-primary/20 bg-primary/5 px-1.5 py-0 text-[11px] font-medium"
                        >
                            <Package class="size-3" />
                            Kit
                        </Badge>
                    </div>
                    <p
                        v-if="variant.is_kit && variant.kit_inclusions.length"
                        class="mt-0.5 text-xs text-muted-foreground"
                    >
                        {{ variant.kit_inclusions.length }} item{{
                            variant.kit_inclusions.length === 1 ? '' : 's'
                        }}
                        included
                    </p>
                </div>

                <div class="w-24 shrink-0 text-right">
                    <div class="text-sm font-medium tabular-nums">
                        {{ formatPrice(variant.price) }}
                    </div>
                    <div class="text-xs text-muted-foreground">price</div>
                </div>

                <div class="w-20 shrink-0 text-right">
                    <div
                        :class="[
                            'text-sm tabular-nums',
                            variant.stock === 0
                                ? 'font-medium text-red-600 dark:text-red-400'
                                : 'font-medium',
                        ]"
                    >
                        {{ variant.stock }}
                    </div>
                    <div class="text-xs text-muted-foreground">in stock</div>
                </div>
            </li>
        </ul>
    </div>
</template>
