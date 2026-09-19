<?php

namespace Tests\Feature\Auth;

use App\Auth\LoginThrottle;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Laravel\Fortify\Features;
use PragmaRX\Google2FA\Google2FA;
use Tests\TestCase;

class LoginThrottleTest extends TestCase
{
    use RefreshDatabase;

    private const GENERIC_ERROR = 'The provided credentials are incorrect.';

    private const THROTTLE_PREFIX = 'Too many sign-in attempts. Please try again in ';

    public function test_first_five_failures_are_ordinary_and_the_next_attempt_is_rate_limited(): void
    {
        $this->freezeSecond();
        $user = User::factory()->create();

        foreach (range(1, 4) as $_) {
            $this->invalidLogin($user->email)->assertSessionHasErrors([
                'email' => self::GENERIC_ERROR,
            ]);
        }

        $this->invalidLogin($user->email)->assertSessionHasErrors([
            'email' => self::GENERIC_ERROR,
        ]);

        $response = $this->post(route('login.store'), $this->credentials($user->email));

        $response->assertStatus(429)
            ->assertHeader('Retry-After', (string) LoginThrottle::PRIMARY_DECAY_SECONDS)
            ->assertSee(self::THROTTLE_PREFIX, false);
        $this->assertGuest();
    }

    public function test_primary_cooldown_expires_and_login_is_allowed_after_fifteen_minutes(): void
    {
        $user = User::factory()->create();

        foreach (range(1, 5) as $_) {
            $this->invalidLogin($user->email);
        }

        $this->post(route('login.store'), $this->credentials($user->email))->assertStatus(429);

        $this->travel(LoginThrottle::PRIMARY_DECAY_SECONDS)->seconds();

        $this->post(route('login.store'), [
            'email' => $user->email,
            'password' => 'password',
        ])->assertRedirect(route('dashboard', absolute: false));
        $this->assertAuthenticatedAs($user);
    }

    public function test_retry_after_reports_the_remaining_cooldown(): void
    {
        $this->freezeSecond();
        $email = 'unknown@example.com';

        foreach (range(1, 5) as $_) {
            $this->invalidLogin($email);
        }

        $this->travel(90)->seconds();

        $this->post(route('login.store'), $this->credentials($email))
            ->assertStatus(429)
            ->assertHeader(
                'Retry-After',
                (string) (LoginThrottle::PRIMARY_DECAY_SECONDS - 90),
            );
    }

    public function test_successful_password_authentication_clears_the_primary_counter(): void
    {
        $user = User::factory()->create();
        $throttle = app(LoginThrottle::class);

        foreach (range(1, 4) as $_) {
            $this->invalidLogin($user->email);
        }

        $this->post(route('login.store'), [
            'email' => $user->email,
            'password' => 'password',
        ])->assertRedirect(route('dashboard', absolute: false));

        $this->assertSame(0, RateLimiter::attempts(
            $throttle->emailIpKey($user->email, '127.0.0.1'),
        ));
    }

    public function test_unknown_and_existing_emails_are_counted_and_receive_the_same_error(): void
    {
        $user = User::factory()->create();

        $existing = $this->invalidLogin($user->email);
        $unknown = $this->invalidLogin('unknown@example.com');

        $existing->assertSessionHasErrors(['email' => self::GENERIC_ERROR]);
        $unknown->assertSessionHasErrors(['email' => self::GENERIC_ERROR]);

        $throttle = app(LoginThrottle::class);
        $this->assertSame(1, RateLimiter::attempts(
            $throttle->emailIpKey($user->email, '127.0.0.1'),
        ));
        $this->assertSame(1, RateLimiter::attempts(
            $throttle->emailIpKey('unknown@example.com', '127.0.0.1'),
        ));
    }

    public function test_email_normalization_prevents_case_and_whitespace_bypass(): void
    {
        $variants = [
            'ADMIN@EXAMPLE.COM',
            ' admin@example.com',
            'admin@example.com ',
            "\tAdmin@Example.com\n",
            'admin@example.com',
        ];

        foreach ($variants as $email) {
            $this->invalidLogin($email)->assertSessionHasErrors([
                'email' => self::GENERIC_ERROR,
            ]);
        }

        $this->post(route('login.store'), $this->credentials('admin@example.com'))
            ->assertStatus(429);
    }

    public function test_primary_counters_are_isolated_by_both_email_and_ip(): void
    {
        foreach (range(1, 5) as $_) {
            $this->fromIp('192.0.2.10')->invalidLogin('first@example.com');
        }

        $this->fromIp('192.0.2.10')
            ->invalidLogin('second@example.com')
            ->assertSessionHasErrors(['email' => self::GENERIC_ERROR]);
        $this->fromIp('192.0.2.11')
            ->invalidLogin('first@example.com')
            ->assertSessionHasErrors(['email' => self::GENERIC_ERROR]);

        $this->fromIp('192.0.2.10')
            ->post(route('login.store'), $this->credentials('first@example.com'))
            ->assertStatus(429);
    }

    public function test_ip_limit_blocks_email_rotation_and_expires_after_one_hour(): void
    {
        $this->freezeSecond();
        $ip = '192.0.2.20';

        foreach (range(1, LoginThrottle::IP_MAX_ATTEMPTS) as $attempt) {
            $this->fromIp($ip)
                ->invalidLogin("unknown-{$attempt}@example.com")
                ->assertSessionHasErrors(['email' => self::GENERIC_ERROR]);
        }

        $this->fromIp($ip)
            ->post(route('login.store'), $this->credentials('another@example.com'))
            ->assertStatus(429)
            ->assertHeader('Retry-After', (string) LoginThrottle::IP_DECAY_SECONDS);

        $this->travel(LoginThrottle::IP_DECAY_SECONDS)->seconds();

        $this->fromIp($ip)
            ->invalidLogin('another@example.com')
            ->assertSessionHasErrors(['email' => self::GENERIC_ERROR]);
    }

    public function test_inertia_and_json_throttle_responses_preserve_status_errors_and_retry_duration(): void
    {
        $email = 'unknown@example.com';

        foreach (range(1, 5) as $_) {
            $this->invalidLogin($email);
        }

        $inertia = $this->withHeader('X-Inertia', 'true')
            ->post(route('login.store'), $this->credentials($email));

        $inertia->assertStatus(429)
            ->assertHeader('X-Inertia', 'true')
            ->assertHeader('Retry-After')
            ->assertJsonPath('component', 'auth/Login')
            ->assertJsonPath('props.errors.email', fn (string $message): bool => str_starts_with(
                $message,
                self::THROTTLE_PREFIX,
            ));

        $json = $this->withoutHeader('X-Inertia')
            ->postJson(route('login.store'), $this->credentials($email));

        $json->assertStatus(429)
            ->assertHeader('Retry-After')
            ->assertJsonValidationErrors('email')
            ->assertJsonPath('message', fn (string $message): bool => str_starts_with(
                $message,
                self::THROTTLE_PREFIX,
            ));
    }

    public function test_throttle_security_log_contains_only_derived_email_and_minimized_ip(): void
    {
        Log::spy();
        $email = 'sensitive-admin@example.com';
        $password = 'never-log-this-password';

        foreach (range(1, 5) as $_) {
            $this->post(route('login.store'), compact('email', 'password'));
        }

        $this->post(route('login.store'), compact('email', 'password'))->assertStatus(429);

        Log::shouldHaveReceived('warning')->once()->withArgs(
            function (string $message, array $context) use ($email, $password): bool {
                $serialized = $message.json_encode($context, JSON_THROW_ON_ERROR);

                return $message === 'Temporary password-login throttle triggered.'
                    && $context['email_hash'] === hash('sha256', $email)
                    && $context['ip_network'] === '127.0.0.0/24'
                    && ! str_contains($serialized, $email)
                    && ! str_contains($serialized, $password)
                    && ! str_contains($serialized, 'login:password:');
            },
        );
    }

    public function test_valid_two_factor_password_clears_primary_and_failed_code_does_not_increment_it(): void
    {
        $this->skipUnlessFortifyHas(Features::twoFactorAuthentication());
        $user = User::factory()->create([
            'two_factor_secret' => encrypt(app(Google2FA::class)->generateSecretKey()),
            'two_factor_recovery_codes' => encrypt(json_encode(['recovery-code-1'])),
            'two_factor_confirmed_at' => now(),
        ]);
        $throttle = app(LoginThrottle::class);

        foreach (range(1, 4) as $_) {
            $this->invalidLogin($user->email);
        }

        $this->post(route('login.store'), [
            'email' => $user->email,
            'password' => 'password',
        ])->assertRedirect(route('two-factor.login'));

        $key = $throttle->emailIpKey($user->email, '127.0.0.1');
        $this->assertSame(0, RateLimiter::attempts($key));

        $this->post(route('two-factor.login.store'), ['code' => 'invalid-code'])
            ->assertSessionHasErrors('code');
        $this->assertSame(0, RateLimiter::attempts($key));
    }

    public function test_login_page_has_static_recovery_help_but_no_forgot_password_link(): void
    {
        $source = file_get_contents(resource_path('js/pages/auth/Login.vue'));

        $this->assertStringContainsString(
            'Forgotten your password? Contact the authorized system',
            $source,
        );
        $this->assertStringNotContainsString('Forgot password?', $source);
        $this->assertStringNotContainsString('password.request', $source);
    }

    private function invalidLogin(string $email)
    {
        return $this->post(route('login.store'), $this->credentials($email));
    }

    /** @return array{email: string, password: string} */
    private function credentials(string $email): array
    {
        return [
            'email' => $email,
            'password' => 'wrong-password',
        ];
    }

    private function fromIp(string $ip): static
    {
        return $this->withServerVariables(['REMOTE_ADDR' => $ip]);
    }
}
