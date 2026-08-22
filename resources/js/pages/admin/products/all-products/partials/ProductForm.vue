<script setup lang="ts">
import { ImagePlus } from '@lucide/vue';
import InputError from '@/components/InputError.vue';
import { Card, CardContent } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import { PRODUCT_CATEGORIES, PRODUCT_STATUSES, PRODUCT_TYPES } from '../types';
import type { ProductFormFields } from '../types';

/**
 * Shared field markup for Create.vue and Show.vue.
 *
 * The field values come through `defineModel` rather than a plain prop, so the
 * page that owns them stays the single source of truth and this component can
 * write to them without tripping `vue/no-mutating-props`. `readonly` flips
 * every control to disabled for Show.vue's default state.
 */
const fields = defineModel<ProductFormFields>({ required: true });

withDefaults(
    defineProps<{
        errors?: Partial<Record<keyof ProductFormFields, string>>;
        readonly?: boolean;
    }>(),
    {
        errors: () => ({}),
        readonly: false,
    },
);

const onThumbnail = (event: Event) => {
    const input = event.target as HTMLInputElement;

    fields.value.thumbnail = input.files?.[0] ?? null;
};

/**
 * Disabled controls ship at 50% opacity, which makes a readonly product read
 * like a form full of placeholders. Keep the inert behaviour, but render the
 * values at full contrast on a muted field.
 */
const inert =
    'disabled:cursor-default disabled:opacity-100 disabled:bg-muted/40 disabled:text-foreground';
</script>

<template>
    <Card class="shadow-sm">
        <CardContent class="grid gap-6 md:grid-cols-2">
            <div class="grid gap-2 md:col-span-2">
                <Label for="name">Name</Label>
                <Input
                    id="name"
                    v-model="fields.name"
                    :disabled="readonly"
                    :class="inert"
                    placeholder="e.g. BPC-157"
                    autocomplete="off"
                />
                <InputError :message="errors.name" />
            </div>

            <div class="grid gap-2">
                <Label for="type">Type</Label>
                <Select v-model="fields.type" :disabled="readonly">
                    <SelectTrigger id="type" :class="['w-full', inert]">
                        <SelectValue placeholder="Select a type" />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem
                            v-for="type in PRODUCT_TYPES"
                            :key="type"
                            :value="type"
                        >
                            {{ type }}
                        </SelectItem>
                    </SelectContent>
                </Select>
                <InputError :message="errors.type" />
            </div>

            <div class="grid gap-2">
                <Label for="category">Category</Label>
                <Select v-model="fields.category" :disabled="readonly">
                    <SelectTrigger id="category" :class="['w-full', inert]">
                        <SelectValue placeholder="Select a category" />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem
                            v-for="category in PRODUCT_CATEGORIES"
                            :key="category"
                            :value="category"
                        >
                            {{ category }}
                        </SelectItem>
                    </SelectContent>
                </Select>
                <InputError :message="errors.category" />
            </div>

            <div class="grid gap-2">
                <Label for="purity">Purity %</Label>
                <Input
                    id="purity"
                    v-model="fields.purity"
                    type="number"
                    step="0.1"
                    min="0"
                    max="100"
                    :disabled="readonly"
                    :class="inert"
                    placeholder="99.2"
                />
                <InputError :message="errors.purity" />
            </div>

            <div class="grid gap-2">
                <Label for="price">Price</Label>
                <Input
                    id="price"
                    v-model="fields.price"
                    type="number"
                    step="0.01"
                    min="0"
                    :disabled="readonly"
                    :class="inert"
                    placeholder="45.00"
                />
                <InputError :message="errors.price" />
            </div>

            <div class="grid gap-2">
                <Label for="stock">Stock</Label>
                <Input
                    id="stock"
                    v-model="fields.stock"
                    type="number"
                    min="0"
                    :disabled="readonly"
                    :class="inert"
                    placeholder="0"
                />
                <InputError :message="errors.stock" />
            </div>

            <div class="grid gap-2">
                <Label for="status">Status</Label>
                <Select v-model="fields.status" :disabled="readonly">
                    <SelectTrigger id="status" :class="['w-full', inert]">
                        <SelectValue placeholder="Select a status" />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem
                            v-for="status in PRODUCT_STATUSES"
                            :key="status"
                            :value="status"
                        >
                            {{ status }}
                        </SelectItem>
                    </SelectContent>
                </Select>
                <InputError :message="errors.status" />
            </div>

            <div class="grid gap-2 md:col-span-2">
                <Label for="description">Short description</Label>
                <Textarea
                    id="description"
                    v-model="fields.description"
                    :disabled="readonly"
                    :class="inert"
                    rows="3"
                    placeholder="One line describing the product."
                />
                <InputError :message="errors.description" />
            </div>

            <div class="grid gap-2 md:col-span-2">
                <Label for="thumbnail">Thumbnail</Label>
                <label
                    :class="[
                        'flex items-center gap-3 rounded-lg border border-dashed px-4 py-3 text-sm transition-colors',
                        readonly
                            ? 'cursor-not-allowed opacity-60'
                            : 'cursor-pointer hover:border-ring hover:bg-accent/40',
                    ]"
                >
                    <ImagePlus class="size-4 text-muted-foreground" />
                    <span class="text-muted-foreground">
                        {{
                            fields.thumbnail
                                ? fields.thumbnail.name
                                : 'Upload an image (PNG or JPG)'
                        }}
                    </span>
                    <input
                        id="thumbnail"
                        type="file"
                        accept="image/*"
                        class="sr-only"
                        :disabled="readonly"
                        :class="inert"
                        @change="onThumbnail"
                    />
                </label>
                <InputError :message="errors.thumbnail" />
            </div>
        </CardContent>
    </Card>
</template>
