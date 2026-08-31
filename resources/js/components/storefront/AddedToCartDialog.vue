<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { Check, FlaskConical, X } from '@lucide/vue';
import { computed } from 'vue';
import { Dialog, DialogContent, DialogTitle } from '@/components/ui/dialog';
import { useStorefrontCart } from '@/composables/useStorefrontCart';
import { formatPrice } from '@/pages/admin/products/all-products/types';
import { cart, checkout } from '@/routes/storefront';

const { count, justAdded, dismissAdded } = useStorefrontCart();

const open = computed({
    get: () => justAdded.value !== null,
    set: (value: boolean) => {
        if (!value) {
            dismissAdded();
        }
    },
});
</script>

<template>
    <!--
        `modal="false"` is what removes the dimmed backdrop: reka only renders
        DialogOverlay when the root is modal. It also drops the focus trap and
        scroll lock, which is right here — anchored under the cart this reads as
        a popover, not a modal. Escape and outside-click still dismiss it.
    -->
    <Dialog v-model:open="open" :modal="false">
        <!--
            The dialog portals to <body>, outside StorefrontLayout, so it would
            otherwise inherit the admin face from `font-sans` on <body> —
            `font-body` pulls it back to Lora.

            Bottom sheet under sm; from sm up it anchors under the navbar,
            right-aligned with the cart icon that raised it. The overrides beat
            DialogContent's own centring because it merges through `cn()`.

            The right offset lands the popover's right edge on the cart
            button's, at any width:

              header padding is 40px and sits OUTSIDE the max-w-[1680px] box,
              so the box stops growing at 1760px of viewport, not 1680px;
              the cart button is then a further 12px in, from the pill's pr-3.

              -> 52px + max(0, (100vw - 1760px) / 2)

            top-[88px] clears the 86px header box by a hair.
        -->
        <DialogContent
            :show-close-button="false"
            class="top-auto bottom-0 left-0 w-full max-w-full translate-x-0 translate-y-0 gap-0 rounded-t-2xl rounded-b-none border-sf-line bg-white p-6 font-body shadow-[0_-8px_40px_rgba(30,35,60,0.18)] sm:top-[88px] sm:right-[calc(max(0px,(100vw-1760px)/2)+3.25rem)] sm:bottom-auto sm:left-auto sm:w-[380px] sm:max-w-[calc(100vw-5rem)] sm:rounded-2xl sm:shadow-[0_20px_48px_rgba(30,35,60,0.22)]"
        >
            <div class="flex items-center justify-between gap-4">
                <DialogTitle
                    class="flex items-center gap-3 font-display text-lg font-semibold text-sf-ink"
                >
                    <span
                        class="grid size-7 shrink-0 place-items-center rounded-full bg-sf-success text-white"
                    >
                        <Check class="size-4 stroke-[3]" />
                    </span>
                    Added to Cart
                </DialogTitle>

                <button
                    type="button"
                    aria-label="Close"
                    class="grid size-8 shrink-0 place-items-center rounded-full border border-sf-line-strong bg-white text-sf-muted transition-colors duration-200 ease-out hover:border-sf-primary hover:text-sf-primary focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-sf-primary"
                    @click="dismissAdded"
                >
                    <X class="size-4" />
                </button>
            </div>

            <div v-if="justAdded" class="mt-6 flex items-center gap-4">
                <span
                    class="relative block size-20 shrink-0 overflow-hidden rounded-xl bg-sf-tint"
                >
                    <img
                        v-if="justAdded.imageUrl"
                        :src="justAdded.imageUrl"
                        :alt="justAdded.productName"
                        class="absolute inset-0 size-full rounded-xl object-cover"
                    />
                    <FlaskConical
                        v-else
                        class="absolute inset-0 m-auto size-7 text-sf-primary/35"
                    />
                </span>

                <span class="min-w-0 flex-1">
                    <span
                        class="block truncate font-display text-base font-semibold text-sf-ink"
                    >
                        {{ justAdded.productName }}
                    </span>
                    <span class="mt-0.5 block text-sm text-sf-subtle">
                        {{ justAdded.variantLabel }}
                    </span>
                    <span
                        class="mt-1 block font-display text-base font-semibold text-sf-primary"
                    >
                        {{ formatPrice(justAdded.unitPrice) }}
                    </span>
                </span>
            </div>

            <div class="mt-6 flex flex-col gap-3">
                <!--
                    Inertia keeps the layout mounted across a visit, so the
                    dialog has to be dismissed by hand on navigation.
                -->
                <!--
                    Rose Quartz outline. At 1px the swatch is only 1.35:1 on
                    white and all but disappears, so the border is 2px with py
                    dropped to 3 — 12px padding + 2px border matches the solid
                    button's 14px, keeping both the same height.
                -->
                <Link
                    :href="cart()"
                    class="inline-flex w-full items-center justify-center rounded-full border-2 border-sf-rose-quartz bg-white px-6 py-3 font-display text-[15px] font-semibold text-sf-rose-deep transition-colors duration-200 ease-out hover:bg-sf-rose-tint focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-sf-rose-deep"
                    @click="dismissAdded"
                >
                    View Cart ({{ count }})
                </Link>

                <Link
                    :href="checkout()"
                    class="inline-flex w-full items-center justify-center rounded-full bg-sf-primary px-6 py-3.5 font-display text-[15px] font-semibold text-white shadow-[0_6px_16px_-6px_rgba(50,70,160,0.55)] transition-colors duration-200 ease-out hover:bg-sf-primary-deep focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-sf-primary"
                    @click="dismissAdded"
                >
                    Checkout
                </Link>
            </div>
        </DialogContent>
    </Dialog>
</template>
