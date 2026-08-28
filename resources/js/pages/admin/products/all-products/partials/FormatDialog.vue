<script setup lang="ts">
import { Plus, X } from '@lucide/vue';
import { computed, nextTick, ref, watch } from 'vue';
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
import { Switch } from '@/components/ui/switch';
import { emptyVariant, newLocalId } from '../types';
import type { ProductVariant } from '../types';

/**
 * One dialog instance owned by ProductForm, serving both add and edit:
 * `variant` null means add, a variant means edit.
 */
const props = defineProps<{
    variant: ProductVariant | null;
}>();

const open = defineModel<boolean>('open', { default: false });

const emit = defineEmits<{
    save: [variant: ProductVariant];
}>();

const draft = ref<ProductVariant>(emptyVariant());
const inclusionList = ref<HTMLElement | null>(null);

const isEdit = computed(() => props.variant !== null);

// Reseed on open so a cancelled edit never leaks into the next one.
watch(open, (isOpen) => {
    if (!isOpen) {
        return;
    }

    draft.value = props.variant
        ? (JSON.parse(JSON.stringify(props.variant)) as ProductVariant)
        : emptyVariant();
});

const addInclusion = async () => {
    draft.value.kit_inclusions.push('');
    await nextTick();

    // Land the caret in the row that was just added.
    const inputs = inclusionList.value?.querySelectorAll('input');

    inputs?.[inputs.length - 1]?.focus();
};

const removeInclusion = (index: number) => {
    draft.value.kit_inclusions.splice(index, 1);
};

const invalid = computed(() => draft.value.label.trim() === '');

const save = () => {
    if (invalid.value) {
        return;
    }

    emit('save', {
        ...draft.value,
        id: draft.value.id || newLocalId('var'),
        label: draft.value.label.trim(),
        price: Number(draft.value.price) || 0,
        stock: Number(draft.value.stock) || 0,
        kit_inclusions: draft.value.is_kit
            ? draft.value.kit_inclusions
                  .map((item) => item.trim())
                  .filter(Boolean)
            : [],
    });

    open.value = false;
};
</script>

<template>
    <Dialog v-model:open="open">
        <DialogContent class="sm:max-w-lg">
            <DialogHeader>
                <DialogTitle>
                    {{ isEdit ? 'Edit format' : 'Add format' }}
                </DialogTitle>
                <DialogDescription>
                    A purchasable size or bundle of this product, with its own
                    price and stock.
                </DialogDescription>
            </DialogHeader>

            <div class="grid gap-5">
                <div class="grid gap-2">
                    <Label for="format-label">Format Label</Label>
                    <Input
                        id="format-label"
                        v-model="draft.label"
                        placeholder="e.g. 5mg vial"
                        autocomplete="off"
                    />
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div class="grid gap-2">
                        <Label for="format-price">Price</Label>
                        <Input
                            id="format-price"
                            v-model="draft.price"
                            type="number"
                            min="0"
                            step="0.01"
                            class="tabular-nums"
                        />
                    </div>
                    <div class="grid gap-2">
                        <Label for="format-stock">Stock</Label>
                        <Input
                            id="format-stock"
                            v-model="draft.stock"
                            type="number"
                            min="0"
                            class="tabular-nums"
                        />
                    </div>
                </div>

                <div
                    class="flex items-center justify-between rounded-lg border px-3 py-2.5"
                >
                    <div class="space-y-0.5">
                        <Label for="format-is-kit" class="font-medium">
                            Is this a kit?
                        </Label>
                        <p class="text-xs text-muted-foreground">
                            Kits bundle the vial with the supplies to use it.
                        </p>
                    </div>
                    <Switch id="format-is-kit" v-model="draft.is_kit" />
                </div>

                <!--
                    Collapse via grid-template-rows rather than height: it
                    animates to intrinsic content size without measuring, and
                    this is the accordion case where a transform equivalent
                    does not exist.
                -->
                <div
                    :class="[
                        'grid transition-[grid-template-rows] duration-200 ease-out motion-reduce:transition-none',
                        draft.is_kit ? 'grid-rows-[1fr]' : 'grid-rows-[0fr]',
                    ]"
                >
                    <div class="overflow-hidden">
                        <div ref="inclusionList" class="grid gap-2 pt-0.5">
                            <Label>Kit Inclusions</Label>

                            <TransitionGroup
                                tag="div"
                                class="grid gap-2"
                                enter-active-class="transition-[opacity,transform] duration-200 ease-out motion-reduce:transition-opacity"
                                enter-from-class="-translate-y-1 opacity-0 motion-reduce:translate-y-0"
                                leave-active-class="transition-[opacity,transform] duration-150 ease-out motion-reduce:transition-opacity"
                                leave-to-class="-translate-y-1 opacity-0 motion-reduce:translate-y-0"
                            >
                                <div
                                    v-for="(
                                        item, index
                                    ) in draft.kit_inclusions"
                                    :key="index"
                                    class="flex items-center gap-2"
                                >
                                    <Input
                                        :model-value="item"
                                        placeholder="e.g. Bacteriostatic water 30ml"
                                        class="h-9"
                                        @update:model-value="
                                            (value) => {
                                                draft.kit_inclusions[index] =
                                                    String(value);
                                            }
                                        "
                                    />
                                    <Button
                                        type="button"
                                        variant="ghost"
                                        size="icon"
                                        class="size-9 shrink-0 text-muted-foreground hover:text-destructive"
                                        @click="removeInclusion(index)"
                                    >
                                        <X class="size-4" />
                                        <span class="sr-only">
                                            Remove inclusion {{ index + 1 }}
                                        </span>
                                    </Button>
                                </div>
                            </TransitionGroup>

                            <p
                                v-if="!draft.kit_inclusions.length"
                                class="text-xs text-muted-foreground"
                            >
                                Nothing listed yet.
                            </p>

                            <Button
                                type="button"
                                variant="outline"
                                size="sm"
                                class="mt-1 w-fit"
                                @click="addInclusion"
                            >
                                <Plus />
                                Add Item
                            </Button>
                        </div>
                    </div>
                </div>
            </div>

            <DialogFooter>
                <Button variant="outline" @click="open = false">Cancel</Button>
                <Button :disabled="invalid" @click="save">
                    {{ isEdit ? 'Save format' : 'Add format' }}
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
