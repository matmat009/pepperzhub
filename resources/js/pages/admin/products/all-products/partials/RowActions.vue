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

defineProps<{
    product: Product;
}>();

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
                size="icon"
                class="size-8 text-muted-foreground data-[state=open]:bg-accent data-[state=open]:text-foreground"
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
