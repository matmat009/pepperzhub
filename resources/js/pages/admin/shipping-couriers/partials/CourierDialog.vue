<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import InputError from '@/components/InputError.vue';
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
import { Spinner } from '@/components/ui/spinner';
import { Switch } from '@/components/ui/switch';
import { store, update } from '@/routes/admin/shipping-couriers';
import { emptyCourierForm, toCourierForm, toCourierPayload } from '../types';
import type { CourierFormFields, ShippingCourier } from '../types';
import RegionRows from './RegionRows.vue';

/**
 * One dialog instance owned by Index.vue, serving both create and edit:
 * `courier` null means create, a courier means edit.
 *
 * The courier and its regions are saved in one request, matching how a product
 * is saved with its variants. No files here, so this uses plain router calls
 * and a real PUT rather than the payment-method dialog's POST spoof.
 */
const props = defineProps<{
    courier: ShippingCourier | null;
}>();

const open = defineModel<boolean>('open', { default: false });

const fields = ref<CourierFormFields>(emptyCourierForm());
const errors = ref<Record<string, string>>({});
const processing = ref(false);

const isEdit = computed(() => props.courier !== null);

// Reseed each time the dialog opens so a cancelled edit never leaks into the
// next one.
watch(open, (isOpen) => {
    if (!isOpen) {
        return;
    }

    fields.value = props.courier
        ? toCourierForm(props.courier)
        : emptyCourierForm();
    errors.value = {};
});

const submit = () => {
    processing.value = true;

    const options = {
        preserveScroll: true,
        onError: (formErrors: Record<string, string>) => {
            errors.value = formErrors;
        },
        onSuccess: () => {
            open.value = false;
        },
        onFinish: () => {
            processing.value = false;
        },
    };

    const payload = toCourierPayload(fields.value);

    if (props.courier) {
        router.put(update(props.courier.id).url, payload, options);

        return;
    }

    router.post(store().url, payload, options);
};
</script>

<template>
    <Dialog v-model:open="open">
        <DialogContent class="sm:max-w-2xl">
            <DialogHeader>
                <DialogTitle>
                    {{ isEdit ? 'Edit courier' : 'New courier' }}
                </DialogTitle>
                <DialogDescription>
                    {{
                        isEdit
                            ? 'Rate changes apply to new orders only — past orders keep the region and fee they were placed with.'
                            : 'Customers choose a courier, then one of its available delivery options.'
                    }}
                </DialogDescription>
            </DialogHeader>

            <div class="grid max-h-[60vh] gap-5 overflow-y-auto px-1">
                <div class="grid gap-2">
                    <Label for="courier-name">Name</Label>
                    <Input
                        id="courier-name"
                        v-model="fields.name"
                        placeholder="e.g. J&T Express"
                        autocomplete="off"
                    />
                    <InputError :message="errors.name" />
                </div>

                <div class="grid gap-2 sm:max-w-40">
                    <Label for="courier-sort">Sort order</Label>
                    <Input
                        id="courier-sort"
                        v-model.number="fields.sort_order"
                        type="number"
                        min="0"
                        inputmode="numeric"
                    />
                    <InputError :message="errors.sort_order" />
                </div>

                <div
                    class="flex items-start justify-between gap-4 rounded-lg border p-3"
                >
                    <div class="grid gap-1">
                        <Label for="courier-active">Active</Label>
                        <p class="text-sm text-muted-foreground">
                            Inactive couriers disappear from checkout
                            immediately. Existing orders are unaffected.
                        </p>
                    </div>
                    <Switch id="courier-active" v-model="fields.is_active" />
                </div>

                <div class="grid gap-2">
                    <Label>Delivery options</Label>
                    <p class="text-sm text-muted-foreground">
                        Configure the delivery options and flat rates available
                        for this courier.
                    </p>
                    <RegionRows v-model="fields.regions" :errors="errors" />
                </div>
            </div>

            <DialogFooter>
                <Button
                    variant="outline"
                    :disabled="processing"
                    @click="open = false"
                >
                    Cancel
                </Button>
                <Button :disabled="processing" @click="submit">
                    <Spinner v-if="processing" />
                    {{ isEdit ? 'Save changes' : 'Create courier' }}
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
