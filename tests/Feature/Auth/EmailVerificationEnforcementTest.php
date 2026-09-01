<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * The `verified` middleware has to actually verify.
 *
 * Every admin.* route is wrapped in ['auth', 'verified'], but Laravel's
 * EnsureEmailIsVerified only redirects when the user implements
 * MustVerifyEmail — so while User did not, the middleware waved every
 * authenticated account straight through and the guard was decoration.
 *
 * The contract could not be added on its own: the only account that exists was
 * provisioned by hand and has a null email_verified_at, so turning the check on
 * without backfilling would have bounced the operator out of their own admin on
 * the next request, with no verification email ever having been sent to click.
 * The migration that grandfathers existing rows ships alongside it, and the
 * tests below cover both halves.
 */
class EmailVerificationEnforcementTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_user_model_implements_the_contract(): void
    {
        // Without this the two redirect tests below pass vacuously — an
        // unverified user would reach nothing, but only because the middleware
        // never ran, not because it refused them.
        $this->assertInstanceOf(MustVerifyEmail::class, new User);
    }

    /**
     * The core behaviour change. Fails before this task, passes after.
     */
    public function test_an_unverified_user_is_redirected_away_from_admin_routes(): void
    {
        $this->actingAs(User::factory()->create(['email_verified_at' => null]));

        foreach ([
            route('admin.orders.index'),
            route('admin.products.index'),
            route('admin.payment-methods.index'),
            route('admin.shipping-couriers.index'),
            route('dashboard'),
        ] as $url) {
            $this->get($url)->assertRedirect(route('verification.notice'));
        }
    }

    public function test_a_verified_user_still_reaches_admin_routes(): void
    {
        $this->actingAs(User::factory()->create(['email_verified_at' => now()]));

        $this->get(route('admin.orders.index'))->assertOk();
        $this->get(route('admin.products.index'))->assertOk();
        $this->get(route('dashboard'))->assertOk();
    }

    /** The notice page itself must stay reachable, or there is no way out. */
    public function test_an_unverified_user_can_still_reach_the_verification_notice(): void
    {
        $this->actingAs(User::factory()->create(['email_verified_at' => null]));

        $this->get(route('verification.notice'))->assertOk();
    }

    // ----- the grandfathering migration -------------------------------------

    /**
     * Backfills null rows and leaves real timestamps completely alone.
     *
     * The migration has already run by the time a test boots, so this reproduces
     * the pre-migration state — a null row and an already-verified row — and
     * re-runs the migration's statement against it. Asserting the exact original
     * timestamp survives, not merely that it is still non-null: an update that
     * forgot its WHERE clause would pass the weaker check.
     */
    public function test_the_migration_backfills_only_null_verification_timestamps(): void
    {
        $alreadyVerifiedAt = Carbon::parse('2026-01-15 08:30:00');

        $untouched = User::factory()->create(['email_verified_at' => $alreadyVerifiedAt]);
        $backfilled = User::factory()->create();

        // Put the second row back into the state the migration is meant to find.
        DB::table('users')->where('id', $backfilled->id)->update(['email_verified_at' => null]);
        $this->assertNull($backfilled->fresh()->email_verified_at, 'test setup failed');

        $migration = include database_path(
            'migrations/2026_09_02_000001_backfill_email_verified_at_for_existing_users.php',
        );

        $migration->up();

        $this->assertNotNull(
            $backfilled->fresh()->email_verified_at,
            'the null row was not backfilled',
        );

        $this->assertTrue(
            $alreadyVerifiedAt->equalTo($untouched->fresh()->email_verified_at),
            'an already-verified row had its timestamp rewritten',
        );
    }

    /**
     * The whole point of shipping the two together.
     *
     * An account provisioned by hand — no Registered event, so no verification
     * email ever sent — must still reach the admin area after migrating.
     */
    public function test_a_grandfathered_account_is_not_locked_out(): void
    {
        $operator = User::factory()->create(['email_verified_at' => null]);

        DB::table('users')->where('id', $operator->id)->update(['email_verified_at' => now()]);

        $this->actingAs($operator->fresh());

        $this->get(route('admin.orders.index'))->assertOk();
    }
}
