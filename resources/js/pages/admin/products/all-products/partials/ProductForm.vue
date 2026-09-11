<script setup lang="ts">
import {
    ClipboardList,
    FlaskConical,
    Package,
    Plus,
    Tag,
    X,
} from '@lucide/vue';
import { computed, ref } from 'vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardAction,
    CardContent,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
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
import { emptyEntry, PRODUCT_STATUSES } from '../types';
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
        createStyle?: boolean;
    }>(),
    {
        errors: () => ({}),
        readonly: false,
        createStyle: false,
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

const shortDescriptionRequired = computed(
    () => fields.value.status === 'Active',
);

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

/**
 * Protocol notes are a plain list of lines, the same interaction as a variant's
 * kit inclusions — push a blank row, splice one out. They are not label/value
 * pairs, so EntryList is deliberately not used here.
 */
const addNote = () => {
    fields.value.protocol_notes.push(emptyEntry());
};

const removeNote = (index: number) => {
    fields.value.protocol_notes.splice(index, 1);
};
</script>

<template>
    <div
        :class="[
            'grid',
            createStyle
                ? 'gap-4 xl:grid-cols-[minmax(0,1.8fr)_minmax(19rem,0.85fr)]'
                : 'gap-6 lg:grid-cols-[minmax(0,1.65fr)_minmax(0,1fr)]',
        ]"
    >
        <!-- LEFT COLUMN -->
        <div :class="createStyle ? 'space-y-4' : 'space-y-6'">
            <Card
                :class="
                    createStyle
                        ? 'gap-0 rounded-xl border-primary/10 py-0 shadow-sm shadow-sf-serenity-blue/10 dark:border-primary/20 dark:shadow-none'
                        : 'border-transparent shadow-sm shadow-black/5'
                "
            >
                <CardHeader :class="createStyle ? 'px-5 pt-5 pb-4' : ''">
                    <div class="flex items-start gap-3">
                        <span
                            v-if="createStyle"
                            class="flex size-9 shrink-0 items-center justify-center rounded-lg bg-sf-serenity-blue/20 text-primary dark:bg-primary/15"
                        >
                            <Tag class="size-5" />
                        </span>
                        <div class="space-y-1">
                            <CardTitle class="text-base">
                                Basic Details
                            </CardTitle>
                            <p
                                v-if="createStyle"
                                class="text-sm text-muted-foreground"
                            >
                                Add the essential information about your
                                product.
                            </p>
                        </div>
                    </div>
                </CardHeader>
                <CardContent
                    :class="['grid', createStyle ? 'gap-4 px-5 pb-5' : 'gap-5']"
                >
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
                        :class="[
                            'flex items-center justify-between',
                            createStyle
                                ? 'py-0.5'
                                : 'rounded-lg border px-3 py-2.5',
                        ]"
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
                            <span
                                v-if="shortDescriptionRequired"
                                class="text-destructive"
                                aria-hidden="true"
                                >*</span
                            >
                            <span
                                v-else
                                class="font-normal text-muted-foreground"
                            >
                                (Optional)
                            </span>
                        </Label>
                        <Input
                            id="short-description"
                            v-model="fields.short_description"
                            :disabled="readonly"
                            :aria-required="shortDescriptionRequired"
                            :class="inert"
                            placeholder="One line shown on the storefront grid"
                            autocomplete="off"
                        />
                        <p
                            v-if="!readonly"
                            class="text-xs text-muted-foreground"
                        >
                            {{
                                shortDescriptionRequired
                                    ? 'Required for active products.'
                                    : 'Optional while this product is not active.'
                            }}
                        </p>
                        <InputError :message="errors.short_description" />
                    </div>

                    <div class="grid gap-2">
                        <Label for="description">
                            Full Description
                            <span class="font-normal text-muted-foreground">
                                (Optional)
                            </span>
                        </Label>
                        <Textarea
                            id="description"
                            v-model="fields.full_description"
                            :disabled="readonly"
                            :class="inert"
                            :rows="createStyle ? 3 : 5"
                            placeholder="What this compound is and how it is supplied."
                        />
                        <InputError :message="errors.full_description" />
                    </div>
                </CardContent>
            </Card>

            <Card
                :class="
                    createStyle
                        ? 'gap-0 rounded-xl border-primary/10 py-0 shadow-sm shadow-sf-serenity-blue/10 dark:border-primary/20 dark:shadow-none'
                        : 'border-transparent shadow-sm shadow-black/5'
                "
            >
                <CardHeader :class="createStyle ? 'px-5 pt-5 pb-4' : ''">
                    <div
                        :class="[
                            'flex items-start gap-3',
                            createStyle &&
                                'flex-col sm:flex-row sm:justify-between',
                        ]"
                    >
                        <div class="flex items-start gap-3">
                            <span
                                v-if="createStyle"
                                class="flex size-9 shrink-0 items-center justify-center rounded-lg bg-sf-serenity-blue/20 text-primary dark:bg-primary/15"
                            >
                                <Package class="size-5" />
                            </span>
                            <div class="space-y-1">
                                <CardTitle class="text-base">
                                    Formats &amp; Pricing
                                </CardTitle>
                                <p class="text-sm text-muted-foreground">
                                    Each format is bought separately and carries
                                    its own price and stock.
                                </p>
                            </div>
                        </div>

                        <Button
                            v-if="!readonly && createStyle"
                            type="button"
                            variant="outline"
                            size="sm"
                            class="shrink-0"
                            @click="openAddFormat"
                        >
                            <Plus />
                            Add Format
                        </Button>
                    </div>
                    <CardAction v-if="!readonly && !createStyle">
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
                <CardContent :class="createStyle ? 'px-5 pb-5' : ''">
                    <FormatsTable
                        :variants="fields.variants"
                        :readonly="readonly"
                        :compact="createStyle"
                        @edit="openEditFormat"
                        @remove="removeFormat"
                    />
                    <InputError
                        class="mt-2"
                        :message="firstError('variants')"
                    />
                </CardContent>
            </Card>

            <Card
                :class="
                    createStyle
                        ? 'gap-0 rounded-xl border-primary/10 py-0 shadow-sm shadow-sf-serenity-blue/10 dark:border-primary/20 dark:shadow-none'
                        : 'border-transparent shadow-sm shadow-black/5'
                "
            >
                <CardHeader :class="createStyle ? 'px-5 pt-5 pb-4' : ''">
                    <div class="flex items-start gap-3">
                        <span
                            v-if="createStyle"
                            class="flex size-9 shrink-0 items-center justify-center rounded-lg bg-sf-serenity-blue/20 text-primary dark:bg-primary/15"
                        >
                            <FlaskConical class="size-5" />
                        </span>
                        <div class="space-y-1">
                            <CardTitle class="text-base">
                                Technical Details
                            </CardTitle>
                            <p class="text-sm text-muted-foreground">
                                Specification lines shown on the product page.
                                Each row is a label and its value.
                            </p>
                        </div>
                    </div>
                </CardHeader>
                <CardContent
                    :class="['grid', createStyle ? 'gap-5 px-5 pb-5' : 'gap-6']"
                >
                    <div class="grid gap-2">
                        <Label>Purity</Label>
                        <EntryList
                            v-model="fields.purity_entries"
                            id-prefix="purity"
                            add-label="Add Purity"
                            label-placeholder="e.g. HPLC"
                            value-placeholder="e.g. 99.2%"
                            :readonly="readonly"
                            :blue-outline="createStyle"
                        />
                        <InputError :message="firstError('purity')" />
                    </div>

                    <div class="grid gap-2">
                        <Label>Storage Instructions</Label>
                        <EntryList
                            v-model="fields.storage_instructions"
                            id-prefix="storage"
                            add-label="Add Storage Instruction"
                            label-placeholder="Label (optional), e.g. Temperature"
                            value-placeholder="Instruction or temperature, e.g. 2-8°C"
                            :readonly="readonly"
                            :blue-outline="createStyle"
                        />
                        <InputError :message="firstError('storage')" />
                    </div>
                </CardContent>
            </Card>

            <Card
                :class="
                    createStyle
                        ? 'gap-0 rounded-xl border-primary/10 py-0 shadow-sm shadow-sf-serenity-blue/10 dark:border-primary/20 dark:shadow-none'
                        : 'border-transparent shadow-sm shadow-black/5'
                "
            >
                <CardHeader :class="createStyle ? 'px-5 pt-5 pb-4' : ''">
                    <div class="flex items-start gap-3">
                        <span
                            v-if="createStyle"
                            class="flex size-9 shrink-0 items-center justify-center rounded-lg bg-sf-serenity-blue/20 text-primary dark:bg-primary/15"
                        >
                            <ClipboardList class="size-5" />
                        </span>
                        <div class="space-y-1">
                            <CardTitle class="text-base">Protocol</CardTitle>
                            <p class="text-sm text-muted-foreground">
                                Dosage guidance for the storefront's Protocols
                                page. Leave it all blank and this product stays
                                off that page entirely.
                            </p>
                        </div>
                    </div>
                </CardHeader>
                <CardContent
                    :class="['grid', createStyle ? 'gap-5 px-5 pb-5' : 'gap-6']"
                >
                    <div class="grid gap-5 sm:grid-cols-3">
                        <div class="grid gap-2">
                            <Label for="dosage">
                                Dosage
                                <span class="font-normal text-muted-foreground">
                                    (Optional)
                                </span>
                            </Label>
                            <Input
                                id="dosage"
                                v-model="fields.dosage"
                                :disabled="readonly"
                                :class="inert"
                                placeholder="e.g. 250-500mcg"
                                autocomplete="off"
                            />
                            <InputError :message="errors.dosage" />
                        </div>

                        <div class="grid gap-2">
                            <Label for="frequency">
                                Frequency
                                <span class="font-normal text-muted-foreground">
                                    (Optional)
                                </span>
                            </Label>
                            <Input
                                id="frequency"
                                v-model="fields.frequency"
                                :disabled="readonly"
                                :class="inert"
                                placeholder="e.g. Once daily"
                                autocomplete="off"
                            />
                            <InputError :message="errors.frequency" />
                        </div>

                        <div class="grid gap-2">
                            <Label for="duration">
                                Duration
                                <span class="font-normal text-muted-foreground">
                                    (Optional)
                                </span>
                            </Label>
                            <Input
                                id="duration"
                                v-model="fields.duration"
                                :disabled="readonly"
                                :class="inert"
                                placeholder="e.g. 4-6 weeks"
                                autocomplete="off"
                            />
                            <InputError :message="errors.duration" />
                        </div>
                    </div>

                    <div class="grid gap-2">
                        <Label>
                            Protocol Notes
                            <span class="font-normal text-muted-foreground">
                                (Optional)
                            </span>
                        </Label>

                        <TransitionGroup
                            tag="div"
                            class="grid gap-2"
                            enter-active-class="transition-[opacity,transform] duration-200 ease-out motion-reduce:transition-opacity"
                            enter-from-class="-translate-y-1 opacity-0 motion-reduce:translate-y-0"
                            leave-active-class="transition-[opacity,transform] duration-150 ease-out motion-reduce:transition-opacity"
                            leave-to-class="-translate-y-1 opacity-0 motion-reduce:translate-y-0"
                        >
                            <div
                                v-for="(note, index) in fields.protocol_notes"
                                :key="note.id"
                                class="flex items-center gap-2"
                            >
                                <Input
                                    :id="`protocol-note-${index}`"
                                    :model-value="note.value"
                                    :disabled="readonly"
                                    :class="['h-9 flex-1', inert]"
                                    placeholder="e.g. Rotate injection sites."
                                    autocomplete="off"
                                    @update:model-value="
                                        (next) => {
                                            fields.protocol_notes[index].value =
                                                String(next);
                                        }
                                    "
                                />
                                <Button
                                    v-if="!readonly"
                                    type="button"
                                    variant="ghost"
                                    size="icon-sm"
                                    class="shrink-0 hover:text-destructive"
                                    @click="removeNote(index)"
                                >
                                    <X class="size-4" />
                                    <span class="sr-only">
                                        Remove note {{ index + 1 }}
                                    </span>
                                </Button>
                            </div>
                        </TransitionGroup>

                        <p
                            v-if="!fields.protocol_notes.length"
                            class="text-xs text-muted-foreground"
                        >
                            Nothing listed yet.
                        </p>

                        <Button
                            v-if="!readonly"
                            type="button"
                            variant="outline"
                            size="sm"
                            class="mt-1 w-fit"
                            @click="addNote"
                        >
                            <Plus />
                            Add Note
                        </Button>
                        <InputError :message="firstError('protocol')" />
                    </div>
                </CardContent>
            </Card>
        </div>

        <!-- RIGHT COLUMN -->
        <div :class="createStyle ? 'space-y-4' : 'space-y-6'">
            <Card
                :class="
                    createStyle
                        ? 'admin-product-image-wash gap-0 rounded-xl border-primary/15 py-0 shadow-sm shadow-sf-serenity-blue/10 dark:border-primary/25 dark:shadow-none'
                        : 'border-transparent shadow-sm shadow-black/5'
                "
            >
                <CardHeader :class="createStyle ? 'px-5 pt-5 pb-4' : ''">
                    <div class="flex items-center">
                        <CardTitle class="text-base">
                            {{
                                createStyle
                                    ? 'Upload Product Image'
                                    : 'Product Images'
                            }}
                        </CardTitle>
                    </div>
                </CardHeader>
                <CardContent :class="createStyle ? 'px-5 pb-5' : ''">
                    <ImageUpload
                        v-model="fields.images"
                        :readonly="readonly"
                        :blue-outline="createStyle"
                    />
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
