<script setup lang="ts">
import { onMounted, ref, useTemplateRef } from 'vue';

/**
 * An `<img>` that fades in once it has actually decoded.
 *
 * Everything is forwarded — `src`, `alt`, sizing classes, `loading="lazy"`,
 * a parent's own `@error` handler — so this is a drop-in for the bare tag and
 * the lazy-loading behaviour of every call site survives unchanged. The only
 * addition is `data-loaded`, which the `img.sf-img` rule in app.css keys the
 * opacity transition off.
 *
 * A cached image is already complete before `load` can fire, so it is checked
 * on mount; a broken one is marked loaded too, so a failed src shows the
 * browser's own fallback rather than nothing at all.
 */
defineOptions({ inheritAttrs: false });

const loaded = ref(false);
const image = useTemplateRef<HTMLImageElement>('image');

const markLoaded = () => {
    loaded.value = true;
};

onMounted(() => {
    if (image.value?.complete) {
        markLoaded();
    }
});
</script>

<template>
    <img
        ref="image"
        v-bind="$attrs"
        class="sf-img"
        :data-loaded="loaded ? '' : undefined"
        @load="markLoaded"
        @error="markLoaded"
    />
</template>
