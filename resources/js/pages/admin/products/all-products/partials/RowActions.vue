<script setup lang="ts">
import { Eye, MoreHorizontal, Pencil, Trash2 } from '@lucide/vue';
import TableActionMenuButton from '@/components/TableActionMenuButton.vue';
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
    remove: [product: Product];
}>();
</script>

<template>
    <DropdownMenu>
        <DropdownMenuTrigger as-child>
            <TableActionMenuButton :label="`Open menu for ${product.name}`">
                <MoreHorizontal class="size-4" />
            </TableActionMenuButton>
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
