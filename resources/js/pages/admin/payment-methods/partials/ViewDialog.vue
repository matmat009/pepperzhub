<script setup lang="ts">
import { Pencil, QrCode } from '@lucide/vue';
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
        <DialogContent
            v-if="method"
            class="max-h-[calc(100vh-2rem)] grid-rows-[auto_minmax(0,1fr)_auto] gap-0 overflow-hidden p-0 sm:max-w-3xl [&_[data-slot=dialog-close]]:top-6 [&_[data-slot=dialog-close]]:right-6"
        >
            <DialogHeader
                class="border-b border-sf-serenity-blue/20 bg-linear-to-r from-sf-serenity-blue/20 via-background to-sf-rose-quartz/25 px-6 py-6 pr-14 sm:px-8 sm:pr-16"
            >
                <DialogTitle class="text-2xl leading-tight">
                    {{ method.name }}
                </DialogTitle>
                <DialogDescription>
                    How this method appears to customers at checkout.
                </DialogDescription>
            </DialogHeader>

            <div
                class="grid min-h-0 gap-5 overflow-y-auto px-6 py-6 sm:px-8 lg:grid-cols-[minmax(0,1.25fr)_minmax(16rem,0.85fr)] lg:gap-x-8"
            >
                <div class="grid content-start gap-5">
                    <div class="grid gap-2">
                        <span class="text-sm font-medium">
                            Payment details
                        </span>
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

                    <div class="grid grid-cols-2 gap-4 border-t pt-4">
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

                <div
                    class="grid content-start gap-2 lg:border-l lg:border-sf-serenity-blue/20 lg:pl-8"
                >
                    <span class="text-sm font-medium">QR code</span>
                    <div
                        class="grid min-h-56 place-items-center rounded-xl border border-sf-serenity-blue/25 bg-linear-to-br from-sf-serenity-blue/15 via-background to-sf-rose-quartz/25 p-4"
                    >
                        <div
                            v-if="method.qr_code_url"
                            class="grid w-fit max-w-full place-items-center rounded-lg border border-sf-serenity-blue/20 bg-white p-3 shadow-sm"
                        >
                            <img
                                :src="method.qr_code_url"
                                :alt="`${method.name} QR code`"
                                class="block h-auto max-h-96 w-auto max-w-full object-contain"
                            />
                        </div>
                        <div
                            v-else
                            class="grid place-items-center gap-3 text-center"
                        >
                            <QrCode
                                class="size-16 text-sf-primary-soft/70"
                                aria-hidden="true"
                            />
                            <p class="text-sm text-muted-foreground">
                                No QR code uploaded.
                            </p>
                        </div>
                    </div>
                </div>
            </div>

            <DialogFooter class="border-t px-6 py-4 sm:px-8">
                <Button variant="outline" @click="open = false">Close</Button>
                <Button @click="requestEdit">
                    <Pencil />
                    Edit
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
