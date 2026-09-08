<script setup lang="ts">
import { Check, ImageIcon, Plus, RefreshCw, Trash2, X } from '@lucide/vue';
import { computed, ref } from 'vue';
import { Button } from '@/components/ui/button';
import { newLocalId } from '../types';
import type { ProductImage } from '../types';

/**
 * Main dropzone plus a thumbnail strip, shared by Create.vue and Show.vue.
 *
 * Picked files preview immediately as object URLs and keep a handle on the
 * File itself, which is what the form submits for upload.
 */
const images = defineModel<ProductImage[]>({ required: true });

const props = withDefaults(
    defineProps<{
        readonly?: boolean;
        blueOutline?: boolean;
    }>(),
    { readonly: false, blueOutline: false },
);

const activeIndex = ref(0);
const dragging = ref(false);

const active = computed<ProductImage | undefined>(
    () => images.value[activeIndex.value] ?? images.value[0],
);

const primaryImage = computed<ProductImage | undefined>(() => images.value[0]);
const additionalImages = computed(() => images.value.slice(1));
const canAddImage = computed(
    () => !props.blueOutline || images.value.length < 10,
);
const additionalPlaceholderCount = computed(() => {
    const available = Math.max(0, 9 - additionalImages.value.length);

    return Math.min(
        available,
        additionalImages.value.length < 4
            ? 4 - additionalImages.value.length
            : 1,
    );
});

const browseInput = ref<HTMLInputElement | null>(null);
const replaceInput = ref<HTMLInputElement | null>(null);

const toImage = (file: File): ProductImage => ({
    id: newLocalId('img'),
    // Previewed locally; the File rides along on submit and the server
    // replaces this with a stored path.
    url: URL.createObjectURL(file),
    file,
});

const addFiles = (files: FileList | null) => {
    if (props.readonly || !files?.length) {
        return;
    }

    const available = props.blueOutline
        ? Math.max(0, 10 - images.value.length)
        : files.length;
    const added = Array.from(files).slice(0, available).map(toImage);

    if (!added.length) {
        return;
    }

    images.value = [...images.value, ...added];
    activeIndex.value = images.value.length - added.length;
};

const replaceActive = (files: FileList | null) => {
    if (props.readonly || !files?.length || !images.value.length) {
        return;
    }

    const next = [...images.value];

    next[props.blueOutline ? 0 : activeIndex.value] = toImage(files[0]);
    images.value = next;
};

const removeAt = (index: number) => {
    if (props.readonly) {
        return;
    }

    images.value = images.value.filter((_, i) => i !== index);
    activeIndex.value = Math.max(
        0,
        Math.min(activeIndex.value, images.value.length - 1),
    );
};

const onDrop = (event: DragEvent) => {
    dragging.value = false;
    addFiles(event.dataTransfer?.files ?? null);
};
</script>

<template>
    <div v-if="blueOutline" class="space-y-5">
        <div class="space-y-2.5">
            <p class="text-sm font-medium">Product Image</p>

            <div
                :class="[
                    'relative flex aspect-square items-center justify-center overflow-hidden rounded-xl border bg-background/75 transition-colors duration-200 ease-out',
                    primaryImage
                        ? readonly
                            ? 'border-border'
                            : 'border-primary/70'
                        : 'border-dashed border-primary/30 hover:border-primary/55',
                    dragging && !readonly && 'border-primary bg-primary/5',
                ]"
                @dragover.prevent="!readonly && (dragging = true)"
                @dragleave.prevent="dragging = false"
                @drop.prevent="onDrop"
            >
                <img
                    v-if="primaryImage"
                    :src="primaryImage.url"
                    alt="Primary product image"
                    class="size-full bg-background/70 object-contain"
                />
                <div
                    v-else
                    class="flex flex-col items-center gap-3 px-6 text-center"
                >
                    <ImageIcon class="size-7 text-muted-foreground" />
                    <p class="text-sm text-muted-foreground">
                        {{
                            readonly
                                ? 'No image for this product'
                                : 'Drop an image here, or browse'
                        }}
                    </p>
                    <Button
                        v-if="!readonly"
                        type="button"
                        variant="outline"
                        size="sm"
                        @click="browseInput?.click()"
                    >
                        Browse
                    </Button>
                </div>

                <span
                    v-if="primaryImage"
                    class="absolute top-2 right-2 grid size-5 place-items-center rounded-full bg-primary text-primary-foreground shadow-sm"
                    aria-label="Primary image selected"
                >
                    <Check class="size-3.5" />
                </span>
            </div>
        </div>

        <div
            v-if="!readonly && primaryImage"
            class="flex flex-wrap items-center gap-2"
        >
            <Button
                type="button"
                variant="outline"
                size="sm"
                @click="replaceInput?.click()"
            >
                <RefreshCw />
                Replace
            </Button>
            <Button
                type="button"
                variant="destructive"
                size="sm"
                @click="removeAt(0)"
            >
                <Trash2 />
                Remove
            </Button>
        </div>

        <div
            v-if="!readonly"
            class="space-y-1 text-xs leading-relaxed text-muted-foreground"
        >
            <p>Recommended: 1200 × 1200 px</p>
            <p>Formats: JPG, PNG, WebP, or SVG</p>
            <p>Max size: 5 MB</p>
        </div>

        <div class="space-y-3 border-t border-primary/10 pt-4">
            <div class="flex items-baseline gap-2">
                <p class="text-sm font-medium">Additional Images</p>
                <span class="text-xs text-muted-foreground">
                    ({{ additionalImages.length }}/9)
                </span>
            </div>

            <TransitionGroup
                tag="div"
                class="grid grid-cols-2 gap-3"
                enter-active-class="transition-[opacity,transform] duration-200 ease-out motion-reduce:transition-opacity"
                enter-from-class="scale-95 opacity-0 motion-reduce:scale-100"
                leave-active-class="absolute transition-[opacity,transform] duration-150 ease-out motion-reduce:transition-opacity"
                leave-to-class="scale-95 opacity-0 motion-reduce:scale-100"
            >
                <div
                    v-for="(image, index) in additionalImages"
                    :key="image.id"
                    class="group relative aspect-square overflow-hidden rounded-lg border bg-background"
                >
                    <img
                        :src="image.url"
                        :alt="`Additional product image ${index + 1}`"
                        class="size-full object-contain"
                    />
                    <button
                        v-if="!readonly"
                        type="button"
                        class="absolute top-1.5 right-1.5 grid size-7 place-items-center rounded-full border border-destructive/50 bg-background/95 text-destructive shadow-xs transition-[color,background-color,border-color,box-shadow,transform] duration-150 ease-out outline-none hover:border-destructive hover:bg-destructive/10 focus-visible:ring-3 focus-visible:ring-destructive/25 focus-visible:ring-offset-2 focus-visible:ring-offset-background active:scale-95 active:bg-destructive/15 motion-reduce:active:scale-100"
                        @click="removeAt(index + 1)"
                    >
                        <X class="size-3" />
                        <span class="sr-only">
                            Remove additional image {{ index + 1 }}
                        </span>
                    </button>
                </div>

                <button
                    v-for="slot in !readonly && canAddImage
                        ? additionalPlaceholderCount
                        : 0"
                    :key="`add-tile-${slot}`"
                    type="button"
                    class="flex aspect-square flex-col items-center justify-center gap-1.5 rounded-lg border border-dashed border-muted-foreground/40 bg-background/70 text-muted-foreground shadow-xs transition-[color,background-color,border-color,box-shadow,transform] duration-150 ease-out outline-none hover:border-primary/60 hover:bg-primary/10 hover:text-primary focus-visible:ring-3 focus-visible:ring-ring/30 focus-visible:ring-offset-2 focus-visible:ring-offset-background active:scale-[0.98] active:shadow-none motion-reduce:active:scale-100"
                    :aria-label="`Add additional image ${additionalImages.length + slot}`"
                    @click="browseInput?.click()"
                >
                    <Plus class="size-5" />
                    <span class="text-xs font-medium">Add Image</span>
                </button>
            </TransitionGroup>

            <p
                v-if="!readonly"
                class="text-xs leading-relaxed text-muted-foreground"
            >
                You can add up to 9 additional images.
            </p>
        </div>
    </div>

    <div v-else class="space-y-4">
        <div class="space-y-2">
            <p class="text-sm font-medium">Product Image</p>

            <div
                :class="[
                    'relative flex aspect-square items-center justify-center overflow-hidden rounded-xl border border-dashed bg-muted/30 transition-colors duration-200 ease-out',
                    dragging && !readonly
                        ? 'border-primary bg-primary/5'
                        : 'border-border',
                ]"
                @dragover.prevent="!readonly && (dragging = true)"
                @dragleave.prevent="dragging = false"
                @drop.prevent="onDrop"
            >
                <img
                    v-if="active"
                    :src="active.url"
                    :alt="`Product image ${activeIndex + 1}`"
                    class="size-full object-cover"
                />
                <div
                    v-else
                    class="flex flex-col items-center gap-2 px-6 text-center"
                >
                    <ImageIcon class="size-6 text-muted-foreground" />
                    <p class="text-sm text-muted-foreground">
                        {{
                            readonly
                                ? 'No image for this product'
                                : 'Drop an image here, or browse'
                        }}
                    </p>
                </div>

                <div
                    v-if="!readonly"
                    class="absolute inset-x-3 bottom-3 flex items-center justify-between gap-2"
                >
                    <Button
                        type="button"
                        variant="secondary"
                        size="sm"
                        class="backdrop-blur-sm"
                        @click="browseInput?.click()"
                    >
                        <ImageIcon />
                        Browse
                    </Button>
                    <Button
                        v-if="active"
                        type="button"
                        variant="secondary"
                        size="sm"
                        class="backdrop-blur-sm"
                        @click="replaceInput?.click()"
                    >
                        <RefreshCw />
                        Replace
                    </Button>
                </div>
            </div>
        </div>

        <TransitionGroup
            tag="div"
            class="grid grid-cols-3 gap-3"
            enter-active-class="transition-[opacity,transform] duration-200 ease-out motion-reduce:transition-opacity"
            enter-from-class="scale-95 opacity-0 motion-reduce:scale-100"
            leave-active-class="absolute transition-[opacity,transform] duration-150 ease-out motion-reduce:transition-opacity"
            leave-to-class="scale-95 opacity-0 motion-reduce:scale-100"
        >
            <button
                v-for="(image, index) in images"
                :key="image.id"
                type="button"
                :class="[
                    'group relative aspect-square overflow-hidden rounded-lg border transition-[border-color,box-shadow,transform] duration-150 ease-out outline-none focus-visible:ring-3 focus-visible:ring-ring/30 focus-visible:ring-offset-2 focus-visible:ring-offset-background active:scale-[0.98] motion-reduce:active:scale-100',
                    index === activeIndex
                        ? 'ring-2 ring-primary/60 ring-offset-2 ring-offset-background'
                        : 'hover:shadow-md hover:shadow-black/5',
                ]"
                @click="activeIndex = index"
            >
                <img
                    :src="image.url"
                    :alt="`Thumbnail ${index + 1}`"
                    class="size-full object-cover"
                />
                <span
                    v-if="!readonly"
                    class="absolute top-1 right-1 grid size-5 place-items-center rounded-full bg-background/90 text-muted-foreground opacity-0 shadow-sm transition-opacity duration-150 ease-out group-hover:opacity-100 hover:text-foreground"
                    @click.stop="removeAt(index)"
                >
                    <X class="size-3" />
                    <span class="sr-only">Remove image {{ index + 1 }}</span>
                </span>
            </button>

            <button
                v-if="!readonly"
                key="add-tile"
                type="button"
                class="flex aspect-square flex-col items-center justify-center gap-1.5 rounded-lg border border-dashed border-muted-foreground/40 bg-background text-muted-foreground shadow-xs transition-[color,background-color,border-color,box-shadow,transform] duration-150 ease-out outline-none hover:border-primary/60 hover:bg-accent hover:text-foreground focus-visible:ring-3 focus-visible:ring-ring/30 focus-visible:ring-offset-2 focus-visible:ring-offset-background active:scale-[0.98] active:shadow-none motion-reduce:active:scale-100"
                @click="browseInput?.click()"
            >
                <Plus class="size-4" />
                <span class="text-xs font-medium">Add Image</span>
            </button>
        </TransitionGroup>
    </div>

    <input
        ref="browseInput"
        type="file"
        accept=".jpg,.jpeg,.png,.webp,.svg,image/jpeg,image/png,image/webp,image/svg+xml"
        multiple
        class="sr-only"
        @change="addFiles(($event.target as HTMLInputElement).files)"
    />
    <input
        ref="replaceInput"
        type="file"
        accept=".jpg,.jpeg,.png,.webp,.svg,image/jpeg,image/png,image/webp,image/svg+xml"
        class="sr-only"
        @change="replaceActive(($event.target as HTMLInputElement).files)"
    />
</template>
