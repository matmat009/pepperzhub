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
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Switch } from '@/components/ui/switch';
import { Textarea } from '@/components/ui/textarea';
import { store, update } from '@/routes/admin/reviews';
import {
    emptyReviewForm,
    NO_PRODUCT,
    toReviewForm,
    toReviewPayload,
} from '../types';
import type { Review, ReviewFormFields, ReviewProductOption } from '../types';
import ReviewImageUpload from './ReviewImageUpload.vue';

/**
 * One dialog instance owned by Index.vue, serving both create and edit:
 * `review` null means create, a review means edit.
 *
 * useForm rather than plain router calls because the photo is a file — the
 * payload has to go as FormData, which also forces the POST-spoofed PUT below.
 */
const props = defineProps<{
    review: Review | null;
    products: ReviewProductOption[];
}>();

const open = defineModel<boolean>('open', { default: false });

const form = useForm<ReviewFormFields>(emptyReviewForm());

const isEdit = computed(() => props.review !== null);

// Reseed each time the dialog opens, so a cancelled edit never leaks into the
// next one.
watch(open, (isOpen) => {
    if (!isOpen) {
        return;
    }

    form.defaults(
        props.review ? toReviewForm(props.review) : emptyReviewForm(),
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

    if (props.review) {
        // FormData cannot ride a real PUT, so the update is spoofed over POST —
        // required as soon as the photo is in the payload.
        form.transform((fields) => ({
            ...toReviewPayload(fields as ReviewFormFields),
            _method: 'put',
        })).post(update(props.review.id).url, options);

        return;
    }

    form.transform((fields) =>
        toReviewPayload(fields as ReviewFormFields),
    ).post(store().url, options);
};
</script>

<template>
    <Dialog v-model:open="open">
        <DialogContent
            class="max-h-[calc(100vh-2rem)] grid-rows-[auto_minmax(0,1fr)_auto_auto] gap-0 overflow-hidden p-0 sm:max-w-4xl [&_[data-slot=dialog-close]]:top-6 [&_[data-slot=dialog-close]]:right-6"
        >
            <DialogHeader
                class="border-b border-sf-serenity-blue/20 bg-linear-to-r from-sf-serenity-blue/20 via-background to-sf-rose-quartz/25 px-6 py-6 pr-14 sm:px-8 sm:pr-16"
            >
                <DialogTitle class="text-2xl leading-tight">
                    {{ isEdit ? 'Edit review' : 'New review' }}
                </DialogTitle>
                <DialogDescription>
                    {{
                        isEdit
                            ? 'Changes show on the storefront straight away.'
                            : 'Shown on the Reviews page, and on the tagged product page if you pick one.'
                    }}
                </DialogDescription>
            </DialogHeader>

            <div
                class="grid min-h-0 gap-5 overflow-y-auto px-6 py-6 sm:px-8 lg:grid-cols-[minmax(0,1.35fr)_minmax(18rem,0.95fr)] lg:gap-x-8"
            >
                <div class="grid gap-2 lg:col-start-1 lg:row-start-1">
                    <Label for="review-product">
                        Product
                        <span class="font-normal text-muted-foreground">
                            (optional)
                        </span>
                    </Label>
                    <Select v-model="form.product_id">
                        <SelectTrigger id="review-product" class="w-full">
                            <SelectValue placeholder="No product" />
                        </SelectTrigger>
                        <SelectContent>
                            <!--
                                A review need not be about any one product, so
                                this stays selectable rather than being a
                                placeholder the user cannot return to.
                            -->
                            <SelectItem :value="NO_PRODUCT">
                                No product
                            </SelectItem>
                            <SelectItem
                                v-for="product in products"
                                :key="product.id"
                                :value="String(product.id)"
                            >
                                {{ product.name }}
                            </SelectItem>
                        </SelectContent>
                    </Select>
                    <p class="text-sm text-muted-foreground">
                        Tagged reviews also appear on that product's page.
                    </p>
                    <InputError :message="form.errors.product_id" />
                </div>

                <div class="grid gap-2 lg:col-start-1 lg:row-start-2">
                    <Label for="review-customer">
                        Customer name
                        <span class="font-normal text-muted-foreground">
                            (optional)
                        </span>
                    </Label>
                    <Input
                        id="review-customer"
                        v-model="form.customer_name"
                        placeholder="e.g. Maria S."
                        autocomplete="off"
                    />
                    <InputError :message="form.errors.customer_name" />
                </div>

                <div class="grid gap-2 lg:col-start-1 lg:row-start-3">
                    <Label for="review-title">Title</Label>
                    <Input
                        id="review-title"
                        v-model="form.title"
                        placeholder="e.g. Great results in three weeks"
                        autocomplete="off"
                    />
                    <InputError :message="form.errors.title" />
                </div>

                <div class="grid gap-2 lg:col-start-1 lg:row-start-4">
                    <Label for="review-description">Review</Label>
                    <Textarea
                        id="review-description"
                        v-model="form.description"
                        rows="5"
                        placeholder="What the customer said."
                    />
                    <InputError :message="form.errors.description" />
                </div>

                <div
                    class="grid gap-2 lg:col-start-2 lg:row-start-1 lg:row-end-6 lg:border-l lg:border-sf-serenity-blue/20 lg:pl-8"
                >
                    <Label>Photo</Label>
                    <ReviewImageUpload
                        v-model:file="form.image"
                        v-model:removed="form.remove_image"
                        :existing-url="review?.image_url ?? null"
                    />
                    <InputError :message="form.errors.image" />
                </div>

                <div
                    class="grid gap-2 sm:max-w-40 lg:col-start-1 lg:row-start-5"
                >
                    <Label for="review-sort">Sort order</Label>
                    <Input
                        id="review-sort"
                        v-model.number="form.sort_order"
                        type="number"
                        min="0"
                        inputmode="numeric"
                    />
                    <InputError :message="form.errors.sort_order" />
                </div>
            </div>

            <div class="px-6 pb-6 sm:px-8">
                <div
                    class="flex items-start justify-between gap-4 rounded-lg border p-3"
                >
                    <div class="grid gap-1">
                        <Label for="review-active">Active</Label>
                        <p class="text-sm text-muted-foreground">
                            Inactive reviews disappear from the storefront
                            immediately, but stay on file here.
                        </p>
                    </div>
                    <Switch id="review-active" v-model="form.is_active" />
                </div>
            </div>

            <DialogFooter class="border-t px-6 py-4 sm:px-8">
                <Button
                    variant="outline"
                    :disabled="form.processing"
                    @click="open = false"
                >
                    Cancel
                </Button>
                <Button :loading="form.processing" @click="submit">
                    {{ isEdit ? 'Save changes' : 'Create review' }}
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
