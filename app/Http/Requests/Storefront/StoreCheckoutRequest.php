<?php

namespace App\Http\Requests\Storefront;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCheckoutRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Note what is absent: no prices, no shipping fee, no totals. Those are all
     * recomputed server-side in the controller, so there is nothing here for a
     * client to tamper with.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'social_handle' => ['required', 'string', 'max:255'],
            'phone' => ['required', 'string', 'max:32'],

            'street' => ['required', 'string', 'max:255'],
            'barangay' => ['required', 'string', 'max:255'],
            'city' => ['required', 'string', 'max:255'],
            'province' => ['required', 'string', 'max:255'],
            'zip' => ['required', 'string', 'max:16'],
            'notes' => ['nullable', 'string', 'max:2000'],

            // Must be active, and must belong to a courier that is also active
            // — otherwise a retired region could still be posted directly.
            'shipping_region_id' => [
                'required',
                'integer',
                Rule::exists('shipping_regions', 'id')
                    ->where('is_active', true)
                    ->whereIn(
                        'shipping_courier_id',
                        fn ($query) => $query
                            ->select('id')
                            ->from('shipping_couriers')
                            ->where('is_active', true),
                    ),
            ],

            'payment_method_id' => [
                'required',
                'integer',
                Rule::exists('payment_methods', 'id')->where('is_active', true),
            ],

            // Screenshot of a bank transfer or GCash receipt. PDFs are allowed
            // because some banks only export receipts that way.
            'payment_proof' => ['required', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:5120'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'shipping_region_id.required' => 'Choose a shipping region.',
            'shipping_region_id.exists' => 'That shipping region is no longer available.',
            'payment_method_id.required' => 'Choose a payment method.',
            'payment_method_id.exists' => 'That payment method is no longer available.',
            'payment_proof.required' => 'Upload your proof of payment.',
            'payment_proof.mimes' => 'Upload a JPG, PNG or PDF receipt.',
            'payment_proof.max' => 'Keep the receipt under 5MB.',
        ];
    }
}
