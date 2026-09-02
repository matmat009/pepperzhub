<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

/**
 * The default seeder is deliberately limited to disposable development data.
 *
 * Payment methods, couriers, and regions become admin-managed after setup. Their
 * seeders identify rows by mutable names, so they live behind the explicit
 * ReferenceDataBootstrapSeeder entry point instead of this routine path.
 *
 * No user is seeded. Accounts are provisioned by hand; see DEPLOYMENT.md.
 */
class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        if (! app()->isProduction()) {
            $this->call(CatalogSeeder::class);
        }
    }
}
