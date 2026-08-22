<script setup lang="ts">
import { computed } from 'vue';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { formatDate, formatDelta } from '../types';
import type { InventoryItem } from '../types';

/** Read-only movement log for a single product. Newest first. */
const props = defineProps<{
    item: InventoryItem | null;
}>();

const open = defineModel<boolean>('open', { default: false });

const entries = computed(() => [...(props.item?.history ?? [])].reverse());
</script>

<template>
    <Dialog v-model:open="open">
        <DialogContent class="sm:max-w-2xl">
            <DialogHeader>
                <DialogTitle>Stock history</DialogTitle>
                <DialogDescription>
                    <template v-if="item">
                        Every recorded movement for
                        <span class="font-medium text-foreground">{{
                            item.name
                        }}</span>
                        .
                    </template>
                </DialogDescription>
            </DialogHeader>

            <div class="max-h-[24rem] overflow-y-auto rounded-lg border">
                <ul class="divide-y">
                    <li
                        v-for="entry in entries"
                        :key="entry.id"
                        class="flex items-start gap-3 px-4 py-3"
                    >
                        <span
                            :class="[
                                'mt-0.5 w-14 shrink-0 rounded-md px-1.5 py-0.5 text-center text-sm font-medium tabular-nums',
                                entry.delta > 0
                                    ? 'bg-emerald-500/10 text-emerald-700 dark:text-emerald-400'
                                    : 'bg-red-500/10 text-red-700 dark:text-red-400',
                            ]"
                        >
                            {{ formatDelta(entry.delta) }}
                        </span>
                        <div class="min-w-0 flex-1">
                            <div
                                class="flex flex-wrap items-center gap-x-2 text-sm"
                            >
                                <span class="font-medium">
                                    {{ entry.reason }}
                                </span>
                                <span class="text-muted-foreground">
                                    &middot; {{ formatDate(entry.date) }}
                                </span>
                            </div>
                            <p
                                v-if="entry.note"
                                class="mt-0.5 text-xs text-muted-foreground"
                            >
                                {{ entry.note }}
                            </p>
                        </div>
                        <div class="shrink-0 text-right">
                            <div class="text-sm font-medium tabular-nums">
                                {{ entry.resulting_stock }}
                            </div>
                            <div class="text-xs text-muted-foreground">
                                in stock
                            </div>
                        </div>
                    </li>
                    <li
                        v-if="!entries.length"
                        class="px-4 py-8 text-center text-sm text-muted-foreground"
                    >
                        No stock movements recorded yet.
                    </li>
                </ul>
            </div>
        </DialogContent>
    </Dialog>
</template>
