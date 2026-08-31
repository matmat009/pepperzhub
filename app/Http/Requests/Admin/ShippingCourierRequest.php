<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class ShippingCourierRequest extends FormRequest
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
            'is_active' => ['boolean'],
            'sort_order' => ['required', 'integer', 'min:0'],

            'regions' => ['array'],
            /*
             * Present for a row that already exists, null for one added in the
             * form. The controller re-checks it against this courier's own rows
             * — an id belonging to another courier is inserted as new rather
             * than hijacked, matching how ProductController treats variants.
             */
            'regions.*.id' => ['nullable', 'integer'],
            'regions.*.name' => ['required', 'string', 'max:255'],
            'regions.*.note' => ['nullable', 'string', 'max:255'],
            // Same precision as shipping_regions.rate and product_variants.price.
            'regions.*.rate' => ['required', 'numeric', 'min:0'],
            'regions.*.is_active' => ['boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'regions.*.name.required' => 'Every region needs a name.',
            'regions.*.rate.required' => 'Every region needs a rate.',
            'regions.*.rate.min' => 'Region rates cannot be negative.',
            'regions.*.rate.numeric' => 'Region rates must be a number.',
        ];
    }

    /**
     * Drop rows the user never filled in, so a blank starter row does not block
     * a courier that legitimately has no regions yet.
     */
    protected function prepareForValidation(): void
    {
        $regions = collect($this->input('regions'))
            ->filter(fn ($row): bool => is_array($row))
            ->reject(fn (array $row): bool => blank($row['name'] ?? null)
                && blank($row['note'] ?? null)
                && blank($row['rate'] ?? null))
            ->values()
            ->all();

        $this->merge([
            'regions' => $regions,
            'is_active' => $this->boolean('is_active'),
            'sort_order' => $this->input('sort_order', 0),
        ]);
    }
}
