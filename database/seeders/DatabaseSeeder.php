<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

/**
 * Two tiers, split by what it would mean to run them against real data.
 *
 * Reference data — payment methods and couriers — is not demo content. Checkout
 * reads both from the database, so an empty `payment_methods` table means a
 * customer reaches checkout with nothing to pay to. Those seeders run
 * everywhere, and both use firstOrCreate, so re-running them is a no-op rather
 * than a duplicate.
 *
 * The demo catalogue is fake products with fake images, and is gated behind a
 * non-production check. That gate is the conventional Laravel place for this —
 * one `db:seed` command that does the right thing per environment, instead of
 * asking whoever deploys to remember which subset is safe. Someone who
 * deliberately wants the demo catalogue in a production-flagged environment can
 * still run `php artisan db:seed --class=CatalogSeeder --force`; the gate stops
 * the accident, not the intent.
 *
 * No user is seeded. There used to be a User::factory() account on
 * test@example.com with the factory's shared default password — which, run
 * against a real database, would have created a second admin with a publicly
 * known password. Accounts are provisioned by hand; see DEPLOYMENT.md.
 */
class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        $this->call([
            PaymentMethodSeeder::class,
            ShippingCourierSeeder::class,
        ]);

        if (! app()->isProduction()) {
            $this->call(CatalogSeeder::class);
        }
    }
}
