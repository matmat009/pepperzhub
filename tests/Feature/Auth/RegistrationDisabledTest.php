<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Fortify\Features;
use Tests\TestCase;

/**
 * Public registration must stay closed.
 *
 * PepperzHub gates every admin route on ['auth', 'verified'] alone — no roles,
 * no policies, by design. An account that could register and verify its own
 * email would therefore be indistinguishable from the operator at the
 * middleware layer, with full access to orders and customers' payment-proof
 * screenshots. Disabling Fortify's registration feature unregisters the routes
 * outright, so both verbs 404 rather than merely redirecting.
 *
 * The counterpart RegistrationTest skips itself while the feature is off; this
 * one asserts the lock is actually on, so re-enabling the feature without a
 * real authorisation layer fails the suite instead of passing quietly.
 */
class RegistrationDisabledTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_registration_feature_is_disabled(): void
    {
        $this->assertFalse(Features::enabled(Features::registration()));
    }

    public function test_the_registration_screen_is_unreachable(): void
    {
        $this->get('/register')->assertNotFound();
    }

    public function test_posting_to_register_does_not_create_an_account(): void
    {
        $before = User::count();

        $this->post('/register', [
            'name' => 'Intruder',
            'email' => 'intruder@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ])->assertNotFound();

        $this->assertGuest();
        $this->assertSame($before, User::count());
        $this->assertDatabaseMissing('users', ['email' => 'intruder@example.com']);
    }

    public function test_no_named_register_route_exists(): void
    {
        // Wayfinder generates its helpers from the route list, so a route that
        // reappears here would also put a `register()` helper back in the
        // frontend bundle.
        $this->assertFalse(app('router')->has('register'));
        $this->assertFalse(app('router')->has('register.store'));
    }
}
