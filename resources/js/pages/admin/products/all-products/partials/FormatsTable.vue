<script setup lang="ts">
import { Package, Pencil, Trash2 } from '@lucide/vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Table,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { formatPrice } from '../types';
import type { ProductVariant } from '../types';

/**
 * In-form summary of a product's formats.
 *
 * Deliberately not DataTable.vue — that shell carries pagination, sorting and
 * selection, none of which a three-row editable list wants.
 */
withDefaults(
    defineProps<{
        variants: ProductVariant[];
        readonly?: boolean;
    }>(),
    { readonly: false },
);

const emit = defineEmits<{
    edit: [variant: ProductVariant];
    remove: [variant: ProductVariant];
}>();
</script>

<template>
    <div class="overflow-hidden rounded-lg border">
        <Table>
            <TableHeader class="bg-muted/40">
                <TableRow class="hover:bg-transparent">
                    <TableHead
                        class="h-10 text-xs font-medium text-muted-foreground"
                    >
                        Format
                    </TableHead>
                    <TableHead
                        class="h-10 text-right text-xs font-medium text-muted-foreground"
                    >
                        Price
                    </TableHead>
                    <TableHead
                        class="h-10 text-right text-xs font-medium text-muted-foreground"
                    >
                        Stock
                    </TableHead>
                    <TableHead
                        v-if="!readonly"
                        class="h-10 w-20 text-xs font-medium text-muted-foreground"
                    >
                        <span class="sr-only">Actions</span>
                    </TableHead>
                </TableRow>
            </TableHeader>

            <TransitionGroup
                tag="tbody"
                enter-active-class="transition-[opacity,transform] duration-200 ease-out motion-reduce:transition-opacity"
                enter-from-class="-translate-y-1 opacity-0 motion-reduce:translate-y-0"
                leave-active-class="transition-[opacity,transform] duration-150 ease-out motion-reduce:transition-opacity"
                leave-to-class="-translate-y-1 opacity-0 motion-reduce:translate-y-0"
            >
                <TableRow v-for="variant in variants" :key="variant.id">
                    <TableCell class="py-3">
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
                            v-if="
                                variant.is_kit && variant.kit_inclusions.length
                            "
                            class="mt-0.5 text-xs text-muted-foreground"
                        >
                            {{ variant.kit_inclusions.length }} item{{
                                variant.kit_inclusions.length === 1 ? '' : 's'
                            }}
                            included
                        </p>
                    </TableCell>
                    <TableCell class="py-3 text-right font-medium tabular-nums">
                        {{ formatPrice(variant.price) }}
                    </TableCell>
                    <TableCell
                        :class="[
                            'py-3 text-right tabular-nums',
                            variant.stock === 0
                                ? 'font-medium text-red-600 dark:text-red-400'
                                : '',
                        ]"
                    >
                        {{ variant.stock }}
                    </TableCell>
                    <TableCell v-if="!readonly" class="py-3">
                        <div class="flex items-center justify-end gap-1">
                            <Button
                                type="button"
                                variant="ghost"
                                size="icon"
                                class="size-8 text-muted-foreground"
                                @click="emit('edit', variant)"
                            >
                                <Pencil class="size-3.5" />
                                <span class="sr-only">
                                    Edit {{ variant.label }}
                                </span>
                            </Button>
                            <Button
                                type="button"
                                variant="ghost"
                                size="icon"
                                class="size-8 text-muted-foreground hover:text-destructive"
                                @click="emit('remove', variant)"
                            >
                                <Trash2 class="size-3.5" />
                                <span class="sr-only">
                                    Remove {{ variant.label }}
                                </span>
                            </Button>
                        </div>
                    </TableCell>
                </TableRow>

                <TableRow v-if="!variants.length" key="empty">
                    <TableCell
                        :colspan="readonly ? 3 : 4"
                        class="h-24 text-center text-sm text-muted-foreground"
                    >
                        No formats yet — add the sizes this product ships in.
                    </TableCell>
                </TableRow>
            </TransitionGroup>
        </Table>
    </div>
</template>
