<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { watch } from 'vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';

/**
 * The confirm step for reject / cancel / ship.
 *
 * Those three take a note or a tracking number, so they must not fire straight
 * off a click. `fields` decides which inputs appear, keeping one dialog rather
 * than three near-identical ones.
 */
const props = defineProps<{
    open: boolean;
    title: string;
    description: string;
    confirmLabel: string;
    /** POST target from a Wayfinder helper. */
    action: string;
    /** 'reason' for reject/cancel, 'shipping' for the ship dialog. */
    fields: 'reason' | 'shipping';
    destructive?: boolean;
    /** Prefills shipped_via from the checkout-time courier snapshot. */
    courierName?: string;
}>();

const emit = defineEmits<{ 'update:open': [value: boolean] }>();

const form = useForm({
    reason: '',
    tracking_number: '',
    shipped_via: '',
});

// Prefill on open rather than at setup: the snapshot is only meaningful once
// the admin is actually looking at this order.
watch(
    () => props.open,
    (open) => {
        if (open) {
            form.reset();
            form.clearErrors();
            form.shipped_via = props.courierName ?? '';
        }
    },
);

const submit = () => {
    form.post(props.action, {
        preserveScroll: true,
        onSuccess: () => emit('update:open', false),
    });
};
</script>

<template>
    <Dialog :open="open" @update:open="(value) => emit('update:open', value)">
        <DialogContent class="sm:max-w-md">
            <DialogHeader>
                <DialogTitle>{{ title }}</DialogTitle>
                <DialogDescription>{{ description }}</DialogDescription>
            </DialogHeader>

            <form class="flex flex-col gap-4" @submit.prevent="submit">
                <template v-if="fields === 'reason'">
                    <div class="flex flex-col gap-2">
                        <Label for="reason">Reason (optional)</Label>
                        <Textarea
                            id="reason"
                            v-model="form.reason"
                            rows="3"
                            placeholder="Shown to the customer on Track Order."
                        />
                        <p
                            v-if="form.errors.reason"
                            class="text-sm text-destructive"
                        >
                            {{ form.errors.reason }}
                        </p>
                    </div>
                </template>

                <template v-else>
                    <div class="flex flex-col gap-2">
                        <Label for="tracking_number">Tracking number</Label>
                        <Input
                            id="tracking_number"
                            v-model="form.tracking_number"
                            placeholder="e.g. JT2260041188PH"
                            required
                        />
                        <p
                            v-if="form.errors.tracking_number"
                            class="text-sm text-destructive"
                        >
                            {{ form.errors.tracking_number }}
                        </p>
                    </div>

                    <div class="flex flex-col gap-2">
                        <Label for="shipped_via">Shipped via</Label>
                        <Input
                            id="shipped_via"
                            v-model="form.shipped_via"
                            :placeholder="courierName"
                        />
                        <p class="text-xs text-muted-foreground">
                            Prefilled from checkout. Change it if the parcel
                            actually went with someone else; leaving it blank
                            keeps the original courier.
                        </p>
                    </div>
                </template>

                <DialogFooter>
                    <Button
                        type="button"
                        variant="outline"
                        @click="emit('update:open', false)"
                    >
                        Cancel
                    </Button>
                    <Button
                        type="submit"
                        :variant="destructive ? 'destructive' : 'default'"
                        :disabled="form.processing"
                    >
                        {{ form.processing ? 'Working…' : confirmLabel }}
                    </Button>
                </DialogFooter>
            </form>
        </DialogContent>
    </Dialog>
</template>
