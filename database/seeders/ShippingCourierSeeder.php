<?php

namespace Database\Seeders;

use App\Models\ShippingCourier;
use Illuminate\Database\Seeder;

/**
 * The exact courier and rates Checkout.vue used to hardcode.
 */
class ShippingCourierSeeder extends Seeder
{
    public function run(): void
    {
        $courier = ShippingCourier::firstOrCreate(
            ['name' => 'J&T Express'],
            ['is_active' => true, 'sort_order' => 0],
        );

        $regions = [
            ['name' => 'Luzon & Visayas', 'note' => 'Standard pouch', 'rate' => 150],
            ['name' => 'Mindanao (Small)', 'note' => 'Max. 2 kits', 'rate' => 100],
            ['name' => 'Mindanao (Large Pouch)', 'note' => 'Min. 5 kits', 'rate' => 200],
        ];

        foreach ($regions as $index => $region) {
            $courier->regions()->firstOrCreate(
                ['name' => $region['name']],
                [
                    'note' => $region['note'],
                    'rate' => $region['rate'],
                    'is_active' => true,
                    'sort_order' => $index,
                ],
            );
        }
    }
}
