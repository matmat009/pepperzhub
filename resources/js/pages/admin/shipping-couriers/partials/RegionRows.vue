<script setup lang="ts">
import { Plus, Trash2 } from '@lucide/vue';
import { nextTick, ref } from 'vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Switch } from '@/components/ui/switch';
import { emptyRegionRow } from '../types';
import type { RegionRow } from '../types';

/**
 * The courier's regions, edited inline with the courier itself — the same
 * parent-with-child-rows shape the product form uses for its formats.
 *
 * Each row keeps its database id in `id`, which is what lets the server update
 * rows in place instead of reinserting them. Removing a row here only drops it
 * from the payload; the row is deleted server-side when the courier is saved.
 */
const rows = defineModel<RegionRow[]>({ required: true });

defineProps<{
    errors?: Record<string, string>;
}>();

const listRef = ref<HTMLElement | null>(null);

const addRow = async () => {
    rows.value = [...rows.value, emptyRegionRow()];
    await nextTick();

    const inputs = listRef.value?.querySelectorAll('input[data-region-name]');

    (inputs?.[inputs.length - 1] as HTMLInputElement | undefined)?.focus();
};

const removeRow = (key: string) => {
    rows.value = rows.value.filter((row) => row.key !== key);
};

const update = (
    key: string,
    field: 'name' | 'note' | 'rate' | 'is_active',
    next: string | number | boolean,
) => {
    rows.value = rows.value.map((row) =>
        row.key === key ? { ...row, [field]: next } : row,
    );
};
</script>

<template>
    <div ref="listRef" class="grid gap-3">
        <TransitionGroup
            tag="div"
            class="grid gap-3"
            enter-active-class="transition-[opacity,transform] duration-200 ease-out motion-reduce:transition-opacity"
            enter-from-class="-translate-y-1 opacity-0 motion-reduce:translate-y-0"
            leave-active-class="transition-[opacity,transform] duration-150 ease-out motion-reduce:transition-opacity"
            leave-to-class="-translate-y-1 opacity-0 motion-reduce:translate-y-0"
        >
            <div
                v-for="(row, index) in rows"
                :key="row.key"
                class="grid gap-3 rounded-lg border p-3"
            >
                <div class="flex items-start gap-2">
                    <div class="grid flex-1 gap-1">
                        <Label
                            :for="`region-name-${index}`"
                            class="text-xs text-muted-foreground"
                        >
                            Area / Region
                        </Label>
                        <Input
                            :id="`region-name-${index}`"
                            data-region-name
                            :model-value="row.name"
                            class="h-9"
                            placeholder="Luzon & Visayas"
                            autocomplete="off"
                            @update:model-value="
                                (next) => update(row.key, 'name', String(next))
                            "
                        />
                        <p
                            v-if="errors?.[`regions.${index}.name`]"
                            class="text-sm text-destructive"
                        >
                            {{ errors[`regions.${index}.name`] }}
                        </p>
                    </div>

                    <div class="grid w-32 gap-1">
                        <Label
                            :for="`region-rate-${index}`"
                            class="text-xs text-muted-foreground"
                        >
                            Rate (₱)
                        </Label>
                        <Input
                            :id="`region-rate-${index}`"
                            :model-value="row.rate"
                            type="number"
                            min="0"
                            step="0.01"
                            inputmode="decimal"
                            class="h-9"
                            placeholder="150"
                            @update:model-value="
                                (next) => update(row.key, 'rate', next)
                            "
                        />
                        <p
                            v-if="errors?.[`regions.${index}.rate`]"
                            class="text-sm text-destructive"
                        >
                            {{ errors[`regions.${index}.rate`] }}
                        </p>
                    </div>

                    <Button
                        type="button"
                        variant="ghost"
                        size="icon"
                        class="mt-5 size-9 shrink-0 text-muted-foreground hover:text-destructive"
                        @click="removeRow(row.key)"
                    >
                        <Trash2 class="size-4" />
                        <span class="sr-only">
                            Remove {{ row.name || `region ${index + 1}` }}
                        </span>
                    </Button>
                </div>

                <div class="flex items-end gap-3">
                    <div class="grid flex-1 gap-1">
                        <Label
                            :for="`region-note-${index}`"
                            class="text-xs text-muted-foreground"
                        >
                            Note
                        </Label>
                        <Input
                            :id="`region-note-${index}`"
                            :model-value="row.note"
                            class="h-9"
                            placeholder="Standard pouch"
                            autocomplete="off"
                            @update:model-value="
                                (next) => update(row.key, 'note', String(next))
                            "
                        />
                    </div>

                    <div class="flex items-center gap-2 pb-2">
                        <Switch
                            :id="`region-active-${index}`"
                            :model-value="row.is_active"
                            @update:model-value="
                                (next) =>
                                    update(row.key, 'is_active', Boolean(next))
                            "
                        />
                        <Label
                            :for="`region-active-${index}`"
                            class="text-xs text-muted-foreground"
                        >
                            Active
                        </Label>
                    </div>
                </div>
            </div>
        </TransitionGroup>

        <p v-if="!rows.length" class="text-sm text-muted-foreground">
            No regions yet. Customers cannot pick this courier at checkout until
            it has at least one active region.
        </p>

        <Button
            type="button"
            variant="outline"
            size="sm"
            class="w-fit"
            @click="addRow"
        >
            <Plus />
            Add delivery option
        </Button>
    </div>
</template>
