<?php

namespace Tests\Feature;

use App\Models\PaymentMethod;
use App\Models\Product;
use App\Models\ShippingCourier;
use App\Models\User;
use Database\Seeders\CatalogSeeder;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\PaymentMethodSeeder;
use Database\Seeders\ShippingCourierSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * `db:seed` has to be safe to point at a real database.
 *
 * Two properties, both of which were previously untrue:
 *
 *  - It must not create a user. DatabaseSeeder used to make a User::factory()
 *    account on test@example.com carrying the factory's shared default
 *    password — a second admin with a publicly known password, created by a
 *    command whose whole purpose is to be run after deploying.
 *  - It must not populate the storefront with fake products, while still
 *    seeding the reference data Checkout genuinely depends on. Those ran
 *    together, so there was no way to have one without the other.
 */
class SeederSafetyTest extends TestCase
{
    use RefreshDatabase;

    public function test_seeding_never_creates_a_user(): void
    {
        $this->seed(DatabaseSeeder::class);

        $this->assertSame(0, User::count(), 'the seeder created an account');
        $this->assertDatabaseMissing('users', ['email' => 'test@example.com']);
    }

    /** Checkout is broken without these, so they run everywhere. */
    public function test_reference_data_is_seeded(): void
    {
        $this->seed(DatabaseSeeder::class);

        $this->assertGreaterThan(0, PaymentMethod::count());
        $this->assertGreaterThan(0, ShippingCourier::count());
        $this->assertGreaterThan(0, ShippingCourier::first()->regions()->count());
    }

    public function test_the_demo_catalogue_is_seeded_outside_production(): void
    {
        // The suite runs as APP_ENV=testing, which is not production.
        $this->assertFalse(app()->isProduction(), 'test env assumption changed');

        $this->seed(DatabaseSeeder::class);

        $this->assertGreaterThan(0, Product::count());
    }

    /**
     * The gate itself.
     *
     * Flips the environment to production and re-runs the same command a
     * deployer would: reference data still lands, fake products do not.
     */
    public function test_the_demo_catalogue_is_skipped_in_production(): void
    {
        app()['env'] = 'production';

        $this->assertTrue(app()->isProduction(), 'could not simulate production');

        // Resolved directly rather than through `db:seed`: in production that
        // command stops for a confirmation prompt (ConfirmableTrait), which is
        // correct but is not the behaviour under test here. A real deploy
        // passes --force to get past it.
        $this->app->make(DatabaseSeeder::class)->setContainer($this->app)->run();

        $this->assertSame(0, Product::count(), 'demo products were seeded into production');
        $this->assertSame(0, User::count());

        // …but Checkout still has something to offer.
        $this->assertGreaterThan(0, PaymentMethod::count());
        $this->assertGreaterThan(0, ShippingCourier::count());
    }

    /**
     * Re-running a deploy must not duplicate reference rows.
     *
     * Scoped to the two reference seeders, because that is exactly what a
     * production `db:seed --force` runs. CatalogSeeder is deliberately not
     * covered: it is not idempotent — a second run fails on the
     * categories.slug unique index — which is fine for demo data seeded once
     * into a fresh dev database, and is another reason it does not belong in
     * the production path.
     */
    public function test_reference_seeders_are_idempotent(): void
    {
        $seed = fn () => $this->seed([PaymentMethodSeeder::class, ShippingCourierSeeder::class]);

        $seed();

        $methods = PaymentMethod::count();
        $couriers = ShippingCourier::count();
        $regions = ShippingCourier::first()->regions()->count();

        $seed();

        $this->assertSame($methods, PaymentMethod::count());
        $this->assertSame($couriers, ShippingCourier::count());
        $this->assertSame($regions, ShippingCourier::first()->regions()->count());
    }

    /**
     * Documents the limitation found while writing the test above, so it is a
     * known property rather than a surprise on someone's second `db:seed`.
     */
    public function test_the_demo_catalogue_is_not_re_runnable(): void
    {
        $this->seed(CatalogSeeder::class);

        $this->expectException(QueryException::class);

        $this->seed(CatalogSeeder::class);
    }
}
