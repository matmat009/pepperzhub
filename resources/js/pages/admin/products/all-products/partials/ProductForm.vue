<script setup lang="ts">
import { Plus } from '@lucide/vue';
import { ref } from 'vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
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
import { PRODUCT_STATUSES } from '../types';
import type {
    CategoryOption,
    ProductFormFields,
    ProductVariant,
} from '../types';
import EntryList from './EntryList.vue';
import FormatDialog from './FormatDialog.vue';
import FormatsTable from './FormatsTable.vue';
import ImageUpload from './ImageUpload.vue';

/**
 * The whole two-column product editor, shared verbatim by Create.vue and
 * Show.vue so the two screens cannot drift.
 *
 * Values come through `defineModel` rather than a plain prop, so the page that
 * owns them stays the single source of truth (and so this component can write
 * without tripping `vue/no-mutating-props`). `readonly` locks every control,
 * hides the format row actions and disables the dropzone.
 */
const fields = defineModel<ProductFormFields>({ required: true });

const props = withDefaults(
    defineProps<{
        categories: CategoryOption[];
        /**
         * Keyed loosely because Laravel reports nested failures as
         * `variants.0.price`, which no keyof of the form type can express.
         */
        errors?: Record<string, string>;
        readonly?: boolean;
    }>(),
    {
        errors: () => ({}),
        readonly: false,
    },
);

/**
 * Disabled controls ship at 50% opacity, which makes a readonly product read
 * like a form full of placeholders. Keep the inert behaviour, restore contrast.
 */
const inert =
    'disabled:cursor-default disabled:opacity-100 disabled:bg-muted/40 disabled:text-foreground';

/**
 * Laravel reports nested failures per index (`variants.0.price`). Surface the
 * first one for a section rather than leaving the user with a silent rejection.
 */
const firstError = (prefix: string): string | undefined =>
    Object.entries(props.errors).find(([key]) => key.startsWith(prefix))?.[1];

const formatDialogOpen = ref(false);
const editingVariant = ref<ProductVariant | null>(null);

const openAddFormat = () => {
    editingVariant.value = null;
    formatDialogOpen.value = true;
};

const openEditFormat = (variant: ProductVariant) => {
    editingVariant.value = variant;
    formatDialogOpen.value = true;
};

const saveFormat = (variant: ProductVariant) => {
    const index = fields.value.variants.findIndex(
        (item) => item.id === variant.id,
    );

    fields.value.variants =
        index === -1
            ? [...fields.value.variants, variant]
            : fields.value.variants.map((item) =>
                  item.id === variant.id ? variant : item,
              );
};

const removeFormat = (variant: ProductVariant) => {
    fields.value.variants = fields.value.variants.filter(
        (item) => item.id !== variant.id,
    );
};
</script>

<template>
    <div class="grid gap-6 lg:grid-cols-[minmax(0,1.65fr)_minmax(0,1fr)]">
        <!-- LEFT COLUMN -->
        <div class="space-y-6">
            <Card class="border-transparent shadow-sm shadow-black/5">
                <CardHeader>
                    <CardTitle class="text-base">Basic Details</CardTitle>
                </CardHeader>
                <CardContent class="grid gap-5">
                    <div class="grid gap-2">
                        <Label for="name">Product Name</Label>
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

                    <div class="grid gap-5 sm:grid-cols-2">
                        <div class="grid gap-2">
                            <Label for="category">Category</Label>
                            <Select
                                v-model="fields.category_id"
                                :disabled="readonly"
                            >
                                <SelectTrigger
                                    id="category"
                                    :class="['w-full', inert]"
                                >
                                    <SelectValue
                                        placeholder="Select a category"
                                    />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem
                                        v-for="category in categories"
                                        :key="category.id"
                                        :value="category.id"
                                    >
                                        {{ category.name }}
                                    </SelectItem>
                                </SelectContent>
                            </Select>
                            <InputError :message="errors.category_id" />
                        </div>

                        <div class="grid gap-2">
                            <Label for="status">Status</Label>
                            <Select
                                v-model="fields.status"
                                :disabled="readonly"
                            >
                                <SelectTrigger
                                    id="status"
                                    :class="['w-full', inert]"
                                >
                                    <SelectValue
                                        placeholder="Select a status"
                                    />
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
                    </div>

                    <div
                        class="flex items-center justify-between rounded-lg border px-3 py-2.5"
                    >
                        <div class="space-y-0.5">
                            <Label for="featured" class="font-medium">
                                Featured
                            </Label>
                            <p class="text-xs text-muted-foreground">
                                Highlight this product in the storefront's
                                featured section.
                            </p>
                        </div>
                        <Switch
                            id="featured"
                            v-model="fields.featured"
                            :disabled="readonly"
                        />
                    </div>

                    <div class="grid gap-2">
                        <Label for="short-description">
                            Short Description
                        </Label>
                        <Input
                            id="short-description"
                            v-model="fields.short_description"
                            :disabled="readonly"
                            :class="inert"
                            placeholder="One line shown on the storefront grid"
                            autocomplete="off"
                        />
                        <InputError :message="errors.short_description" />
                    </div>

                    <div class="grid gap-2">
                        <Label for="description">Full Description</Label>
                        <Textarea
                            id="description"
                            v-model="fields.full_description"
                            :disabled="readonly"
                            :class="inert"
                            rows="5"
                            placeholder="What this compound is and how it is supplied."
                        />
                        <InputError :message="errors.full_description" />
                    </div>
                </CardContent>
            </Card>

            <Card class="border-transparent shadow-sm shadow-black/5">
                <CardHeader>
                    <CardTitle class="text-base">
                        Formats &amp; Pricing
                    </CardTitle>
                    <p class="text-sm text-muted-foreground">
                        Each format is bought separately and carries its own
                        price and stock.
                    </p>
                    <CardAction v-if="!readonly">
                        <Button
                            type="button"
                            variant="outline"
                            size="sm"
                            @click="openAddFormat"
                        >
                            <Plus />
                            Add Format
                        </Button>
                    </CardAction>
                </CardHeader>
                <CardContent>
                    <FormatsTable
                        :variants="fields.variants"
                        :readonly="readonly"
                        @edit="openEditFormat"
                        @remove="removeFormat"
                    />
                    <InputError
                        class="mt-2"
                        :message="firstError('variants')"
                    />
                </CardContent>
            </Card>

            <Card class="border-transparent shadow-sm shadow-black/5">
                <CardHeader>
                    <CardTitle class="text-base">Technical Details</CardTitle>
                    <p class="text-sm text-muted-foreground">
                        Specification lines shown on the product page. Each row
                        is a label and its value.
                    </p>
                </CardHeader>
                <CardContent class="grid gap-6">
                    <div class="grid gap-2">
                        <Label>Purity</Label>
                        <EntryList
                            v-model="fields.purity_entries"
                            id-prefix="purity"
                            add-label="Add Purity"
                            label-placeholder="e.g. HPLC"
                            value-placeholder="e.g. 99.2%"
                            :readonly="readonly"
                        />
                        <InputError :message="firstError('purity')" />
                    </div>

                    <div class="grid gap-2">
                        <Label>Storage Instructions</Label>
                        <EntryList
                            v-model="fields.storage_instructions"
                            id-prefix="storage"
                            add-label="Add Storage Instruction"
                            label-placeholder="e.g. Temperature"
                            value-placeholder="e.g. Store at 2-8°C"
                            :readonly="readonly"
                        />
                        <InputError :message="firstError('storage')" />
                    </div>
                </CardContent>
            </Card>
        </div>

        <!-- RIGHT COLUMN -->
        <div class="space-y-6">
            <Card class="border-transparent shadow-sm shadow-black/5">
                <CardHeader>
                    <CardTitle class="text-base">
                        Upload Product Image
                    </CardTitle>
                </CardHeader>
                <CardContent>
                    <ImageUpload v-model="fields.images" :readonly="readonly" />
                </CardContent>
            </Card>
        </div>
    </div>

    <FormatDialog
        v-model:open="formatDialogOpen"
        :variant="editingVariant"
        @save="saveFormat"
    />
</template>
