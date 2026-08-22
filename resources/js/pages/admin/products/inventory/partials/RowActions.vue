<script setup lang="ts">
import { History, MoreHorizontal, PackagePlus } from '@lucide/vue';
import { Button } from '@/components/ui/button';
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
            <Button
                variant="ghost"
                size="icon"
                class="size-8 text-muted-foreground data-[state=open]:bg-accent data-[state=open]:text-foreground"
            >
                <MoreHorizontal class="size-4" />
                <span class="sr-only">Open menu for {{ item.name }}</span>
            </Button>
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
