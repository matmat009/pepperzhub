<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import type { CategoryFormFields } from '../types';

/**
 * Field markup shared by the create and edit modes of the category dialog.
 *
 * Values come through `defineModel` so the dialog owning them stays the single
 * source of truth (and so this component can write without tripping
 * `vue/no-mutating-props`).
 */
const fields = defineModel<CategoryFormFields>({ required: true });

withDefaults(
    defineProps<{
        errors?: Partial<Record<keyof CategoryFormFields, string>>;
    }>(),
    { errors: () => ({}) },
);
</script>

<template>
    <div class="grid gap-5">
        <div class="grid gap-2">
            <Label for="category-name">Name</Label>
            <Input
                id="category-name"
                v-model="fields.name"
                placeholder="e.g. Recovery"
                autocomplete="off"
            />
            <InputError :message="errors.name" />
        </div>

        <div class="grid gap-2">
            <Label for="category-description">Description</Label>
            <Textarea
                id="category-description"
                v-model="fields.description"
                rows="3"
                placeholder="What belongs in this category?"
            />
            <InputError :message="errors.description" />
        </div>
    </div>
</template>
