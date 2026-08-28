<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'category_id' => ['required', 'integer', Rule::exists('categories', 'id')],
            'status' => ['required', Rule::in(['draft', 'active', 'archived'])],
            'featured' => ['boolean'],
            'short_description' => ['nullable', 'string', 'max:255'],
            'full_description' => ['nullable', 'string', 'max:5000'],

            'variants' => ['array'],
            // Present for a row that already exists; null for one added in the
            // form. The controller re-checks it against this product's rows.
            'variants.*.id' => ['nullable', 'integer'],
            'variants.*.label' => ['required', 'string', 'max:255'],
            'variants.*.price' => ['required', 'numeric', 'min:0'],
            'variants.*.stock' => ['required', 'integer', 'min:0'],
            'variants.*.is_kit' => ['boolean'],
            'variants.*.kit_inclusions' => ['array'],
            'variants.*.kit_inclusions.*' => ['string', 'max:255'],

            // Both repeater lists share a shape; empty rows are stripped in
            // prepareForValidation so a blank starter row never blocks a save.
            'purity' => ['array'],
            'purity.*.id' => ['nullable', 'integer'],
            'purity.*.label' => ['nullable', 'string', 'max:255'],
            'purity.*.value' => ['required', 'string', 'max:255'],

            'storage' => ['array'],
            'storage.*.id' => ['nullable', 'integer'],
            'storage.*.label' => ['nullable', 'string', 'max:255'],
            'storage.*.value' => ['required', 'string', 'max:255'],

            'kept_image_ids' => ['array'],
            'kept_image_ids.*' => ['integer'],
            'new_images' => ['array', 'max:10'],
            'new_images.*' => ['image', 'mimes:jpg,jpeg,png,webp,svg', 'max:5120'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'variants.*.label.required' => 'Every format needs a label.',
            'variants.*.price.min' => 'Format prices cannot be negative.',
            'variants.*.stock.min' => 'Format stock cannot be negative.',
            'purity.*.value.required' => 'Remove the empty purity row, or give it a value.',
            'storage.*.value.required' => 'Remove the empty storage row, or give it a value.',
            'new_images.*.max' => 'Each image must be 5MB or smaller.',
        ];
    }

    /**
     * The repeater lists always render at least one row, so a product with no
     * technical details arrives as a single blank pair. Drop those before the
     * rules run rather than forcing the user to delete them by hand.
     */
    protected function prepareForValidation(): void
    {
        $strip = static fn (mixed $rows): array => collect(is_array($rows) ? $rows : [])
            ->reject(fn ($row) => blank($row['label'] ?? null) && blank($row['value'] ?? null))
            ->values()
            ->all();

        $this->merge([
            'purity' => $strip($this->input('purity')),
            'storage' => $strip($this->input('storage')),
        ]);
    }
}
