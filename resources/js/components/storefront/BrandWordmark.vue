<script setup lang="ts">
/**
 * "PepperzzHub" set as live type rather than baked into the logo image, so it
 * stays selectable, scalable and readable to screen readers. The emblem beside
 * it is the real transparent PNG from the design bundle.
 */
withDefaults(
    defineProps<{
        /** Emblem height in pixels; the width follows the natural aspect. */
        emblem?: number;
        size?: 'sm' | 'md' | 'lg';
        tagline?: boolean;
    }>(),
    { emblem: 34, size: 'sm', tagline: false },
);

const sizeClass = {
    sm: 'text-[18px]',
    md: 'text-[19px]',
    lg: 'text-5xl',
} as const;
</script>

<template>
    <span class="flex items-center gap-3">
        <img
            src="/images/branding/pepperzhub-emblem.png"
            alt=""
            aria-hidden="true"
            :style="{ height: `${emblem}px` }"
            class="w-auto shrink-0"
        />
        <span class="flex min-w-0 flex-col">
            <span
                :class="sizeClass[size]"
                class="font-display font-semibold tracking-[-0.02em] whitespace-nowrap"
            >
                <span class="text-sf-rose">Pepperzz</span
                ><span class="text-sf-primary">Hub</span>
            </span>
            <span
                v-if="tagline"
                class="font-display text-[13px] font-medium tracking-[0.42em] text-sf-muted uppercase"
            >
                Peptide Solutions
            </span>
        </span>
    </span>
</template>
