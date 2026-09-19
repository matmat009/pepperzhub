<?php

namespace Tests\Feature\Auth;

use App\Auth\LoginThrottle;
use App\Console\Commands\ClearAdminLoginThrottleCommand;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Tests\TestCase;

class ClearAdminLoginThrottleCommandTest extends TestCase
{
    use RefreshDatabase;

    private const IP = '192.0.2.45';

    public function test_command_has_no_arguments_or_options(): void
    {
        $command = $this->app->make(ClearAdminLoginThrottleCommand::class);

        $this->assertSame('admin:clear-login-throttle', $command->getName());
        $this->assertSame([], $command->getDefinition()->getArguments());
        $this->assertSame([], $command->getDefinition()->getOptions());
    }

    public function test_command_requires_a_valid_exact_existing_email(): void
    {
        User::factory()->create(['email' => 'admin@example.com']);

        $this->artisan('admin:clear-login-throttle')
            ->expectsQuestion('Exact admin email', 'not-an-email')
            ->expectsOutput('A valid email address is required.')
            ->assertFailed();

        $this->artisan('admin:clear-login-throttle')
            ->expectsQuestion('Exact admin email', 'ADMIN@example.com')
            ->expectsOutput('No matching admin account was found.')
            ->assertFailed();
    }

    public function test_command_rejects_an_invalid_ip_without_clearing_the_throttle(): void
    {
        $user = User::factory()->create();
        $key = $this->seedPrimary($user->email, self::IP);

        $this->artisan('admin:clear-login-throttle')
            ->expectsQuestion('Exact admin email', $user->email)
            ->expectsQuestion('Affected client IP address', 'not-an-ip')
            ->expectsOutput('A valid IP address is required.')
            ->assertFailed();

        $this->assertSame(1, RateLimiter::attempts($key));
    }

    public function test_cancelling_confirmation_makes_no_changes(): void
    {
        $user = User::factory()->create();
        $key = $this->seedPrimary($user->email, self::IP);

        $this->artisan('admin:clear-login-throttle')
            ->expectsQuestion('Exact admin email', $user->email)
            ->expectsQuestion('Affected client IP address', self::IP)
            ->expectsConfirmation('Clear only this administrator login throttle?', 'no')
            ->expectsOutput('Login throttle reset cancelled.')
            ->assertFailed();

        $this->assertSame(1, RateLimiter::attempts($key));
    }

    public function test_command_clears_only_the_matching_primary_and_ip_counters(): void
    {
        $user = User::factory()->withTwoFactor()->create();
        $throttle = app(LoginThrottle::class);
        $targetPrimary = $this->seedPrimary($user->email, self::IP);
        $targetIp = $this->seedIp(self::IP);
        $unrelatedPrimary = $this->seedPrimary('other@example.com', self::IP);
        $unrelatedIp = $this->seedIp('192.0.2.99');
        Cache::put('unrelated-application-cache-entry', 'preserved', 3600);
        session(['preserved-session-value' => Str::random(20)]);
        $sessionValue = session('preserved-session-value');
        $originalPassword = $user->password;
        $originalTwoFactor = [
            $user->getRawOriginal('two_factor_secret'),
            $user->getRawOriginal('two_factor_recovery_codes'),
            $user->getRawOriginal('two_factor_confirmed_at'),
        ];

        $this->artisan('admin:clear-login-throttle')
            ->expectsQuestion('Exact admin email', $user->email)
            ->expectsQuestion('Affected client IP address', self::IP)
            ->expectsConfirmation('Clear only this administrator login throttle?', 'yes')
            ->expectsOutput('Login throttle cleared. Normal authentication is still required.')
            ->assertSuccessful();

        $this->assertSame(0, RateLimiter::attempts($targetPrimary));
        $this->assertSame(0, RateLimiter::attempts($targetIp));
        $this->assertSame(1, RateLimiter::attempts($unrelatedPrimary));
        $this->assertSame(1, RateLimiter::attempts($unrelatedIp));
        $this->assertSame('preserved', Cache::get('unrelated-application-cache-entry'));
        $this->assertSame($sessionValue, session('preserved-session-value'));
        $this->assertGuest();

        $user->refresh();
        $this->assertSame($originalPassword, $user->password);
        $this->assertSame($originalTwoFactor, [
            $user->getRawOriginal('two_factor_secret'),
            $user->getRawOriginal('two_factor_recovery_codes'),
            $user->getRawOriginal('two_factor_confirmed_at'),
        ]);
        $this->assertSame(
            hash('sha256', $throttle->normalizeEmail($user->email)),
            $throttle->emailIdentifier($user->email),
        );
    }

    public function test_no_matching_throttle_returns_safe_information_and_preserves_other_cache(): void
    {
        $user = User::factory()->create();
        Cache::put('unrelated-application-cache-entry', 'preserved', 3600);

        $this->artisan('admin:clear-login-throttle')
            ->expectsQuestion('Exact admin email', $user->email)
            ->expectsQuestion('Affected client IP address', self::IP)
            ->expectsConfirmation('Clear only this administrator login throttle?', 'yes')
            ->expectsOutput('No matching login throttle was active.')
            ->assertSuccessful();

        $this->assertSame('preserved', Cache::get('unrelated-application-cache-entry'));
    }

    public function test_clearing_a_throttle_does_not_bypass_password_or_two_factor_authentication(): void
    {
        $user = User::factory()->withTwoFactor()->create();
        $this->seedPrimary($user->email, '127.0.0.1', LoginThrottle::PRIMARY_MAX_ATTEMPTS);
        $this->seedIp('127.0.0.1');

        $this->artisan('admin:clear-login-throttle')
            ->expectsQuestion('Exact admin email', $user->email)
            ->expectsQuestion('Affected client IP address', '127.0.0.1')
            ->expectsConfirmation('Clear only this administrator login throttle?', 'yes')
            ->assertSuccessful();

        $this->post(route('login.store'), [
            'email' => $user->email,
            'password' => 'still-wrong',
        ])->assertSessionHasErrors(['email' => 'The provided credentials are incorrect.']);
        $this->assertGuest();

        $this->post(route('login.store'), [
            'email' => $user->email,
            'password' => 'password',
        ])->assertRedirect(route('two-factor.login'));
        $this->assertGuest();
    }

    public function test_reset_log_omits_credentials_full_ip_and_internal_keys(): void
    {
        Log::spy();
        $user = User::factory()->create(['email' => 'operator@example.com']);
        $this->seedPrimary($user->email, self::IP);

        $this->artisan('admin:clear-login-throttle')
            ->expectsQuestion('Exact admin email', $user->email)
            ->expectsQuestion('Affected client IP address', self::IP)
            ->expectsConfirmation('Clear only this administrator login throttle?', 'yes')
            ->assertSuccessful();

        Log::shouldHaveReceived('notice')->once()->withArgs(
            function (string $message, array $context) use ($user): bool {
                $serialized = $message.json_encode($context, JSON_THROW_ON_ERROR);

                return $message === 'Developer login throttle reset completed.'
                    && $context['user_id'] === $user->id
                    && $context['email_hash'] === hash('sha256', $user->email)
                    && $context['ip_network'] === '192.0.2.0/24'
                    && ! str_contains($serialized, $user->email)
                    && ! str_contains($serialized, self::IP)
                    && ! str_contains($serialized, 'login:password:');
            },
        );
    }

    private function seedPrimary(string $email, string $ip, int $attempts = 1): string
    {
        $key = app(LoginThrottle::class)->emailIpKey($email, $ip);
        RateLimiter::increment($key, LoginThrottle::PRIMARY_DECAY_SECONDS, $attempts);

        return $key;
    }

    private function seedIp(string $ip, int $attempts = 1): string
    {
        $key = app(LoginThrottle::class)->ipKey($ip);
        RateLimiter::increment($key, LoginThrottle::IP_DECAY_SECONDS, $attempts);

        return $key;
    }
}
