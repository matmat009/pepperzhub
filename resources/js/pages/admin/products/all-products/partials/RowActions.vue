<script setup lang="ts">
import { Copy, Eye, MoreHorizontal, Pencil, Trash2 } from '@lucide/vue';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import type { Product } from '../types';

withDefaults(
    defineProps<{
        product: Product;
        tableStyle?: boolean;
    }>(),
    {
        tableStyle: false,
    },
);

const emit = defineEmits<{
    view: [product: Product];
    edit: [product: Product];
    duplicate: [product: Product];
    remove: [product: Product];
}>();
</script>

<template>
    <DropdownMenu>
        <DropdownMenuTrigger as-child>
            <Button
                variant="ghost"
                size="icon-sm"
                :class="[
                    'data-[state=open]:bg-accent data-[state=open]:text-foreground',
                    tableStyle &&
                        'size-10 rounded-lg border border-sf-serenity-blue/80 bg-sf-serenity-blue/[0.06] text-sf-primary-soft shadow-none hover:border-sf-primary/60 hover:bg-sf-serenity-blue/20 hover:text-sf-primary-deep focus-visible:ring-sf-primary/25 data-[state=open]:border-sf-primary/60 data-[state=open]:bg-sf-serenity-blue/20 data-[state=open]:text-sf-primary-deep dark:border-sf-serenity-blue/40 dark:bg-primary/10 dark:text-sf-serenity-blue dark:hover:bg-primary/20',
                ]"
            >
                <MoreHorizontal class="size-4" />
                <span class="sr-only">Open menu for {{ product.name }}</span>
            </Button>
        </DropdownMenuTrigger>
        <DropdownMenuContent align="end" class="w-40">
            <DropdownMenuItem @select="emit('view', product)">
                <Eye />
                View
            </DropdownMenuItem>
            <DropdownMenuItem @select="emit('edit', product)">
                <Pencil />
                Edit
            </DropdownMenuItem>
            <DropdownMenuItem @select="emit('duplicate', product)">
                <Copy />
                Duplicate
            </DropdownMenuItem>
            <DropdownMenuSeparator />
            <DropdownMenuItem
                variant="destructive"
                @select="emit('remove', product)"
            >
                <Trash2 />
                Delete
            </DropdownMenuItem>
        </DropdownMenuContent>
    </DropdownMenu>
</template>
