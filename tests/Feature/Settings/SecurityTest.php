<?php

namespace Tests\Feature\Settings;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Laravel\Fortify\Features;
use Tests\TestCase;

class SecurityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'session.driver' => 'database',
            'session.connection' => config('database.default'),
            'session.table' => 'sessions',
        ]);
        $this->app['session']->forgetDrivers();
        $this->app->forgetInstance('session.store');
    }

    public function test_security_page_is_displayed()
    {
        $this->skipUnlessFortifyHas(Features::twoFactorAuthentication());

        Features::twoFactorAuthentication([
            'confirm' => true,
            'confirmPassword' => true,
        ]);
        $user = User::factory()->create();

        $this->actingAs($user)
            /* @chisel-password-confirmation */
            ->withSession(['auth.password_confirmed_at' => time()])
            /* @end-chisel-password-confirmation */
            ->get(route('security.edit'))
            ->assertInertia(fn (Assert $page) => $page
                ->component('settings/Security')
                ->missing('canManagePasskeys')
                ->missing('passkeys')
                ->where('twoFactorAvailable', true)
                ->where('canManageTwoFactor', false)
                ->where('twoFactorSettingsLocked', true)
                ->where('twoFactorStatus', 'disabled')
                ->where('twoFactorEnabled', false)
                ->missing('twoFactorSecret')
                ->missing('twoFactorRecoveryCodes')
                ->missing('twoFactorQrCode'),
            );
    }

    public function test_security_page_reports_enabled_and_pending_two_factor_without_exposing_secrets(): void
    {
        $this->skipUnlessFortifyHas(Features::twoFactorAuthentication());

        $enabled = User::factory()->withTwoFactor()->create();

        $this->actingAs($enabled)
            ->withSession(['auth.password_confirmed_at' => time()])
            ->get(route('security.edit'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('twoFactorStatus', 'enabled')
                ->where('twoFactorEnabled', true)
                ->missing('twoFactorSecret')
                ->missing('twoFactorRecoveryCodes')
                ->missing('twoFactorQrCode'));

        $pending = User::factory()->create([
            'two_factor_secret' => encrypt('pending-secret'),
            'two_factor_recovery_codes' => encrypt(json_encode(['pending-recovery-code'])),
            'two_factor_confirmed_at' => null,
        ]);
        $pendingState = [
            'two_factor_secret' => $pending->getRawOriginal('two_factor_secret'),
            'two_factor_recovery_codes' => $pending->getRawOriginal('two_factor_recovery_codes'),
            'two_factor_confirmed_at' => $pending->getRawOriginal('two_factor_confirmed_at'),
        ];

        $this->actingAs($pending)
            ->withSession(['auth.password_confirmed_at' => time()])
            ->get(route('security.edit'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('twoFactorStatus', 'pending')
                ->where('twoFactorEnabled', false)
                ->missing('twoFactorSecret')
                ->missing('twoFactorRecoveryCodes')
                ->missing('twoFactorQrCode'));

        $pending->refresh();
        $this->assertSame($pendingState, [
            'two_factor_secret' => $pending->getRawOriginal('two_factor_secret'),
            'two_factor_recovery_codes' => $pending->getRawOriginal('two_factor_recovery_codes'),
            'two_factor_confirmed_at' => $pending->getRawOriginal('two_factor_confirmed_at'),
        ]);
    }

    public function test_locked_two_factor_controls_are_visible_disabled_and_accessible(): void
    {
        $source = file_get_contents(resource_path('js/components/ManageTwoFactor.vue'));

        $this->assertStringContainsString('LockKeyhole', $source);
        $this->assertStringContainsString(
            'Two-factor authentication is locked and managed by the',
            $source,
        );
        $this->assertStringContainsString('authorized system maintainer.', $source);

        foreach ([
            'enable-two-factor-disabled',
            'confirm-two-factor-disabled',
            'restart-two-factor-disabled',
            'disable-two-factor-disabled',
            'view-recovery-codes-disabled',
            'regenerate-recovery-codes-disabled',
        ] as $control) {
            $this->assertStringContainsString("data-test=\"{$control}\"", $source);
        }

        $this->assertGreaterThanOrEqual(6, substr_count($source, 'aria-disabled="true"'));
        $this->assertGreaterThanOrEqual(6, substr_count($source, "\n                    disabled"));
    }

    public function test_security_page_does_not_require_password_confirmation(): void
    {
        $this->skipUnlessFortifyHas(Features::twoFactorAuthentication());

        $user = User::factory()->create();

        Features::twoFactorAuthentication([
            'confirm' => true,
            'confirmPassword' => true,
        ]);

        $response = $this->actingAs($user)
            ->get(route('security.edit'));

        $response
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('settings/Security'));
    }

    public function test_security_page_requires_authentication(): void
    {
        $this->get(route('security.edit'))
            ->assertRedirect(route('login'));
    }

    public function test_security_page_renders_without_two_factor_when_feature_is_disabled()
    {
        $this->skipUnlessFortifyHas(Features::twoFactorAuthentication());

        config(['fortify.features' => []]);

        $user = User::factory()->create();

        $this->actingAs($user)
            /* @chisel-password-confirmation */
            ->withSession(['auth.password_confirmed_at' => time()])
            /* @end-chisel-password-confirmation */
            ->get(route('security.edit'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('settings/Security')
                ->missing('canManagePasskeys')
                ->missing('passkeys')
                ->where('twoFactorAvailable', false)
                ->where('canManageTwoFactor', false)
                ->where('twoFactorSettingsLocked', false)
                ->missing('twoFactorEnabled')
                ->missing('twoFactorStatus')
                ->missing('requiresConfirmation'),
            );
    }

    public function test_password_can_be_updated_without_ending_the_current_session(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $passkeyId = DB::table('passkeys')->insertGetId([
            'user_id' => $user->id,
            'name' => 'Admin security key',
            'credential_id' => 'settings-password-change-credential',
            'credential' => json_encode(['public_key' => 'opaque-test-value'], JSON_THROW_ON_ERROR),
            'last_used_at' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $currentSession = $this->createDatabaseSessionThroughLogin($user);
        $user->forceFill([
            'two_factor_secret' => encrypt('secret'),
            'two_factor_recovery_codes' => encrypt(json_encode(['recovery-code-1'])),
            'two_factor_confirmed_at' => now(),
        ])->save();
        $user->refresh();
        $otherTargetSession = $this->cloneDatabaseSession($currentSession, $user);
        $otherUserSession = $this->cloneDatabaseSession($currentSession, $otherUser);
        $guestSession = $this->cloneDatabaseSession($currentSession, null);
        $originalRememberToken = $user->remember_token;
        $preserved = [
            'name' => $user->name,
            'email' => $user->email,
            'email_verified_at' => $user->getRawOriginal('email_verified_at'),
            'two_factor_secret' => $user->getRawOriginal('two_factor_secret'),
            'two_factor_recovery_codes' => $user->getRawOriginal('two_factor_recovery_codes'),
            'two_factor_confirmed_at' => $user->getRawOriginal('two_factor_confirmed_at'),
        ];

        $response = $this->usingSession($currentSession)
            ->from(route('security.edit'))
            ->put(route('user-password.update'), [
                'current_password' => 'password',
                'password' => 'new-password',
                'password_confirmation' => 'new-password',
            ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertSessionHas('inertia.flash_data.toast.message', 'Password updated.')
            ->assertRedirect(route('security.edit'));

        $newSession = $response->getCookie(config('session.cookie'))?->getValue();
        $this->assertIsString($newSession);
        $this->assertNotSame($currentSession, $newSession);

        $user->refresh();
        $this->assertFalse(Hash::check('password', $user->password));
        $this->assertTrue(Hash::check('new-password', $user->password));
        $this->assertNotSame($originalRememberToken, $user->remember_token);
        $this->assertSame($preserved, [
            'name' => $user->name,
            'email' => $user->email,
            'email_verified_at' => $user->getRawOriginal('email_verified_at'),
            'two_factor_secret' => $user->getRawOriginal('two_factor_secret'),
            'two_factor_recovery_codes' => $user->getRawOriginal('two_factor_recovery_codes'),
            'two_factor_confirmed_at' => $user->getRawOriginal('two_factor_confirmed_at'),
        ]);
        $this->assertDatabaseHas('passkeys', ['id' => $passkeyId, 'user_id' => $user->id]);

        $this->assertDatabaseMissing('sessions', ['id' => $currentSession]);
        $this->assertDatabaseMissing('sessions', ['id' => $otherTargetSession]);
        $this->assertDatabaseHas('sessions', ['id' => $newSession, 'user_id' => $user->id]);
        $this->assertDatabaseHas('sessions', ['id' => $otherUserSession, 'user_id' => $otherUser->id]);
        $this->assertDatabaseHas('sessions', ['id' => $guestSession, 'user_id' => null]);

        $this->usingSession($newSession)
            ->get(route('dashboard'))
            ->assertOk();

        $this->usingSession($otherTargetSession)
            ->get(route('dashboard'))
            ->assertRedirect(route('login'));

        $this->usingSession($otherUserSession)
            ->get(route('dashboard'))
            ->assertOk();

        $this->usingFreshGuestSession()
            ->from(route('login'))
            ->post(route('login.store'), ['email' => $user->email, 'password' => 'password'])
            ->assertRedirect(route('login'));
        $this->assertGuest();

        $this->usingFreshGuestSession()
            ->post(route('login.store'), ['email' => $user->email, 'password' => 'new-password'])
            ->assertRedirect(route('two-factor.login'))
            ->assertSessionHas('login.id', $user->id);
        $this->assertGuest();
    }

    public function test_correct_password_must_be_provided_to_update_password(): void
    {
        $user = User::factory()->create();
        $currentSession = $this->createDatabaseSessionThroughLogin($user);
        $otherSession = $this->cloneDatabaseSession($currentSession, $user);
        $originalPassword = $user->password;
        $originalRememberToken = $user->remember_token;

        $response = $this->usingSession($currentSession)
            ->from(route('security.edit'))
            ->put(route('user-password.update'), [
                'current_password' => 'wrong-password',
                'password' => 'new-password',
                'password_confirmation' => 'new-password',
            ]);

        $response
            ->assertRedirect(route('security.edit'));

        $user->refresh();
        $this->assertSame($originalPassword, $user->password);
        $this->assertSame($originalRememberToken, $user->remember_token);
        $this->assertDatabaseHas('sessions', ['id' => $currentSession, 'user_id' => $user->id]);
        $this->assertDatabaseHas('sessions', ['id' => $otherSession, 'user_id' => $user->id]);

        $this->usingSession($otherSession)
            ->get(route('dashboard'))
            ->assertOk();
    }

    public function test_password_confirmation_must_match_before_sessions_are_revoked(): void
    {
        $user = User::factory()->create();
        $currentSession = $this->createDatabaseSessionThroughLogin($user);
        $otherSession = $this->cloneDatabaseSession($currentSession, $user);
        $originalPassword = $user->password;

        $this->usingSession($currentSession)
            ->from(route('security.edit'))
            ->put(route('user-password.update'), [
                'current_password' => 'password',
                'password' => 'new-password',
                'password_confirmation' => 'different-password',
            ])
            ->assertRedirect(route('security.edit'));

        $this->assertSame($originalPassword, $user->fresh()->password);
        $this->assertDatabaseHas('sessions', ['id' => $currentSession, 'user_id' => $user->id]);
        $this->assertDatabaseHas('sessions', ['id' => $otherSession, 'user_id' => $user->id]);
    }

    public function test_password_validation_errors_are_returned_to_the_form(): void
    {
        config(['session.driver' => 'array']);
        $this->app['session']->forgetDrivers();
        $this->app->forgetInstance('session.store');
        $user = User::factory()->create();

        $this->actingAs($user)
            ->from(route('security.edit'))
            ->put(route('user-password.update'), [
                'current_password' => 'wrong-password',
                'password' => 'new-password',
                'password_confirmation' => 'new-password',
            ])
            ->assertSessionHasErrors('current_password')
            ->assertRedirect(route('security.edit'));

        $this->actingAs($user)
            ->from(route('security.edit'))
            ->put(route('user-password.update'), [
                'current_password' => 'password',
                'password' => 'new-password',
                'password_confirmation' => 'different-password',
            ])
            ->assertSessionHasErrors('password')
            ->assertRedirect(route('security.edit'));
    }

    private function createDatabaseSessionThroughLogin(User $user): string
    {
        $response = $this->usingFreshGuestSession()
            ->post(route('login.store'), ['email' => $user->email, 'password' => 'password'])
            ->assertRedirect(route('dashboard', absolute: false));

        $cookie = $response->getCookie(config('session.cookie'));
        $this->assertNotNull($cookie);
        $id = $cookie->getValue();
        $this->assertDatabaseHas('sessions', ['id' => $id, 'user_id' => $user->id]);

        return $id;
    }

    private function cloneDatabaseSession(string $sourceId, ?User $user): string
    {
        $connection = DB::connection(config('session.connection'));
        $source = $connection->table(config('session.table'))->where('id', $sourceId)->first();
        $this->assertNotNull($source);
        $payload = json_decode(base64_decode($source->payload), true, flags: JSON_THROW_ON_ERROR);
        $guardName = collect(array_keys($payload))
            ->first(fn (string $key): bool => str_starts_with($key, 'login_web_'));
        $this->assertIsString($guardName);

        if ($user) {
            $payload[$guardName] = $user->getAuthIdentifier();
        } else {
            unset($payload[$guardName]);
        }

        $id = Str::random(40);
        $connection->table(config('session.table'))->insert([
            'id' => $id,
            'user_id' => $user?->getAuthIdentifier(),
            'ip_address' => $source->ip_address,
            'user_agent' => $source->user_agent,
            'payload' => base64_encode(json_encode($payload, JSON_THROW_ON_ERROR)),
            'last_activity' => now()->getTimestamp(),
        ]);

        return $id;
    }

    private function usingSession(string $sessionId): static
    {
        if ($this->app->resolved('auth.driver')) {
            $this->app->make('auth.driver')->forgetUser();
        }

        Auth::guard('web')->forgetUser();
        Auth::forgetGuards();
        $this->app->forgetInstance('auth.driver');
        $this->app['session']->forgetDrivers();
        $this->app->forgetInstance('session.store');

        return $this->withCookie(config('session.cookie'), $sessionId);
    }

    private function usingFreshGuestSession(): static
    {
        return $this->usingSession(Str::random(40));
    }
}
