<script setup lang="ts">
import { ImageOff, Upload, X } from '@lucide/vue';
import { computed, onBeforeUnmount, ref, watch } from 'vue';
import { Button } from '@/components/ui/button';

/**
 * Single-image picker for a payment method's QR code.
 *
 * Three states the form has to distinguish, which is why removal is its own
 * flag rather than "no file selected":
 *   - untouched: no new file, not removing — the server leaves the stored path
 *   - replaced:  a new file — the server deletes the old one and stores this
 *   - removed:   remove flag set — the server deletes the old one and nulls it
 */
const file = defineModel<File | null>('file', { required: true });
const removed = defineModel<boolean>('removed', { required: true });

const props = defineProps<{
    /** The stored QR code, if this method already has one. */
    existingUrl: string | null;
}>();

const input = ref<HTMLInputElement | null>(null);
const previewUrl = ref<string | null>(null);

// Object URLs are revoked on replace and unmount; leaving them allocated leaks
// the blob for the life of the page.
const releasePreview = () => {
    if (previewUrl.value) {
        URL.revokeObjectURL(previewUrl.value);
        previewUrl.value = null;
    }
};

watch(file, (next) => {
    releasePreview();

    if (next) {
        previewUrl.value = URL.createObjectURL(next);
    }
});

onBeforeUnmount(releasePreview);

/** What to show: a freshly picked file wins, then the stored one. */
const shownUrl = computed(() => {
    if (previewUrl.value) {
        return previewUrl.value;
    }

    return removed.value ? null : props.existingUrl;
});

const pick = (event: Event) => {
    const chosen = (event.target as HTMLInputElement).files?.[0] ?? null;

    if (!chosen) {
        return;
    }

    file.value = chosen;
    // Choosing a replacement supersedes a pending removal.
    removed.value = false;
};

const clear = () => {
    file.value = null;
    releasePreview();

    // Only flag a removal if there is something stored to remove; otherwise
    // this is just cancelling an unsaved pick.
    removed.value = Boolean(props.existingUrl);

    if (input.value) {
        input.value.value = '';
    }
};
</script>

<template>
    <div class="flex items-start gap-4">
        <div
            class="grid size-28 shrink-0 place-items-center overflow-hidden rounded-lg border bg-muted/30"
        >
            <img
                v-if="shownUrl"
                :src="shownUrl"
                alt="Payment QR code"
                class="size-full object-contain"
            />
            <ImageOff v-else class="size-6 text-muted-foreground" />
        </div>

        <div class="grid gap-2">
            <input
                ref="input"
                type="file"
                accept="image/jpeg,image/png,image/webp"
                class="hidden"
                @change="pick"
            />
            <Button
                type="button"
                variant="outline"
                size="sm"
                class="w-fit"
                @click="input?.click()"
            >
                <Upload />
                {{ shownUrl ? 'Replace QR code' : 'Upload QR code' }}
            </Button>
            <Button
                v-if="shownUrl"
                type="button"
                variant="ghost"
                size="sm"
                class="w-fit text-muted-foreground hover:text-destructive"
                @click="clear"
            >
                <X />
                Remove
            </Button>
            <p class="text-xs text-muted-foreground">
                JPG, PNG or WebP, up to 5MB. Shown to customers at checkout.
            </p>
        </div>
    </div>
</template>
