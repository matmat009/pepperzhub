<?php

namespace Database\Seeders;

use App\Models\PaymentMethod;
use Illuminate\Database\Seeder;

/**
 * The exact method Checkout.vue used to hardcode. Moving the source of truth,
 * not the content — nothing should look different to a customer.
 */
class PaymentMethodSeeder extends Seeder
{
    public function run(): void
    {
        PaymentMethod::firstOrCreate(
            ['name' => 'GOtyme Bank'],
            [
                'details' => [
                    ['label' => 'Bank', 'value' => 'GOtyme Bank'],
                    ['label' => 'Account Number', 'value' => '0012 3456 7890'],
                    ['label' => 'Account Name', 'value' => 'PepperzzHub Trading'],
                ],
                // No real QR asset yet; the checkout page renders a placeholder
                // when this is null.
                'qr_code_path' => null,
                'is_active' => true,
                'sort_order' => 0,
            ],
        );
    }
}
