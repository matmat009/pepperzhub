<?php

namespace Tests\Feature\Auth;

use App\Auth\DatabaseUserSessionRevoker;
use App\Console\Commands\ResetAdminPasswordCommand;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use RuntimeException;
use Tests\TestCase;

class AdminPasswordRecoveryCommandTest extends TestCase
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
        Notification::fake();
    }

    public function test_command_uses_hidden_password_prompts_and_accepts_no_password_argument(): void
    {
        $source = file_get_contents(app_path('Console/Commands/ResetAdminPasswordCommand.php'));
        $command = $this->app->make(ResetAdminPasswordCommand::class);

        $this->assertSame('admin:reset-password', $command->getName());
        $this->assertSame([], $command->getDefinition()->getArguments());
        $this->assertSame([], $command->getDefinition()->getOptions());
        $this->assertStringContainsString("secret('New password')", $source);
        $this->assertStringContainsString("secret('Confirm new password')", $source);
    }

    public function test_command_rejects_an_unknown_or_inexact_email_without_changes(): void
    {
        $user = User::factory()->create(['email' => 'admin@example.com']);
        $originalPassword = $user->password;

        $this->artisan('admin:reset-password')
            ->expectsQuestion('Exact admin email', 'ADMIN@example.com')
            ->expectsOutput('No matching admin account was found.')
            ->assertFailed();

        $this->assertSame($originalPassword, $user->fresh()->password);
        $this->assertSame(1, User::count());
        Notification::assertNothingSent();
    }

    public function test_mismatched_password_confirmation_makes_no_changes(): void
    {
        $user = User::factory()->create();
        $sessionId = $this->createSessionRow($user);
        $originalPassword = $user->password;
        $originalRememberToken = $user->remember_token;

        $this->artisan('admin:reset-password')
            ->expectsQuestion('Exact admin email', $user->email)
            ->expectsQuestion('New password', 'temporary-password-123')
            ->expectsQuestion('Confirm new password', 'different-password-123')
            ->assertFailed();

        $user->refresh();
        $this->assertSame($originalPassword, $user->password);
        $this->assertSame($originalRememberToken, $user->remember_token);
        $this->assertDatabaseHas('sessions', ['id' => $sessionId, 'user_id' => $user->id]);
        Notification::assertNothingSent();
    }

    public function test_password_policy_rejection_makes_no_changes(): void
    {
        $user = User::factory()->create();
        $sessionId = $this->createSessionRow($user);
        $originalPassword = $user->password;

        $this->artisan('admin:reset-password')
            ->expectsQuestion('Exact admin email', $user->email)
            ->expectsQuestion('New password', 'short')
            ->expectsQuestion('Confirm new password', 'short')
            ->assertFailed();

        $this->assertSame($originalPassword, $user->fresh()->password);
        $this->assertDatabaseHas('sessions', ['id' => $sessionId, 'user_id' => $user->id]);
        Notification::assertNothingSent();
    }

    public function test_cancelled_confirmation_makes_no_changes(): void
    {
        $user = User::factory()->create();
        $originalPassword = $user->password;

        $this->artisan('admin:reset-password')
            ->expectsQuestion('Exact admin email', $user->email)
            ->expectsQuestion('New password', 'temporary-password-123')
            ->expectsQuestion('Confirm new password', 'temporary-password-123')
            ->expectsConfirmation('Reset this admin password and sign out all sessions?', 'no')
            ->expectsOutput('Password reset cancelled.')
            ->assertFailed();

        $this->assertSame($originalPassword, $user->fresh()->password);
        Notification::assertNothingSent();
    }

    public function test_successful_recovery_changes_only_password_authentication_and_target_sessions(): void
    {
        $user = User::factory()->withTwoFactor()->create([
            'name' => 'Sole Operator',
            'email' => 'operator@example.com',
        ]);
        $otherUser = User::factory()->create();
        $passkeyId = DB::table('passkeys')->insertGetId([
            'user_id' => $user->id,
            'name' => 'Admin security key',
            'credential_id' => 'recovery-command-credential',
            'credential' => json_encode(['public_key' => 'opaque-test-value'], JSON_THROW_ON_ERROR),
            'last_used_at' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $targetSession = $this->createSessionRow($user);
        $secondTargetSession = $this->createSessionRow($user);
        $otherSession = $this->createSessionRow($otherUser);
        $guestSession = $this->createSessionRow();
        $originalRememberToken = $user->remember_token;
        $preserved = [
            'name' => $user->name,
            'email' => $user->email,
            'email_verified_at' => $user->getRawOriginal('email_verified_at'),
            'two_factor_secret' => $user->getRawOriginal('two_factor_secret'),
            'two_factor_recovery_codes' => $user->getRawOriginal('two_factor_recovery_codes'),
            'two_factor_confirmed_at' => $user->getRawOriginal('two_factor_confirmed_at'),
        ];
        $newPassword = 'temporary-password-123';

        $this->artisan('admin:reset-password')
            ->expectsQuestion('Exact admin email', $user->email)
            ->expectsQuestion('New password', $newPassword)
            ->expectsQuestion('Confirm new password', $newPassword)
            ->expectsConfirmation('Reset this admin password and sign out all sessions?', 'yes')
            ->expectsOutput('Admin password reset successfully.')
            ->doesntExpectOutputToContain($newPassword)
            ->assertSuccessful();

        $user->refresh();
        $this->assertFalse(Auth::validate(['email' => $user->email, 'password' => 'password']));
        $this->assertTrue(Auth::validate(['email' => $user->email, 'password' => $newPassword]));
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
        $this->assertDatabaseMissing('sessions', ['id' => $targetSession]);
        $this->assertDatabaseMissing('sessions', ['id' => $secondTargetSession]);
        $this->assertDatabaseHas('sessions', ['id' => $otherSession, 'user_id' => $otherUser->id]);
        $this->assertDatabaseHas('sessions', ['id' => $guestSession, 'user_id' => null]);
        Notification::assertNothingSent();
    }

    public function test_unsupported_session_driver_makes_no_changes(): void
    {
        $user = User::factory()->create();
        $originalPassword = $user->password;
        $originalRememberToken = $user->remember_token;
        config(['session.driver' => 'file']);

        $this->artisan('admin:reset-password')
            ->expectsQuestion('Exact admin email', $user->email)
            ->expectsQuestion('New password', 'temporary-password-123')
            ->expectsQuestion('Confirm new password', 'temporary-password-123')
            ->expectsConfirmation('Reset this admin password and sign out all sessions?', 'yes')
            ->expectsOutput('Password reset failed. No changes were applied.')
            ->assertFailed();

        $user->refresh();
        $this->assertSame($originalPassword, $user->password);
        $this->assertSame($originalRememberToken, $user->remember_token);
        Notification::assertNothingSent();
    }

    public function test_database_failure_makes_no_changes(): void
    {
        $user = User::factory()->create();
        $sessionId = $this->createSessionRow($user);
        $originalPassword = $user->password;
        $originalRememberToken = $user->remember_token;

        $revoker = \Mockery::mock(DatabaseUserSessionRevoker::class);
        $revoker->shouldReceive('revokeWithinPasswordChange')
            ->once()
            ->andReturnUsing(function (User $user, \Closure $changePassword): void {
                $user->getConnection()->transaction(function () use ($changePassword): void {
                    $changePassword();

                    throw new RuntimeException('Simulated database failure');
                });
            });
        $this->app->instance(DatabaseUserSessionRevoker::class, $revoker);

        $this->artisan('admin:reset-password')
            ->expectsQuestion('Exact admin email', $user->email)
            ->expectsQuestion('New password', 'temporary-password-123')
            ->expectsQuestion('Confirm new password', 'temporary-password-123')
            ->expectsConfirmation('Reset this admin password and sign out all sessions?', 'yes')
            ->expectsOutput('Password reset failed. No changes were applied.')
            ->assertFailed();

        $user->refresh();
        $this->assertSame($originalPassword, $user->password);
        $this->assertSame($originalRememberToken, $user->remember_token);
        $this->assertDatabaseHas('sessions', ['id' => $sessionId, 'user_id' => $user->id]);
        Notification::assertNothingSent();
    }

    private function createSessionRow(?User $user = null): string
    {
        $id = Str::random(40);

        DB::connection(config('session.connection'))
            ->table(config('session.table'))
            ->insert([
                'id' => $id,
                'user_id' => $user?->getAuthIdentifier(),
                'ip_address' => '127.0.0.1',
                'user_agent' => 'recovery-command-test',
                'payload' => base64_encode(json_encode(['_token' => Str::random(40)], JSON_THROW_ON_ERROR)),
                'last_activity' => now()->getTimestamp(),
            ]);

        return $id;
    }
}
