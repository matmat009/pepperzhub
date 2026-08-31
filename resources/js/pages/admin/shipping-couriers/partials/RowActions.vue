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
import type { ShippingCourier } from '../types';

defineProps<{
    courier: ShippingCourier;
}>();

const emit = defineEmits<{
    edit: [courier: ShippingCourier];
    remove: [courier: ShippingCourier];
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
                <span class="sr-only">Open menu for {{ courier.name }}</span>
            </Button>
        </DropdownMenuTrigger>
        <DropdownMenuContent align="end" class="w-36">
            <DropdownMenuItem @select="emit('edit', courier)">
                <Pencil />
                Edit
            </DropdownMenuItem>
            <DropdownMenuSeparator />
            <DropdownMenuItem
                variant="destructive"
                @select="emit('remove', courier)"
            >
                <Trash2 />
                Delete
            </DropdownMenuItem>
        </DropdownMenuContent>
    </DropdownMenu>
</template>
