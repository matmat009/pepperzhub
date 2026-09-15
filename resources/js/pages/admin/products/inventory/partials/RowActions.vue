<script setup lang="ts">
import { History, MoreHorizontal, PackagePlus } from '@lucide/vue';
import TableActionMenuButton from '@/components/TableActionMenuButton.vue';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import type { InventoryItem } from '../types';

defineProps<{
    item: InventoryItem;
}>();

const emit = defineEmits<{
    adjust: [item: InventoryItem];
    history: [item: InventoryItem];
}>();
</script>

<template>
    <DropdownMenu>
        <DropdownMenuTrigger as-child>
            <TableActionMenuButton
                :label="`Open menu for ${item.product_name} ${item.variant_label}`"
            >
                <MoreHorizontal class="size-4" />
            </TableActionMenuButton>
        </DropdownMenuTrigger>
        <DropdownMenuContent align="end" class="w-44">
            <DropdownMenuItem @select="emit('adjust', item)">
                <PackagePlus />
                Adjust Stock
            </DropdownMenuItem>
            <DropdownMenuItem @select="emit('history', item)">
                <History />
                View History
            </DropdownMenuItem>
        </DropdownMenuContent>
    </DropdownMenu>
</template>
