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

const blueOutlineButton =
    'border-primary/70 text-primary shadow-xs hover:border-primary hover:bg-primary/10 hover:text-primary dark:border-primary/75 dark:text-primary dark:hover:bg-primary/15 dark:hover:text-primary';

const active = computed<ProductImage | undefined>(
    () => images.value[activeIndex.value] ?? images.value[0],
);

const primaryImage = computed<ProductImage | undefined>(() => images.value[0]);
const additionalImages = computed(() => images.value.slice(1));
const canAddImage = computed(
    () => !props.blueOutline || images.value.length < 10,
);

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
    <div v-if="blueOutline" class="space-y-4">
        <div
            :class="[
                'relative flex aspect-square items-center justify-center overflow-hidden rounded-lg border bg-background transition-colors duration-200 ease-out',
                primaryImage
                    ? 'border-2 border-primary'
                    : 'border-dashed border-border',
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
                class="size-full bg-muted/10 object-contain"
            />
            <div
                v-else
                class="flex flex-col items-center gap-2 px-6 text-center"
            >
                <ImageIcon class="size-6 text-muted-foreground" />
                <p v-if="readonly" class="text-sm text-muted-foreground">
                    No image for this product
                </p>
                <p v-else class="text-sm text-muted-foreground">
                    Drop an image here, or
                    <button
                        type="button"
                        class="font-medium text-primary hover:underline"
                        @click="browseInput?.click()"
                    >
                        browse
                    </button>
                </p>
            </div>

            <span
                v-if="primaryImage"
                class="absolute top-2 right-2 grid size-5 place-items-center rounded-full bg-primary text-primary-foreground shadow-sm"
                aria-label="Primary image selected"
            >
                <Check class="size-3.5" />
            </span>
        </div>

        <div v-if="!readonly" class="flex flex-wrap items-center gap-2">
            <Button
                v-if="primaryImage"
                type="button"
                variant="outline"
                size="sm"
                :class="blueOutlineButton"
                @click="replaceInput?.click()"
            >
                <RefreshCw />
                Replace
            </Button>
            <Button
                v-else
                type="button"
                variant="outline"
                size="sm"
                :class="blueOutlineButton"
                @click="browseInput?.click()"
            >
                <ImageIcon />
                Browse
            </Button>
            <Button
                v-if="primaryImage"
                type="button"
                variant="outline"
                size="sm"
                class="border-destructive/40 text-destructive shadow-xs hover:border-destructive hover:bg-destructive/10 hover:text-destructive"
                @click="removeAt(0)"
            >
                <Trash2 />
                Remove
            </Button>
        </div>

        <p class="text-xs text-muted-foreground">
            Recommended: 1000 × 1000px, JPG, PNG, WEBP, or SVG (max 5MB).
        </p>

        <div class="space-y-3 border-t pt-4">
            <div class="flex items-baseline gap-2">
                <p class="text-sm font-medium">Additional Images</p>
                <span class="text-xs text-muted-foreground">
                    ({{ additionalImages.length }}/9)
                </span>
            </div>

            <TransitionGroup
                tag="div"
                class="grid grid-cols-3 gap-3"
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
                        class="absolute top-1.5 right-1.5 grid size-5 place-items-center rounded-full border bg-background/95 text-muted-foreground shadow-xs transition-colors hover:border-destructive/40 hover:text-destructive"
                        @click="removeAt(index + 1)"
                    >
                        <X class="size-3" />
                        <span class="sr-only">
                            Remove additional image {{ index + 1 }}
                        </span>
                    </button>
                </div>

                <button
                    v-if="!readonly && canAddImage"
                    key="add-tile"
                    type="button"
                    class="flex aspect-square flex-col items-center justify-center gap-1.5 rounded-lg border border-dashed border-primary/55 text-primary transition-colors duration-200 ease-out hover:border-primary hover:bg-primary/10 hover:text-primary"
                    @click="browseInput?.click()"
                >
                    <Plus class="size-5" />
                    <span class="text-xs font-medium">Add Image</span>
                </button>
            </TransitionGroup>
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
                        class="shadow-sm backdrop-blur-sm"
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
                        class="shadow-sm backdrop-blur-sm"
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
                    'group relative aspect-square overflow-hidden rounded-lg border transition-shadow duration-200 ease-out',
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
                class="flex aspect-square flex-col items-center justify-center gap-1.5 rounded-lg border border-dashed text-muted-foreground transition-colors duration-200 ease-out hover:border-ring hover:bg-accent/40 hover:text-foreground"
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
        accept="image/*"
        multiple
        class="sr-only"
        @change="addFiles(($event.target as HTMLInputElement).files)"
    />
    <input
        ref="replaceInput"
        type="file"
        accept="image/*"
        class="sr-only"
        @change="replaceActive(($event.target as HTMLInputElement).files)"
    />
</template>
