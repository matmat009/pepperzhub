<script setup lang="ts">
import { Plus, Trash2 } from '@lucide/vue';
import { computed, nextTick, ref } from 'vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { emptyEntry } from '../types';
import type { LabeledEntry } from '../types';

/**
 * A repeatable label/value list. Purity and Storage Instructions are the same
 * interaction, so they share one component rather than duplicating the rows.
 *
 * The list never drops below one row — removing the last one would leave the
 * subsection with nothing to type into.
 */
const entries = defineModel<LabeledEntry[]>({ required: true });

const props = withDefaults(
    defineProps<{
        addLabel: string;
        labelPlaceholder: string;
        valuePlaceholder: string;
        idPrefix: string;
        readonly?: boolean;
    }>(),
    { readonly: false },
);

/** Matches the readonly treatment used across the rest of the form. */
const inert =
    'disabled:cursor-default disabled:opacity-100 disabled:bg-muted/40 disabled:text-foreground';

const listRef = ref<HTMLElement | null>(null);

const canRemove = computed(() => !props.readonly && entries.value.length > 1);

const addEntry = async () => {
    entries.value = [...entries.value, emptyEntry()];
    await nextTick();

    // Land the caret in the row that was just added.
    const inputs = listRef.value?.querySelectorAll('input');

    inputs?.[inputs.length - 2]?.focus();
};

const removeEntry = (id: string) => {
    if (!canRemove.value) {
        return;
    }

    entries.value = entries.value.filter((entry) => entry.id !== id);
};

const update = (id: string, key: 'label' | 'value', next: string) => {
    entries.value = entries.value.map((entry) =>
        entry.id === id ? { ...entry, [key]: next } : entry,
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
            <div
                v-for="(entry, index) in entries"
                :key="entry.id"
                class="flex items-center gap-2"
            >
                <Input
                    :id="`${idPrefix}-label-${index}`"
                    :model-value="entry.label"
                    :disabled="readonly"
                    :class="['h-9 flex-1', inert]"
                    :placeholder="labelPlaceholder"
                    autocomplete="off"
                    @update:model-value="
                        (next) => update(entry.id, 'label', String(next))
                    "
                />
                <Input
                    :id="`${idPrefix}-value-${index}`"
                    :model-value="entry.value"
                    :disabled="readonly"
                    :class="['h-9 flex-1', inert]"
                    :placeholder="valuePlaceholder"
                    autocomplete="off"
                    @update:model-value="
                        (next) => update(entry.id, 'value', String(next))
                    "
                />
                <Button
                    v-if="!readonly"
                    type="button"
                    variant="ghost"
                    size="icon"
                    class="size-9 shrink-0 text-muted-foreground hover:text-destructive"
                    :disabled="!canRemove"
                    @click="removeEntry(entry.id)"
                >
                    <Trash2 class="size-4" />
                    <span class="sr-only">
                        Remove {{ entry.label || `row ${index + 1}` }}
                    </span>
                </Button>
            </div>
        </TransitionGroup>

        <Button
            v-if="!readonly"
            type="button"
            variant="outline"
            size="sm"
            class="mt-1 w-fit"
            @click="addEntry"
        >
            <Plus />
            {{ addLabel }}
        </Button>
    </div>
</template>
