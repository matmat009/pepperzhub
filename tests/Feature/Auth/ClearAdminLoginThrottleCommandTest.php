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

    private const FIRST_IP = '192.0.2.45';

    private const SECOND_IP = '198.51.100.72';

    public function test_command_has_no_arguments_options_or_ip_prompt(): void
    {
        $command = $this->app->make(ClearAdminLoginThrottleCommand::class);
        $source = file_get_contents(app_path('Console/Commands/ClearAdminLoginThrottleCommand.php'));

        $this->assertSame('admin:clear-login-throttle', $command->getName());
        $this->assertSame([], $command->getDefinition()->getArguments());
        $this->assertSame([], $command->getDefinition()->getOptions());
        $this->assertStringNotContainsString('Affected client IP address', $source);
        $this->assertStringNotContainsString('FILTER_VALIDATE_IP', $source);
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

    public function test_cancelling_confirmation_does_not_rotate_the_namespace(): void
    {
        $user = User::factory()->create();
        $throttle = app(LoginThrottle::class);
        $generation = $throttle->currentGeneration();
        $key = $this->seedPrimary($user->email, self::FIRST_IP);

        $this->artisan('admin:clear-login-throttle')
            ->expectsQuestion('Exact admin email', $user->email)
            ->expectsConfirmation('Clear all temporary login limits?', 'no')
            ->expectsOutput('Login throttle reset cancelled.')
            ->assertFailed();

        $this->assertSame($generation, $throttle->currentGeneration());
        $this->assertSame(1, RateLimiter::attempts($key));
    }

    public function test_command_rotates_all_primary_and_ip_buckets_without_deleting_old_entries(): void
    {
        $user = User::factory()->create();
        $throttle = app(LoginThrottle::class);
        $oldGeneration = $throttle->currentGeneration();
        $oldKeys = [
            $this->seedPrimary($user->email, self::FIRST_IP, LoginThrottle::PRIMARY_MAX_ATTEMPTS),
            $this->seedIp(self::FIRST_IP, LoginThrottle::IP_MAX_ATTEMPTS),
            $this->seedPrimary($user->email, self::SECOND_IP, LoginThrottle::PRIMARY_MAX_ATTEMPTS),
            $this->seedIp(self::SECOND_IP, LoginThrottle::IP_MAX_ATTEMPTS),
        ];

        $this->runSuccessfulCommand($user);

        $this->assertNotSame($oldGeneration, $throttle->currentGeneration());
        $this->assertSame(LoginThrottle::PRIMARY_MAX_ATTEMPTS, RateLimiter::attempts($oldKeys[0]));
        $this->assertSame(LoginThrottle::IP_MAX_ATTEMPTS, RateLimiter::attempts($oldKeys[1]));
        $this->assertSame(LoginThrottle::PRIMARY_MAX_ATTEMPTS, RateLimiter::attempts($oldKeys[2]));
        $this->assertSame(LoginThrottle::IP_MAX_ATTEMPTS, RateLimiter::attempts($oldKeys[3]));

        foreach ([self::FIRST_IP, self::SECOND_IP] as $ip) {
            $this->withServerVariables(['REMOTE_ADDR' => $ip])
                ->post(route('login.store'), [
                    'email' => $user->email,
                    'password' => 'wrong-password',
                ])
                ->assertSessionHasErrors([
                    'email' => 'The provided credentials are incorrect.',
                ]);

            $this->assertSame(1, RateLimiter::attempts($throttle->emailIpKey($user->email, $ip)));
            $this->assertSame(1, RateLimiter::attempts($throttle->ipKey($ip)));
        }
    }

    public function test_namespace_is_created_when_absent_and_new_failures_use_it(): void
    {
        $throttle = app(LoginThrottle::class);
        $generation = $throttle->currentGeneration();

        $this->assertNotSame('', $generation);
        $this->assertSame($generation, $throttle->currentGeneration());

        $key = $throttle->emailIpKey('unknown@example.com', self::FIRST_IP);

        $this->withServerVariables(['REMOTE_ADDR' => self::FIRST_IP])
            ->post(route('login.store'), [
                'email' => 'unknown@example.com',
                'password' => 'wrong-password',
            ])
            ->assertSessionHasErrors('email');

        $this->assertSame(1, RateLimiter::attempts($key));
    }

    public function test_command_preserves_unrelated_cache_sessions_and_administrator_data(): void
    {
        $user = User::factory()->withTwoFactor()->create();
        $this->seedPrimary($user->email, self::FIRST_IP);
        Cache::put('unrelated-application-cache-entry', 'preserved', 3600);
        session(['preserved-session-value' => Str::random(20)]);
        $sessionValue = session('preserved-session-value');
        $preservedUser = [
            'password' => $user->password,
            'email' => $user->email,
            'email_verified_at' => $user->getRawOriginal('email_verified_at'),
            'remember_token' => $user->remember_token,
            'two_factor_secret' => $user->getRawOriginal('two_factor_secret'),
            'two_factor_recovery_codes' => $user->getRawOriginal('two_factor_recovery_codes'),
            'two_factor_confirmed_at' => $user->getRawOriginal('two_factor_confirmed_at'),
        ];

        $this->runSuccessfulCommand($user);

        $this->assertSame('preserved', Cache::get('unrelated-application-cache-entry'));
        $this->assertSame($sessionValue, session('preserved-session-value'));
        $this->assertGuest();

        $user->refresh();
        $this->assertSame($preservedUser, [
            'password' => $user->password,
            'email' => $user->email,
            'email_verified_at' => $user->getRawOriginal('email_verified_at'),
            'remember_token' => $user->remember_token,
            'two_factor_secret' => $user->getRawOriginal('two_factor_secret'),
            'two_factor_recovery_codes' => $user->getRawOriginal('two_factor_recovery_codes'),
            'two_factor_confirmed_at' => $user->getRawOriginal('two_factor_confirmed_at'),
        ]);
    }

    public function test_reset_does_not_bypass_password_or_two_factor_authentication(): void
    {
        $user = User::factory()->withTwoFactor()->create();
        $this->seedPrimary($user->email, '127.0.0.1', LoginThrottle::PRIMARY_MAX_ATTEMPTS);
        $this->seedIp('127.0.0.1', LoginThrottle::IP_MAX_ATTEMPTS);

        $this->runSuccessfulCommand($user);

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

    public function test_reset_log_and_command_output_omit_sensitive_or_internal_values(): void
    {
        Log::spy();
        $user = User::factory()->create(['email' => 'operator@example.com']);
        $throttle = app(LoginThrottle::class);
        $oldGeneration = $throttle->currentGeneration();
        $oldKey = $this->seedPrimary($user->email, self::FIRST_IP);

        $this->artisan('admin:clear-login-throttle')
            ->expectsQuestion('Exact admin email', $user->email)
            ->expectsConfirmation('Clear all temporary login limits?', 'yes')
            ->expectsOutput('All temporary login limits cleared. Normal authentication is still required.')
            ->doesntExpectOutputToContain($user->email)
            ->doesntExpectOutputToContain($oldGeneration)
            ->doesntExpectOutputToContain($oldKey)
            ->assertSuccessful();

        $newGeneration = $throttle->currentGeneration();

        Log::shouldHaveReceived('notice')->once()->withArgs(
            function (string $message, array $context) use ($newGeneration, $oldGeneration, $oldKey, $user): bool {
                $serialized = $message.json_encode($context, JSON_THROW_ON_ERROR);

                return $message === 'Login-throttle namespace reset through authorized server command.'
                    && $context['user_id'] === $user->id
                    && $context['email_hash'] === hash('sha256', $user->email)
                    && ! str_contains($serialized, $user->email)
                    && ! str_contains($serialized, self::FIRST_IP)
                    && ! str_contains($serialized, $oldGeneration)
                    && ! str_contains($serialized, $newGeneration)
                    && ! str_contains($serialized, $oldKey)
                    && ! str_contains($serialized, 'login:password:');
            },
        );
    }

    public function test_login_limit_policy_values_are_unchanged(): void
    {
        $this->assertSame(5, LoginThrottle::PRIMARY_MAX_ATTEMPTS);
        $this->assertSame(15 * 60, LoginThrottle::PRIMARY_DECAY_SECONDS);
        $this->assertSame(20, LoginThrottle::IP_MAX_ATTEMPTS);
        $this->assertSame(60 * 60, LoginThrottle::IP_DECAY_SECONDS);
    }

    private function runSuccessfulCommand(User $user): void
    {
        $this->artisan('admin:clear-login-throttle')
            ->expectsQuestion('Exact admin email', $user->email)
            ->expectsConfirmation('Clear all temporary login limits?', 'yes')
            ->expectsOutput('All temporary login limits cleared. Normal authentication is still required.')
            ->assertSuccessful();
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
