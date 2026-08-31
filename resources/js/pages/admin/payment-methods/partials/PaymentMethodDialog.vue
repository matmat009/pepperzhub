<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { computed, watch } from 'vue';
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
import { store, update } from '@/routes/admin/payment-methods';
import {
    emptyPaymentMethodForm,
    toPaymentMethodForm,
    toPaymentMethodPayload,
} from '../types';
import type { PaymentMethod, PaymentMethodFormFields } from '../types';
import DetailRows from './DetailRows.vue';
import QrCodeUpload from './QrCodeUpload.vue';

/**
 * One dialog instance owned by Index.vue, serving both create and edit:
 * `method` null means create, a method means edit.
 *
 * useForm rather than plain router calls because the QR code is a file — the
 * payload has to go as FormData, which also forces the POST-spoofed PUT below.
 */
const props = defineProps<{
    method: PaymentMethod | null;
}>();

const open = defineModel<boolean>('open', { default: false });

const form = useForm<PaymentMethodFormFields>(emptyPaymentMethodForm());

const isEdit = computed(() => props.method !== null);

// Reseed each time the dialog opens, so a cancelled edit never leaks into the
// next one.
watch(open, (isOpen) => {
    if (!isOpen) {
        return;
    }

    form.defaults(
        props.method
            ? toPaymentMethodForm(props.method)
            : emptyPaymentMethodForm(),
    );
    form.reset();
    form.clearErrors();
});

const submit = () => {
    const options = {
        preserveScroll: true,
        onSuccess: () => {
            open.value = false;
        },
    };

    if (props.method) {
        // FormData cannot ride a real PUT, so the update is spoofed over POST —
        // required as soon as the QR code file is in the payload.
        form.transform((fields) => ({
            ...toPaymentMethodPayload(fields as PaymentMethodFormFields),
            _method: 'put',
        })).post(update(props.method.id).url, options);

        return;
    }

    form.transform((fields) =>
        toPaymentMethodPayload(fields as PaymentMethodFormFields),
    ).post(store().url, options);
};
</script>

<template>
    <Dialog v-model:open="open">
        <DialogContent class="sm:max-w-xl">
            <DialogHeader>
                <DialogTitle>
                    {{ isEdit ? 'Edit payment method' : 'New payment method' }}
                </DialogTitle>
                <DialogDescription>
                    {{
                        isEdit
                            ? 'Changes apply to new orders only — past orders keep the details they were placed with.'
                            : 'Customers pick from the active methods at checkout.'
                    }}
                </DialogDescription>
            </DialogHeader>

            <div class="grid max-h-[60vh] gap-5 overflow-y-auto px-1">
                <div class="grid gap-2">
                    <Label for="payment-method-name">Name</Label>
                    <Input
                        id="payment-method-name"
                        v-model="form.name"
                        placeholder="e.g. GOtyme Bank"
                        autocomplete="off"
                    />
                    <InputError :message="form.errors.name" />
                </div>

                <div class="grid gap-2">
                    <Label>Payment details</Label>
                    <p class="text-sm text-muted-foreground">
                        Shown to the customer at checkout, in this order.
                    </p>
                    <DetailRows
                        v-model="form.details"
                        :errors="form.errors as Record<string, string>"
                    />
                    <InputError :message="form.errors.details" />
                </div>

                <div class="grid gap-2">
                    <Label>QR code</Label>
                    <QrCodeUpload
                        v-model:file="form.qr_code"
                        v-model:removed="form.remove_qr_code"
                        :existing-url="method?.qr_code_url ?? null"
                    />
                    <InputError :message="form.errors.qr_code" />
                </div>

                <div class="grid gap-2 sm:max-w-40">
                    <Label for="payment-method-sort">Sort order</Label>
                    <Input
                        id="payment-method-sort"
                        v-model.number="form.sort_order"
                        type="number"
                        min="0"
                        inputmode="numeric"
                    />
                    <InputError :message="form.errors.sort_order" />
                </div>

                <div
                    class="flex items-start justify-between gap-4 rounded-lg border p-3"
                >
                    <div class="grid gap-1">
                        <Label for="payment-method-active">Active</Label>
                        <p class="text-sm text-muted-foreground">
                            Inactive methods disappear from checkout
                            immediately. Existing orders are unaffected.
                        </p>
                    </div>
                    <Switch
                        id="payment-method-active"
                        v-model="form.is_active"
                    />
                </div>
            </div>

            <DialogFooter>
                <Button
                    variant="outline"
                    :disabled="form.processing"
                    @click="open = false"
                >
                    Cancel
                </Button>
                <Button :disabled="form.processing" @click="submit">
                    <Spinner v-if="form.processing" />
                    {{ isEdit ? 'Save changes' : 'Create method' }}
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
