<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Inertia\Testing\AssertableInertia as Assert;
use Laravel\Fortify\Features;
use PragmaRX\Google2FA\Google2FA;
use Tests\TestCase;

class TwoFactorChallengeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->skipUnlessFortifyHas(Features::twoFactorAuthentication());
    }

    public function test_two_factor_challenge_redirects_to_login_when_not_authenticated(): void
    {
        $response = $this->get(route('two-factor.login'));

        $response->assertRedirect(route('login'));
    }

    public function test_two_factor_challenge_can_be_rendered(): void
    {
        Features::twoFactorAuthentication([
            'confirm' => true,
            'confirmPassword' => true,
        ]);

        $user = User::factory()->withTwoFactor()->create();

        $this->post(route('login'), [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $this->get(route('two-factor.login'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('auth/TwoFactorChallenge'),
            );
    }

    public function test_valid_authenticator_code_completes_login_and_regenerates_the_session(): void
    {
        $secret = app(Google2FA::class)->generateSecretKey();
        $user = $this->createTwoFactorUser($secret);

        $this->post(route('login.store'), [
            'email' => $user->email,
            'password' => 'password',
        ])->assertRedirect(route('two-factor.login'));
        $challengeSessionId = session()->getId();

        $response = $this->post(route('two-factor.login.store'), [
            'code' => app(Google2FA::class)->getCurrentOtp($secret),
        ]);

        $response->assertRedirect(route('dashboard', absolute: false));
        $this->assertAuthenticatedAs($user);
        $this->assertNotSame($challengeSessionId, session()->getId());
    }

    public function test_invalid_authenticator_code_is_rejected_without_changing_two_factor_state(): void
    {
        $secret = app(Google2FA::class)->generateSecretKey();
        $user = $this->createTwoFactorUser($secret);
        $preservedSecret = $user->getRawOriginal('two_factor_secret');
        $preservedRecoveryCodes = $user->getRawOriginal('two_factor_recovery_codes');

        $this->post(route('login.store'), [
            'email' => $user->email,
            'password' => 'password',
        ])->assertRedirect(route('two-factor.login'));

        $this->from(route('two-factor.login'))
            ->post(route('two-factor.login.store'), ['code' => 'invalid-code'])
            ->assertSessionHasErrors('code')
            ->assertRedirect(route('two-factor.login'));

        $this->assertGuest();
        $user->refresh();
        $this->assertSame($preservedSecret, $user->getRawOriginal('two_factor_secret'));
        $this->assertSame($preservedRecoveryCodes, $user->getRawOriginal('two_factor_recovery_codes'));
    }

    public function test_existing_recovery_code_completes_login_and_cannot_be_reused(): void
    {
        $user = $this->createTwoFactorUser(
            app(Google2FA::class)->generateSecretKey(),
            ['existing-recovery-code', 'second-recovery-code'],
        );

        $this->post(route('login.store'), [
            'email' => $user->email,
            'password' => 'password',
        ])->assertRedirect(route('two-factor.login'));

        $this->post(route('two-factor.login.store'), [
            'recovery_code' => 'existing-recovery-code',
        ])->assertRedirect(route('dashboard', absolute: false));

        $this->assertAuthenticatedAs($user);
        $this->assertNotContains('existing-recovery-code', $user->fresh()->recoveryCodes());
        $this->assertContains('second-recovery-code', $user->recoveryCodes());

        $this->post(route('logout'));
        $this->assertGuest();

        $this->post(route('login.store'), [
            'email' => $user->email,
            'password' => 'password',
        ])->assertRedirect(route('two-factor.login'));

        $this->from(route('two-factor.login'))
            ->post(route('two-factor.login.store'), [
                'recovery_code' => 'existing-recovery-code',
            ])
            ->assertSessionHasErrors('recovery_code')
            ->assertRedirect(route('two-factor.login'));

        $this->assertGuest();
    }

    /** @param list<string> $recoveryCodes */
    private function createTwoFactorUser(
        string $secret,
        array $recoveryCodes = ['recovery-code-1'],
    ): User {
        return User::factory()->create([
            'password' => Hash::make('password'),
            'two_factor_secret' => encrypt($secret),
            'two_factor_recovery_codes' => encrypt(json_encode($recoveryCodes)),
            'two_factor_confirmed_at' => now(),
        ]);
    }
}
