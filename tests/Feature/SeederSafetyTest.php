<?php

namespace Tests\Feature;

use App\Models\PaymentMethod;
use App\Models\Product;
use App\Models\ShippingCourier;
use App\Models\User;
use Database\Seeders\CatalogSeeder;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\ReferenceDataBootstrapSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Routine deployment seeding must not own admin-managed reference data.
 *
 * Two properties, both of which were previously untrue:
 *
 *  - It must not create a user. DatabaseSeeder used to make a User::factory()
 *    account on test@example.com carrying the factory's shared default
 *    password — a second admin with a publicly known password, created by a
 *    command whose whole purpose is to be run after deploying.
 *  - It must not populate the storefront with fake products or recreate
 *    reference data that an admin renamed or deliberately deleted.
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

    /** Checkout requires these on a fresh database, so bootstrap is explicit. */
    public function test_reference_data_is_seeded_by_the_bootstrap_entry_point(): void
    {
        $this->seed(ReferenceDataBootstrapSeeder::class);

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
     * Flips the environment to production and resolves DatabaseSeeder directly,
     * matching what `db:seed --force` invokes without its confirmation prompt.
     */
    public function test_routine_production_seeding_does_not_restore_admin_managed_reference_data(): void
    {
        $this->seed(ReferenceDataBootstrapSeeder::class);

        $method = PaymentMethod::where('name', 'GOtyme Bank')->sole();
        $method->update(['name' => 'Operator Bank']);

        $courier = ShippingCourier::where('name', 'J&T Express')->sole();
        $courier->update(['name' => 'Operator Courier']);
        $courier->regions()->where('name', 'Luzon & Visayas')->sole()->delete();

        app()['env'] = 'production';

        $this->assertTrue(app()->isProduction(), 'could not simulate production');

        $this->app->make(DatabaseSeeder::class)->setContainer($this->app)->run();

        $this->assertSame(0, Product::count(), 'demo products were seeded into production');
        $this->assertSame(0, User::count());
        $this->assertSame(1, PaymentMethod::count());
        $this->assertSame(1, ShippingCourier::count());
        $this->assertDatabaseHas('payment_methods', ['name' => 'Operator Bank']);
        $this->assertDatabaseMissing('payment_methods', ['name' => 'GOtyme Bank']);
        $this->assertDatabaseHas('shipping_couriers', ['name' => 'Operator Courier']);
        $this->assertDatabaseMissing('shipping_couriers', ['name' => 'J&T Express']);
        $this->assertDatabaseMissing('shipping_regions', ['name' => 'Luzon & Visayas']);
    }

    /**
     * The regression this whole split exists to prevent, end to end.
     *
     * Distinct from the production test above in the two ways that make it
     * capable of failing:
     *
     *  - The rename goes through the real admin route — an authenticated PUT to
     *    admin.payment-methods.update — so it exercises PaymentMethodRequest
     *    validation and the controller's attribute rebuild, which is what an
     *    operator actually does. A bare $method->update() skips both.
     *  - It runs the plain `db:seed --force` command in the default
     *    environment, where DatabaseSeeder genuinely executes. In production
     *    that method body is empty, so a production-only assertion cannot
     *    distinguish "the reference seeders are correctly excluded" from
     *    "nothing ran at all".
     *
     * Verified to fail when the reference seeders are put back into
     * DatabaseSeeder's non-production branch; the whole suite passes with that
     * regression present without it.
     */
    public function test_a_renamed_payment_method_survives_a_routine_reseed(): void
    {
        $this->seed(ReferenceDataBootstrapSeeder::class);

        $method = PaymentMethod::where('name', 'GOtyme Bank')->sole();
        $originalId = $method->id;

        $this->actingAs(User::factory()->create(['email_verified_at' => now()]));

        // The operator's actual workflow: rename it in Admin.
        $this->put(route('admin.payment-methods.update', $method), [
            'name' => 'Operator Bank',
            'details' => [
                ['label' => 'Bank', 'value' => 'Operator Bank'],
                ['label' => 'Account Number', 'value' => '9988 7766 5544'],
            ],
            'is_active' => true,
            'sort_order' => 0,
        ])->assertRedirect()->assertSessionHasNoErrors();

        $this->assertSame('Operator Bank', $method->fresh()->name, 'the admin rename did not apply');

        // The routine command, exactly as a deploy script would run it.
        $this->artisan('db:seed', ['--force' => true])->assertSuccessful();

        $this->assertSame(
            1,
            PaymentMethod::count(),
            'reseeding recreated a payment method the admin had renamed',
        );

        $survivor = PaymentMethod::sole();

        $this->assertSame($originalId, $survivor->id, 'the row was replaced rather than left alone');
        $this->assertSame('Operator Bank', $survivor->name, 'the rename was reverted by reseeding');
        $this->assertDatabaseMissing('payment_methods', ['name' => 'GOtyme Bank']);

        // The admin's edited details survived too, not just the name.
        $this->assertSame('9988 7766 5544', $survivor->details[1]['value']);
    }

    /**
     * The same guarantee for couriers and their delivery options.
     *
     * A deleted region is the harsher case: recreating it puts a shipping
     * option the operator withdrew back in front of customers at checkout.
     */
    public function test_a_renamed_courier_and_deleted_region_survive_a_routine_reseed(): void
    {
        $this->seed(ReferenceDataBootstrapSeeder::class);

        $courier = ShippingCourier::where('name', 'J&T Express')->sole();
        $originalId = $courier->id;
        $kept = $courier->regions()->where('name', 'Mindanao (Small)')->sole();

        $this->actingAs(User::factory()->create(['email_verified_at' => now()]));

        // Rename the courier and drop one delivery option, through Admin.
        $this->put(route('admin.shipping-couriers.update', $courier), [
            'name' => 'Operator Courier',
            'is_active' => true,
            'sort_order' => 0,
            'regions' => [[
                'id' => $kept->id,
                'name' => $kept->name,
                'note' => $kept->note,
                'rate' => (float) $kept->rate,
                'is_active' => true,
            ]],
        ])->assertRedirect()->assertSessionHasNoErrors();

        $this->assertSame('Operator Courier', $courier->fresh()->name, 'the admin rename did not apply');
        $this->assertSame(1, $courier->fresh()->regions()->count(), 'the region removal did not apply');

        $this->artisan('db:seed', ['--force' => true])->assertSuccessful();

        $this->assertSame(1, ShippingCourier::count(), 'reseeding recreated a courier');
        $this->assertSame($originalId, ShippingCourier::sole()->id);
        $this->assertDatabaseHas('shipping_couriers', ['name' => 'Operator Courier']);
        $this->assertDatabaseMissing('shipping_couriers', ['name' => 'J&T Express']);

        $this->assertSame(
            1,
            ShippingCourier::sole()->regions()->count(),
            'reseeding restored delivery options the admin had removed',
        );
        $this->assertDatabaseMissing('shipping_regions', ['name' => 'Luzon & Visayas']);
    }

    /**
     * An immediate bootstrap retry must not duplicate unchanged reference rows.
     *
     * This does not make the bootstrap safe after admin edits: mutable lookup
     * names are why it is excluded from DatabaseSeeder.
     */
    public function test_the_reference_bootstrap_does_not_duplicate_unchanged_rows(): void
    {
        $seed = fn () => $this->seed(ReferenceDataBootstrapSeeder::class);

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
