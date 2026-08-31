<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class PaymentMethodRequest extends FormRequest
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

            /*
             * Free-form label/value pairs rather than fixed columns: a bank
             * needs three rows, GCash one. At least one pair must survive
             * prepareForValidation, so a method always shows the customer
             * something to pay to.
             */
            'details' => ['required', 'array', 'min:1'],
            'details.*.label' => ['required', 'string', 'max:255'],
            'details.*.value' => ['required', 'string', 'max:255'],

            // Same disk and mimes as product images; see syncQrCode for the
            // replace/remove handling.
            'qr_code' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            'remove_qr_code' => ['boolean'],

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
            'details.required' => 'Add at least one payment detail, such as an account number.',
            'details.min' => 'Add at least one payment detail, such as an account number.',
            'details.*.label.required' => 'Every payment detail needs a label.',
            'details.*.value.required' => 'Every payment detail needs a value.',
            'qr_code.image' => 'The QR code must be an image.',
            'qr_code.max' => 'Keep the QR code under 5MB.',
        ];
    }

    /**
     * The repeater renders at least one row, so a method being created arrives
     * with a blank pair. Drop fully-empty rows the same way ProductRequest does
     * for purity and storage, rather than making the user delete them by hand.
     *
     * A half-filled row is deliberately kept so the rules can report it.
     */
    protected function prepareForValidation(): void
    {
        $details = collect($this->input('details'))
            ->filter(fn ($row): bool => is_array($row))
            ->reject(fn (array $row): bool => blank($row['label'] ?? null) && blank($row['value'] ?? null))
            ->values()
            ->all();

        $this->merge([
            'details' => $details,
            'is_active' => $this->boolean('is_active'),
            'remove_qr_code' => $this->boolean('remove_qr_code'),
            'sort_order' => $this->input('sort_order', 0),
        ]);
    }
}
