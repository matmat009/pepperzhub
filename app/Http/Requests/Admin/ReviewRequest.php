<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class ReviewRequest extends FormRequest
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
            /*
             * Optional by design. A review may be about the shop rather than
             * any one product, and one whose product is later deleted comes
             * back through this form untagged — so a required rule here would
             * make an existing row unsaveable.
             */
            'product_id' => ['nullable', 'integer', 'exists:products,id'],
            'customer_name' => ['nullable', 'string', 'max:255'],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['required', 'string'],

            // Same disk, mimes and ceiling as the payment method QR code; see
            // syncImage for the replace/remove handling.
            'image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            'remove_image' => ['boolean'],

            'is_active' => ['boolean'],
            'sort_order' => ['required', 'integer', 'min:0'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'title.required' => 'Give the review a short title.',
            'description.required' => 'Add the review text.',
            'product_id.exists' => 'That product no longer exists.',
            'image.image' => 'The review photo must be an image.',
            'image.max' => 'Keep the review photo under 5MB.',
        ];
    }

    /**
     * A blank product select arrives as an empty string, which `nullable` alone
     * would not turn into null — so it is normalised here rather than becoming
     * a spurious `exists` failure on a review that simply has no product.
     */
    protected function prepareForValidation(): void
    {
        $this->merge([
            'product_id' => blank($this->input('product_id'))
                ? null
                : (int) $this->input('product_id'),
            'customer_name' => blank($this->input('customer_name'))
                ? null
                : trim((string) $this->input('customer_name')),
            'is_active' => $this->boolean('is_active'),
            'remove_image' => $this->boolean('remove_image'),
            'sort_order' => $this->input('sort_order', 0),
        ]);
    }
}
