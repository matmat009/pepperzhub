<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

/**
 * One-time bootstrap for the admin-managed choices Checkout requires.
 *
 * Run only against a fresh database. The child seeders match mutable names, so
 * repeating this after admin edits can recreate defaults that were renamed or
 * deliberately deleted.
 */
class ReferenceDataBootstrapSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        $this->call([
            PaymentMethodSeeder::class,
            ShippingCourierSeeder::class,
        ]);
    }
}
