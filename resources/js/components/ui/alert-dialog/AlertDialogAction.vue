<script setup lang="ts">
import type { AlertDialogActionProps } from "reka-ui"
import type { HTMLAttributes } from "vue"
import type { ButtonVariants } from '@/components/ui/button'
import { reactiveOmit } from "@vueuse/core"
import { AlertDialogAction } from "reka-ui"
import { cn } from "@/lib/utils"
import { buttonVariants } from '@/components/ui/button'
import { Spinner } from '@/components/ui/spinner'

const props = withDefaults(defineProps<AlertDialogActionProps & {
  class?: HTMLAttributes["class"]
  variant?: ButtonVariants['variant']
  disabled?: boolean
  loading?: boolean
}>(), {
  variant: 'default',
  loading: false,
})

const delegatedProps = reactiveOmit(props, "class", "variant", "disabled", "loading")
</script>

<template>
  <AlertDialogAction
    v-bind="delegatedProps"
    :data-loading="loading ? 'true' : undefined"
    :aria-busy="loading || undefined"
    :disabled="disabled || loading"
    :class="cn(buttonVariants({ variant }), props.class)"
  >
    <Spinner v-if="loading" />
    <slot />
  </AlertDialogAction>
</template>
