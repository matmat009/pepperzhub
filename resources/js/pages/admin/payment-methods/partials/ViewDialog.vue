<script setup lang="ts">
import { ImageOff, Pencil } from '@lucide/vue';
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
import {
    availabilityKey,
    availabilityLabels,
    availabilityTone,
} from '../types';
import type { PaymentMethod } from '../types';

/**
 * Read-only view of one payment method, opened by clicking its row.
 *
 * Deliberately separate from PaymentMethodDialog rather than a view/edit mode
 * on it: the edit dialog is already working and tested, and a second mode would
 * put that at risk for no gain. Edit here just hands off — this closes, that
 * opens on the same record.
 */
const props = defineProps<{
    method: PaymentMethod | null;
}>();

const open = defineModel<boolean>('open', { default: false });

const emit = defineEmits<{
    edit: [method: PaymentMethod];
}>();

// Same maps the table badge uses, so the two can never disagree.
const statusKey = computed(() =>
    availabilityKey(props.method?.is_active ?? false),
);

const requestEdit = () => {
    if (!props.method) {
        return;
    }

    open.value = false;
    emit('edit', props.method);
};
</script>

<template>
    <Dialog v-model:open="open">
        <DialogContent v-if="method" class="sm:max-w-xl">
            <DialogHeader>
                <DialogTitle>{{ method.name }}</DialogTitle>
                <DialogDescription>
                    How this method appears to customers at checkout.
                </DialogDescription>
            </DialogHeader>

            <div class="grid max-h-[60vh] gap-5 overflow-y-auto px-1">
                <div class="grid gap-2">
                    <span class="text-sm font-medium">Payment details</span>
                    <dl
                        v-if="method.details.length"
                        class="grid gap-2 rounded-lg border p-3"
                    >
                        <div
                            v-for="(detail, index) in method.details"
                            :key="index"
                            class="flex flex-wrap items-baseline justify-between gap-2"
                        >
                            <dt class="text-sm text-muted-foreground">
                                {{ detail.label }}
                            </dt>
                            <dd class="text-sm font-medium">
                                {{ detail.value }}
                            </dd>
                        </div>
                    </dl>
                    <p v-else class="text-sm text-muted-foreground">
                        No details recorded.
                    </p>
                </div>

                <div class="grid gap-2">
                    <span class="text-sm font-medium">QR code</span>
                    <div
                        class="grid size-28 place-items-center overflow-hidden rounded-lg border bg-muted/30"
                    >
                        <img
                            v-if="method.qr_code_url"
                            :src="method.qr_code_url"
                            :alt="`${method.name} QR code`"
                            class="size-full object-contain"
                        />
                        <ImageOff v-else class="size-6 text-muted-foreground" />
                    </div>
                    <p
                        v-if="!method.qr_code_url"
                        class="text-sm text-muted-foreground"
                    >
                        No QR code uploaded.
                    </p>
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div class="grid gap-1">
                        <span class="text-sm font-medium">Sort order</span>
                        <span class="text-sm tabular-nums">
                            {{ method.sort_order }}
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
