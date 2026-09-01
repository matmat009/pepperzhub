<script setup lang="ts">
import { Pencil } from '@lucide/vue';
import { computed } from 'vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { formatPrice } from '@/pages/admin/products/all-products/types';
import {
    availabilityKey,
    availabilityLabels,
    availabilityTone,
} from '../types';
import type { ShippingCourier } from '../types';

/**
 * Read-only view of one courier and its delivery options, opened by clicking
 * its row.
 *
 * Deliberately separate from CourierDialog rather than a view/edit mode on it:
 * the edit dialog is already working and tested, and a second mode would put
 * that at risk for no gain. Edit here just hands off — this closes, that opens
 * on the same record.
 *
 * The rows render as plain text: no inputs, no add or remove affordances.
 */
const props = defineProps<{
    courier: ShippingCourier | null;
}>();

const open = defineModel<boolean>('open', { default: false });

const emit = defineEmits<{
    edit: [courier: ShippingCourier];
}>();

// Same maps the table badge uses, so the two can never disagree.
const statusKey = computed(() =>
    availabilityKey(props.courier?.is_active ?? false),
);

const requestEdit = () => {
    if (!props.courier) {
        return;
    }

    open.value = false;
    emit('edit', props.courier);
};
</script>

<template>
    <Dialog v-model:open="open">
        <DialogContent v-if="courier" class="sm:max-w-2xl">
            <DialogHeader>
                <DialogTitle>{{ courier.name }}</DialogTitle>
                <DialogDescription>
                    How this courier appears to customers at checkout.
                </DialogDescription>
            </DialogHeader>

            <div class="grid max-h-[60vh] gap-5 overflow-y-auto px-1">
                <div class="grid grid-cols-2 gap-4">
                    <div class="grid gap-1">
                        <span class="text-sm font-medium">Sort order</span>
                        <span class="text-sm tabular-nums">
                            {{ courier.sort_order }}
                        </span>
                    </div>
                    <div class="grid gap-1">
                        <span class="text-sm font-medium">Status</span>
                        <Badge
                            variant="outline"
                            class="w-fit rounded-md font-normal"
                            :class="availabilityTone[statusKey]"
                        >
                            {{ availabilityLabels[statusKey] }}
                        </Badge>
                    </div>
                </div>

                <div class="grid gap-2">
                    <span class="text-sm font-medium">Delivery options</span>

                    <div v-if="courier.regions.length" class="grid gap-2">
                        <div
                            v-for="region in courier.regions"
                            :key="region.id"
                            class="flex flex-wrap items-baseline justify-between gap-x-4 gap-y-1 rounded-lg border p-3"
                        >
                            <div class="grid gap-0.5">
                                <span class="text-sm font-medium">
                                    {{ region.name }}
                                </span>
                                <span
                                    v-if="region.note"
                                    class="text-xs text-muted-foreground"
                                >
                                    {{ region.note }}
                                </span>
                            </div>
                            <div class="flex items-center gap-3">
                                <span class="text-sm tabular-nums">
                                    {{ formatPrice(region.rate) }}
                                </span>
                                <Badge
                                    variant="outline"
                                    class="rounded-md font-normal"
                                    :class="
                                        availabilityTone[
                                            availabilityKey(region.is_active)
                                        ]
                                    "
                                >
                                    {{
                                        availabilityLabels[
                                            availabilityKey(region.is_active)
                                        ]
                                    }}
                                </Badge>
                            </div>
                        </div>
                    </div>

                    <p v-else class="text-sm text-muted-foreground">
                        No delivery options yet. Customers cannot pick this
                        courier at checkout until it has at least one active
                        option.
                    </p>
                </div>
            </div>

            <DialogFooter>
                <Button variant="outline" @click="open = false">Close</Button>
                <Button @click="requestEdit">
                    <Pencil />
                    Edit
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
