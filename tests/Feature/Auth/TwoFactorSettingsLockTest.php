<?php

namespace Tests\Feature\Auth;

use App\Http\Middleware\EnsureTwoFactorSettingsAreUnlocked;
use App\Models\User;
use App\Support\SessionCart;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Laravel\Fortify\Features;
use Tests\TestCase;

class TwoFactorSettingsLockTest extends TestCase
{
    use RefreshDatabase;

    /** @var array<string, array{string, array<string, string>}> */
    private const MANAGEMENT_REQUESTS = [
        'two-factor.enable' => ['POST', ['force' => '1']],
        'two-factor.confirm' => ['POST', ['code' => '123456']],
        'two-factor.disable' => ['DELETE', []],
        'two-factor.qr-code' => ['GET', []],
        'two-factor.secret-key' => ['GET', []],
        'two-factor.recovery-codes' => ['GET', []],
        'two-factor.regenerate-recovery-codes' => ['POST', []],
    ];

    protected function setUp(): void
    {
        parent::setUp();

        $this->skipUnlessFortifyHas(Features::twoFactorAuthentication());
    }

    public function test_two_factor_settings_lock_is_secure_by_default_and_covers_every_management_route(): void
    {
        $this->assertTrue(config('fortify.two_factor_settings_locked'));
        $this->assertSame(
            array_keys(self::MANAGEMENT_REQUESTS),
            EnsureTwoFactorSettingsAreUnlocked::MANAGEMENT_ROUTES,
        );
    }

    public function test_authenticated_management_requests_are_forbidden_without_changing_or_exposing_state(): void
    {
        $user = User::factory()->withTwoFactor()->create([
            'name' => 'Sole Operator',
            'email' => 'operator@example.com',
        ]);
        $otherUser = User::factory()->create();
        $passkeyId = DB::table('passkeys')->insertGetId([
            'user_id' => $user->id,
            'name' => 'Existing passkey',
            'credential_id' => 'two-factor-lock-passkey',
            'credential' => json_encode(['public_key' => 'opaque-test-value'], JSON_THROW_ON_ERROR),
            'last_used_at' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $preserved = $this->preservedState($user);
        $otherUserState = $otherUser->getAttributes();
        $guestSession = Str::random(40);
        DB::table('sessions')->insert([
            'id' => $guestSession,
            'user_id' => null,
            'ip_address' => '127.0.0.1',
            'user_agent' => 'two-factor-lock-test',
            'payload' => base64_encode(json_encode([
                SessionCart::SESSION_KEY => [999 => 2],
            ], JSON_THROW_ON_ERROR)),
            'last_activity' => now()->getTimestamp(),
        ]);

        foreach (self::MANAGEMENT_REQUESTS as $routeName => [$method, $parameters]) {
            $response = $this
                ->actingAs($user)
                ->withSession(['auth.password_confirmed_at' => time()])
                ->json($method, route($routeName), $parameters);

            $response
                ->assertForbidden()
                ->assertJson(['message' => 'Two-factor authentication settings are locked.'])
                ->assertDontSee('secret')
                ->assertDontSee('recovery-code-1');

            $this->assertSame($preserved, $this->preservedState($user->fresh()));
        }

        $this->assertDatabaseHas('passkeys', ['id' => $passkeyId, 'user_id' => $user->id]);
        $this->assertEquals($otherUserState, $otherUser->fresh()->getAttributes());
        $this->assertDatabaseHas('sessions', ['id' => $guestSession, 'user_id' => null]);
    }

    public function test_unauthenticated_management_requests_still_use_fortify_authentication_middleware(): void
    {
        foreach (self::MANAGEMENT_REQUESTS as $routeName => [$method, $parameters]) {
            $this->call($method, route($routeName), $parameters)
                ->assertRedirect(route('login'));
        }
    }

    public function test_management_can_be_enabled_only_by_an_explicit_isolated_override(): void
    {
        config(['fortify.two_factor_settings_locked' => false]);
        $user = User::factory()->create();

        $this->actingAs($user)
            ->withSession(['auth.password_confirmed_at' => time()])
            ->postJson(route('two-factor.enable'))
            ->assertOk();

        $user->refresh();
        $this->assertNotNull($user->two_factor_secret);
        $this->assertNotNull($user->two_factor_recovery_codes);
        $this->assertNull($user->two_factor_confirmed_at);
    }

    /** @return array<string, mixed> */
    private function preservedState(User $user): array
    {
        return [
            'name' => $user->name,
            'email' => $user->email,
            'email_verified_at' => $user->getRawOriginal('email_verified_at'),
            'password' => $user->password,
            'remember_token' => $user->remember_token,
            'two_factor_secret' => $user->getRawOriginal('two_factor_secret'),
            'two_factor_recovery_codes' => $user->getRawOriginal('two_factor_recovery_codes'),
            'two_factor_confirmed_at' => $user->getRawOriginal('two_factor_confirmed_at'),
        ];
    }
}
