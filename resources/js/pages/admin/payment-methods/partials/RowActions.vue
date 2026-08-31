<script setup lang="ts">
import { MoreHorizontal, Pencil, Trash2 } from '@lucide/vue';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import type { PaymentMethod } from '../types';

defineProps<{
    method: PaymentMethod;
}>();

const emit = defineEmits<{
    edit: [method: PaymentMethod];
    remove: [method: PaymentMethod];
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
                <span class="sr-only">Open menu for {{ method.name }}</span>
            </Button>
        </DropdownMenuTrigger>
        <DropdownMenuContent align="end" class="w-36">
            <DropdownMenuItem @select="emit('edit', method)">
                <Pencil />
                Edit
            </DropdownMenuItem>
            <DropdownMenuSeparator />
            <DropdownMenuItem
                variant="destructive"
                @select="emit('remove', method)"
            >
                <Trash2 />
                Delete
            </DropdownMenuItem>
        </DropdownMenuContent>
    </DropdownMenu>
</template>
