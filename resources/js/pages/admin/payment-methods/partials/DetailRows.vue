<script setup lang="ts">
import { Plus, Trash2 } from '@lucide/vue';
import { computed, nextTick, ref } from 'vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { emptyDetailRow } from '../types';
import type { DetailRow } from '../types';

/**
 * Repeatable label/value rows, the same interaction as the product form's
 * EntryList — a bank needs three rows and GCash needs one, so this cannot be a
 * fixed set of fields.
 *
 * The list never drops below one row: removing the last would leave nothing to
 * type into, and the server requires at least one pair anyway.
 */
const rows = defineModel<DetailRow[]>({ required: true });

defineProps<{
    errors?: Record<string, string>;
}>();

const listRef = ref<HTMLElement | null>(null);

const canRemove = computed(() => rows.value.length > 1);

const addRow = async () => {
    rows.value = [...rows.value, emptyDetailRow()];
    await nextTick();

    // Land the caret in the row that was just added.
    const inputs = listRef.value?.querySelectorAll('input');

    inputs?.[inputs.length - 2]?.focus();
};

const removeRow = (key: string) => {
    if (!canRemove.value) {
        return;
    }

    rows.value = rows.value.filter((row) => row.key !== key);
};

const update = (key: string, field: 'label' | 'value', next: string) => {
    rows.value = rows.value.map((row) =>
        row.key === key ? { ...row, [field]: next } : row,
    );
};
</script>

<template>
    <div ref="listRef" class="grid gap-2">
        <TransitionGroup
            tag="div"
            class="grid gap-2"
            enter-active-class="transition-[opacity,transform] duration-200 ease-out motion-reduce:transition-opacity"
            enter-from-class="-translate-y-1 opacity-0 motion-reduce:translate-y-0"
            leave-active-class="transition-[opacity,transform] duration-150 ease-out motion-reduce:transition-opacity"
            leave-to-class="-translate-y-1 opacity-0 motion-reduce:translate-y-0"
        >
            <div v-for="(row, index) in rows" :key="row.key" class="grid gap-1">
                <div class="flex items-center gap-2">
                    <Input
                        :id="`detail-label-${index}`"
                        :model-value="row.label"
                        class="h-9 flex-1"
                        placeholder="Account Name"
                        autocomplete="off"
                        @update:model-value="
                            (next) => update(row.key, 'label', String(next))
                        "
                    />
                    <Input
                        :id="`detail-value-${index}`"
                        :model-value="row.value"
                        class="h-9 flex-1"
                        placeholder="PepperzzHub Trading"
                        autocomplete="off"
                        @update:model-value="
                            (next) => update(row.key, 'value', String(next))
                        "
                    />
                    <Button
                        type="button"
                        variant="ghost"
                        size="icon"
                        class="size-9 shrink-0 text-muted-foreground hover:text-destructive"
                        :disabled="!canRemove"
                        @click="removeRow(row.key)"
                    >
                        <Trash2 class="size-4" />
                        <span class="sr-only">
                            Remove {{ row.label || `row ${index + 1}` }}
                        </span>
                    </Button>
                </div>
                <p
                    v-if="
                        errors?.[`details.${index}.label`] ||
                        errors?.[`details.${index}.value`]
                    "
                    class="text-sm text-destructive"
                >
                    {{
                        errors?.[`details.${index}.label`] ??
                        errors?.[`details.${index}.value`]
                    }}
                </p>
            </div>
        </TransitionGroup>

        <Button
            type="button"
            variant="outline"
            size="sm"
            class="mt-1 w-fit"
            @click="addRow"
        >
            <Plus />
            Add detail
        </Button>
    </div>
</template>
