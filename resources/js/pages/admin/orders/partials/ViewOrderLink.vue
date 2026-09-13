<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { Eye } from '@lucide/vue';
import { Button } from '@/components/ui/button';
import { show } from '@/routes/admin/orders';
import type { OrderRow } from '../types';

defineProps<{
    order: OrderRow;
}>();
</script>

<template>
    <Button
        as-child
        variant="outline"
        size="sm"
        class="border-sf-serenity-blue/55 bg-sf-serenity-blue/10 text-sf-primary-deep shadow-none hover:border-sf-serenity-blue/80 hover:bg-sf-serenity-blue/20 hover:text-sf-primary-deep dark:border-sf-serenity-blue/45 dark:bg-sf-serenity-blue/10 dark:text-sf-serenity-blue dark:hover:bg-sf-serenity-blue/20"
    >
        <Link
            :href="show(order.id)"
            :aria-label="`View order ${order.order_number}`"
            @click.stop
        >
            <Eye aria-hidden="true" class="size-4" />
            View
        </Link>
    </Button>
</template>
