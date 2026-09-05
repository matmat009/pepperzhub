<script setup lang="ts">
import { computed } from 'vue';
import { Badge } from '@/components/ui/badge';
import { Checkbox } from '@/components/ui/checkbox';
import { useLowStockThreshold } from '@/composables/useLowStockThreshold';
import { priceRange, totalStock } from '../types';
import type { Product } from '../types';
import ProductCell from './ProductCell.vue';
import RowActions from './RowActions.vue';
import StatusBadge from './StatusBadge.vue';

/**
 * One product, as the table row reads below `md`.
 *
 * Deliberately built from the same partials the columns use — ProductCell,
 * StatusBadge, RowActions — rather than restating their markup, so the card and
 * the row cannot drift apart as any of the three changes.
 */
const props = defineProps<{
    product: Product;
    selected: boolean;
}>();

const emit = defineEmits<{
    'update:selected': [value: boolean];
    open: [product: Product];
    view: [product: Product];
    edit: [product: Product];
    duplicate: [product: Product];
    remove: [product: Product];
}>();

const lowStockThreshold = useLowStockThreshold();

const stock = computed(() => totalStock(props.product.variants));

const stockLabel = computed(() =>
    stock.value === 0 ? 'Out of stock' : `${stock.value} in stock`,
);

/**
 * Same three tiers the Stock column shows, but reading the threshold from the
 * server rather than a literal — see `useLowStockThreshold`.
 */
const stockTone = computed(() => {
    if (stock.value === 0) {
        return 'font-medium text-red-600 dark:text-red-400';
    }

    if (stock.value <= lowStockThreshold.value) {
        return 'font-medium text-amber-600 dark:text-amber-400';
    }

    return 'text-muted-foreground';
});

const formatCount = computed(() => props.product.variants.length);
</script>

<template>
    <!--
        The card is the row-click target, matching the table's `rowClickable`.
        The checkbox and the row menu stop propagation so they stay
        independently tappable, exactly as their `noRowClick` cells do.
    -->
    <div
        :class="[
            'rounded-xl border bg-card p-3 transition-colors',
            selected ? 'border-primary/40 bg-muted/40' : 'active:bg-muted/40',
        ]"
        @click="emit('open', product)"
    >
        <div class="flex items-start gap-3">
            <span class="pt-0.5" @click.stop>
                <Checkbox
                    :model-value="selected"
                    :aria-label="`Select ${product.name}`"
                    @update:model-value="
                        (value) => emit('update:selected', !!value)
                    "
                />
            </span>

            <!--
                The card's keyboard affordance, mirroring the one DataTable puts
                in a row's first clickable cell: a real button, so Enter and
                Space are the platform's job, and its accessible name comes from
                the product name it wraps.
            -->
            <button
                type="button"
                class="min-w-0 flex-1 cursor-pointer rounded-sm text-left focus-visible:outline-2 focus-visible:-outline-offset-2 focus-visible:outline-ring"
                @click.stop="emit('open', product)"
            >
                <ProductCell :product="product" />
            </button>

            <span @click.stop>
                <RowActions
                    :product="product"
                    @view="emit('view', product)"
                    @edit="emit('edit', product)"
                    @duplicate="emit('duplicate', product)"
                    @remove="emit('remove', product)"
                />
            </span>
        </div>

        <!-- Indented past the checkbox so it lines up under the product name. -->
        <div class="mt-3 flex items-end justify-between gap-3 pl-8">
            <div class="min-w-0">
                <div
                    class="text-base font-semibold whitespace-nowrap tabular-nums"
                >
                    {{ priceRange(product.variants) }}
                </div>
                <div :class="['mt-0.5 text-xs tabular-nums', stockTone]">
                    {{ stockLabel }}
                </div>
            </div>

            <div
                class="flex shrink-0 flex-wrap items-center justify-end gap-1.5"
            >
                <StatusBadge :status="product.status" />
                <Badge
                    variant="secondary"
                    class="rounded-md font-normal text-muted-foreground"
                >
                    {{ formatCount }} format{{ formatCount === 1 ? '' : 's' }}
                </Badge>
            </div>
        </div>
    </div>
</template>
