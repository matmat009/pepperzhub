<script setup lang="ts">
import { Button } from '@/components/ui/button';

defineProps<{
    label: string;
    disabled?: boolean;
}>();
</script>

<template>
    <Button
        variant="ghost"
        size="icon-sm"
        class="size-10 rounded-lg border border-sf-serenity-blue/80 bg-sf-serenity-blue/[0.06] text-sf-primary-soft shadow-none hover:border-sf-primary/60 hover:bg-sf-serenity-blue/20 hover:text-sf-primary-deep focus-visible:ring-sf-primary/25 disabled:border-muted-foreground/25 disabled:bg-muted/30 disabled:text-muted-foreground data-[state=open]:border-sf-primary/60 data-[state=open]:bg-sf-serenity-blue/20 data-[state=open]:text-sf-primary-deep dark:border-sf-serenity-blue/40 dark:bg-primary/10 dark:text-sf-serenity-blue dark:hover:bg-primary/20"
        :disabled="disabled"
        @click.stop
    >
        <slot />
        <span class="sr-only">{{ label }}</span>
    </Button>
</template>
