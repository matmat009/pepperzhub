<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { Minus, Plus } from '@lucide/vue';
import { computed, ref, watch } from 'vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Spinner } from '@/components/ui/spinner';
import { Textarea } from '@/components/ui/textarea';
import { adjust } from '@/routes/admin/products/inventory';
import { STOCK_REASONS } from '../types';
import type { InventoryItem, StockReason } from '../types';

const props = defineProps<{
    item: InventoryItem | null;
}>();

const open = defineModel<boolean>('open', { default: false });

const emit = defineEmits<{
    adjusted: [
        payload: {
            item: InventoryItem;
            delta: number;
            reason: StockReason;
            note: string;
        },
    ];
}>();

const direction = ref<1 | -1>(1);
const quantity = ref<number | string>(1);
const reason = ref<StockReason>('Restock');
const note = ref('');
const processing = ref(false);

watch(open, (isOpen) => {
    if (!isOpen) {
        return;
    }

    direction.value = 1;
    quantity.value = 1;
    reason.value = 'Restock';
    note.value = '';
});

const delta = computed(
    () => direction.value * Math.abs(Number(quantity.value) || 0),
);

const resulting = computed(() =>
    Math.max(0, (props.item?.stock ?? 0) + delta.value),
);

const invalid = computed(() => delta.value === 0);

const submit = () => {
    if (!props.item || invalid.value) {
        return;
    }

    const item = props.item;

    processing.value = true;

    router.post(
        adjust(item.id).url,
        { delta: delta.value, reason: reason.value, note: note.value },
        {
            preserveScroll: true,
            preserveState: true,
            onSuccess: () => {
                // The endpoint is a stub, so the page applies the movement to
                // its own copy of the row.
                emit('adjusted', {
                    item,
                    delta: delta.value,
                    reason: reason.value,
                    note: note.value,
                });
                open.value = false;
            },
            onFinish: () => {
                processing.value = false;
            },
        },
    );
};
</script>

<template>
    <Dialog v-model:open="open">
        <DialogContent class="sm:max-w-lg">
            <DialogHeader>
                <DialogTitle>Adjust stock</DialogTitle>
                <DialogDescription>
                    <template v-if="item">
                        <span class="font-medium text-foreground">{{
                            item.name
                        }}</span>
                        currently holds {{ item.stock }} units.
                    </template>
                </DialogDescription>
            </DialogHeader>

            <div class="grid gap-5">
                <div class="grid gap-2">
                    <Label for="stock-quantity">Quantity change</Label>
                    <div class="flex items-center gap-2">
                        <div class="flex rounded-md border p-0.5">
                            <Button
                                type="button"
                                :variant="
                                    direction === 1 ? 'secondary' : 'ghost'
                                "
                                size="icon"
                                class="size-8"
                                @click="direction = 1"
                            >
                                <Plus class="size-4" />
                                <span class="sr-only">Add stock</span>
                            </Button>
                            <Button
                                type="button"
                                :variant="
                                    direction === -1 ? 'secondary' : 'ghost'
                                "
                                size="icon"
                                class="size-8"
                                @click="direction = -1"
                            >
                                <Minus class="size-4" />
                                <span class="sr-only">Remove stock</span>
                            </Button>
                        </div>
                        <Input
                            id="stock-quantity"
                            v-model="quantity"
                            type="number"
                            min="1"
                            class="h-9 flex-1 tabular-nums"
                        />
                    </div>
                    <p class="text-xs text-muted-foreground">
                        {{ item?.stock ?? 0 }} &rarr;
                        <span class="font-medium text-foreground tabular-nums">
                            {{ resulting }}
                        </span>
                    </p>
                </div>

                <div class="grid gap-2">
                    <Label for="stock-reason">Reason</Label>
                    <Select v-model="reason">
                        <SelectTrigger id="stock-reason" class="w-full">
                            <SelectValue placeholder="Select a reason" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem
                                v-for="option in STOCK_REASONS"
                                :key="option"
                                :value="option"
                            >
                                {{ option }}
                            </SelectItem>
                        </SelectContent>
                    </Select>
                </div>

                <div class="grid gap-2">
                    <Label for="stock-note">
                        Note
                        <span class="font-normal text-muted-foreground">
                            (optional)
                        </span>
                    </Label>
                    <Textarea
                        id="stock-note"
                        v-model="note"
                        rows="2"
                        placeholder="Lot number, supplier, context..."
                    />
                </div>
            </div>

            <DialogFooter>
                <Button
                    variant="outline"
                    :disabled="processing"
                    @click="open = false"
                >
                    Cancel
                </Button>
                <Button :disabled="processing || invalid" @click="submit">
                    <Spinner v-if="processing" />
                    Apply adjustment
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
