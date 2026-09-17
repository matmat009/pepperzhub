<?php

namespace Tests\Feature\Auth;

use App\Auth\DatabaseUserSessionRevoker;
use App\Http\Responses\PasswordResetLinkResponse;
use App\Models\User;
use App\Support\SessionCart;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Contracts\Notifications\Dispatcher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Laravel\Fortify\Features;
use LogicException;
use Symfony\Component\Mailer\Exception\TransportException;
use Tests\TestCase;

class PasswordResetTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->skipUnlessFortifyHas(Features::resetPasswords());

        config([
            'session.driver' => 'database',
            'session.connection' => config('database.default'),
            'session.table' => 'sessions',
        ]);
        $this->app['session']->forgetDrivers();
        $this->app->forgetInstance('session.store');
    }

    public function test_reset_password_link_screen_can_be_rendered()
    {
        $response = $this->get(route('password.request'));

        $response->assertOk();
    }

    public function test_reset_password_link_can_be_requested()
    {
        Notification::fake();

        $user = User::factory()->create();

        $this->post(route('password.email'), ['email' => $user->email]);

        Notification::assertSentTo($user, ResetPassword::class);
    }

    public function test_existing_and_nonexistent_emails_receive_the_same_public_response(): void
    {
        Notification::fake();

        $user = User::factory()->create();
        $missingEmail = 'missing@example.com';

        $existingResponse = $this
            ->from(route('password.request'))
            ->post(route('password.email'), ['email' => $user->email]);

        $existingResponse
            ->assertRedirect(route('password.request'))
            ->assertSessionHasNoErrors()
            ->assertSessionHas('status', PasswordResetLinkResponse::MESSAGE);

        $missingResponse = $this
            ->from(route('password.request'))
            ->post(route('password.email'), ['email' => $missingEmail]);

        $missingResponse
            ->assertStatus($existingResponse->getStatusCode())
            ->assertRedirect($existingResponse->headers->get('Location'))
            ->assertSessionHasNoErrors()
            ->assertSessionHas('status', PasswordResetLinkResponse::MESSAGE);

        Notification::assertSentToTimes($user, ResetPassword::class, 1);
        Notification::assertCount(1);
        $this->assertDatabaseMissing('password_reset_tokens', ['email' => $missingEmail]);
    }

    public function test_broker_cooldown_uses_the_same_neutral_response(): void
    {
        Notification::fake();

        $user = User::factory()->create();

        foreach (range(1, 2) as $attempt) {
            $this
                ->from(route('password.request'))
                ->post(route('password.email'), ['email' => $user->email])
                ->assertRedirect(route('password.request'))
                ->assertSessionHasNoErrors()
                ->assertSessionHas('status', PasswordResetLinkResponse::MESSAGE);
        }

        Notification::assertSentToTimes($user, ResetPassword::class, 1);
    }

    public function test_missing_and_malformed_reset_link_emails_still_fail_validation(): void
    {
        $this
            ->from(route('password.request'))
            ->post(route('password.email'), [])
            ->assertRedirect(route('password.request'))
            ->assertSessionHasErrors('email')
            ->assertSessionMissing('status');

        $this
            ->from(route('password.request'))
            ->post(route('password.email'), ['email' => 'not-an-email'])
            ->assertRedirect(route('password.request'))
            ->assertSessionHasErrors('email')
            ->assertSessionMissing('status');
    }

    public function test_forgot_password_requests_are_limited_for_nonexistent_normalized_emails(): void
    {
        Notification::fake();

        foreach (range(1, 5) as $attempt) {
            $email = $attempt % 2 === 0 ? 'MISSING@EXAMPLE.COM' : 'missing@example.com';

            $this
                ->from(route('password.request'))
                ->post(route('password.email'), ['email' => $email])
                ->assertSessionHas('status', PasswordResetLinkResponse::MESSAGE);
        }

        $response = $this
            ->from(route('password.request'))
            ->post(route('password.email'), ['email' => 'missing@example.com']);

        $response
            ->assertTooManyRequests()
            ->assertSeeText('Too many password-reset requests. Please try again in ')
            ->assertHeader('Retry-After');

        $inertiaResponse = $this
            ->withHeader('X-Inertia', 'true')
            ->from(route('password.request'))
            ->post(route('password.email'), ['email' => 'missing@example.com']);

        $inertiaResponse
            ->assertRedirect(route('password.request'))
            ->assertSessionHasErrors('email')
            ->assertHeader('Retry-After');

        $this->assertStringStartsWith(
            'Too many password-reset requests. Please try again in ',
            session('errors')->first('email'),
        );
        Notification::assertNothingSent();
        $this->assertDatabaseMissing('password_reset_tokens', ['email' => 'missing@example.com']);
    }

    public function test_forgot_password_ip_limit_cannot_be_bypassed_by_rotating_nonexistent_emails(): void
    {
        Notification::fake();

        foreach (range(1, 20) as $attempt) {
            $this
                ->from(route('password.request'))
                ->post(route('password.email'), ['email' => "missing{$attempt}@example.com"])
                ->assertSessionHas('status', PasswordResetLinkResponse::MESSAGE);
        }

        $this
            ->from(route('password.request'))
            ->post(route('password.email'), ['email' => 'another-missing@example.com'])
            ->assertTooManyRequests()
            ->assertSeeText('Too many password-reset requests. Please try again in ')
            ->assertHeader('Retry-After');

        Notification::assertNothingSent();
    }

    public function test_reset_password_screen_can_be_rendered()
    {
        Notification::fake();

        $user = User::factory()->create();

        $this->post(route('password.email'), ['email' => $user->email]);

        Notification::assertSentTo($user, ResetPassword::class, function ($notification) {
            $response = $this->get(route('password.reset', $notification->token));

            $response->assertOk();

            return true;
        });
    }

    public function test_password_can_be_reset_with_valid_token()
    {
        Notification::fake();

        $user = User::factory()->create();

        $this->post(route('password.email'), ['email' => $user->email]);

        Notification::assertSentTo($user, ResetPassword::class, function ($notification) use ($user) {
            $response = $this->post(route('password.update'), [
                'token' => $notification->token,
                'email' => $user->email,
                'password' => 'password',
                'password_confirmation' => 'password',
            ]);

            $response
                ->assertSessionHasNoErrors()
                ->assertRedirect(route('login'));

            return true;
        });
    }

    public function test_password_cannot_be_reset_with_invalid_token(): void
    {
        $user = User::factory()->create();
        $originalPassword = $user->password;

        $response = $this->post(route('password.update'), [
            'token' => 'invalid-token',
            'email' => $user->email,
            'password' => 'newpassword123',
            'password_confirmation' => 'newpassword123',
        ]);

        $response->assertSessionHasErrors('email');
        $this->assertSame($originalPassword, $user->fresh()->password);
        $this->assertFalse(Hash::check('newpassword123', $user->fresh()->password));
    }

    public function test_reset_password_submissions_are_limited_for_nonexistent_emails(): void
    {
        $payload = [
            'token' => 'invalid-token',
            'email' => 'missing@example.com',
            'password' => 'newpassword123',
            'password_confirmation' => 'newpassword123',
        ];

        foreach (range(1, 5) as $attempt) {
            $payload['email'] = $attempt % 2 === 0 ? 'MISSING@EXAMPLE.COM' : 'missing@example.com';

            $this->post(route('password.update'), $payload)
                ->assertSessionHasErrors('email');
        }

        $payload['email'] = 'missing@example.com';
        $response = $this->post(route('password.update'), $payload);

        $response
            ->assertTooManyRequests()
            ->assertSeeText('Too many password-reset requests. Please try again in ')
            ->assertHeader('Retry-After');
    }

    public function test_reset_request_throttling_does_not_affect_login(): void
    {
        $user = User::factory()->create();
        $payload = [
            'token' => 'invalid-token',
            'email' => 'missing@example.com',
            'password' => 'newpassword123',
            'password_confirmation' => 'newpassword123',
        ];

        foreach (range(1, 6) as $attempt) {
            $this->post(route('password.update'), $payload);
        }

        $this->post(route('login.store'), [
            'email' => $user->email,
            'password' => 'password',
        ])->assertRedirect(route('dashboard', absolute: false));

        $this->assertAuthenticatedAs($user);
    }

    public function test_mail_transport_failures_are_logged_safely_and_receive_the_neutral_response(): void
    {
        $this->withoutExceptionHandling();

        $user = User::factory()->create();
        $dispatcher = \Mockery::mock(Dispatcher::class);
        $dispatcher->shouldReceive('send')
            ->once()
            ->andThrow(new TransportException('Sensitive transport detail'));
        $this->app->instance(Dispatcher::class, $dispatcher);
        Log::spy();

        $response = $this
            ->from(route('password.request'))
            ->post(route('password.email'), ['email' => $user->email]);

        $response
            ->assertRedirect(route('password.request'))
            ->assertSessionHasNoErrors()
            ->assertSessionHas('status', PasswordResetLinkResponse::MESSAGE);

        Log::shouldHaveReceived('error')
            ->once()
            ->with(
                'Password-reset notification transport failed.',
                ['exception_class' => TransportException::class],
            );
    }

    public function test_successful_reset_revokes_only_the_users_persistent_sessions_and_preserves_guest_cart(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $firstSession = $this->createDatabaseSessionThroughLogin($user);
        $this->usingSession($firstSession)
            ->get(route('dashboard'))
            ->assertOk();

        $secondSession = $this->cloneDatabaseSession($firstSession, $user);
        $otherSession = $this->cloneDatabaseSession($firstSession, $otherUser);
        $guestSession = $this->cloneDatabaseSession(
            $firstSession,
            null,
            [SessionCart::SESSION_KEY => [999 => 2]],
        );
        $rememberToken = $user->remember_token;

        $token = Password::broker(config('fortify.passwords'))->createToken($user);

        $this->usingFreshGuestSession()
            ->post(route('password.update'), [
                'token' => $token,
                'email' => $user->email,
                'password' => 'newpassword123',
                'password_confirmation' => 'newpassword123',
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('login'));

        $this->assertGuest();
        $this->assertDatabaseMissing('sessions', ['id' => $firstSession]);
        $this->assertDatabaseMissing('sessions', ['id' => $secondSession]);
        $this->assertDatabaseHas('sessions', ['id' => $otherSession, 'user_id' => $otherUser->id]);
        $this->assertDatabaseHas('sessions', ['id' => $guestSession, 'user_id' => null]);

        $this->usingSession($firstSession)
            ->get(route('dashboard'))
            ->assertRedirect(route('login'));

        $this->usingSession($otherSession)
            ->get(route('dashboard'))
            ->assertOk();

        $this->usingSession($guestSession)
            ->get(route('home'))
            ->assertInertia(fn ($page) => $page->where('cartCount', 2));

        $this->assertTrue(Hash::check('newpassword123', $user->fresh()->password));
        $this->assertNotSame($rememberToken, $user->fresh()->remember_token);

        $this->usingFreshGuestSession()
            ->from(route('login'))
            ->post(route('login.store'), ['email' => $user->email, 'password' => 'password'])
            ->assertRedirect(route('login'));

        $this->assertGuest();

        $this->usingFreshGuestSession()
            ->post(route('login.store'), ['email' => $user->email, 'password' => 'newpassword123'])
            ->assertRedirect(route('dashboard', absolute: false));

        $this->assertAuthenticatedAs($user->fresh());
    }

    public function test_failed_reset_keeps_the_password_and_existing_session_valid(): void
    {
        $user = User::factory()->create();
        $session = $this->createDatabaseSessionThroughLogin($user);
        $originalPassword = $user->password;

        $this->usingFreshGuestSession()
            ->post(route('password.update'), [
                'token' => 'invalid-token',
                'email' => $user->email,
                'password' => 'newpassword123',
                'password_confirmation' => 'newpassword123',
            ])
            ->assertRedirect();

        $this->assertSame($originalPassword, $user->fresh()->password);
        $this->assertDatabaseHas('sessions', ['id' => $session, 'user_id' => $user->id]);

        $this->usingSession($session)
            ->get(route('dashboard'))
            ->assertOk();
    }

    public function test_expired_reset_token_keeps_the_password_and_existing_session_valid(): void
    {
        $user = User::factory()->create();
        $session = $this->createDatabaseSessionThroughLogin($user);
        $originalPassword = $user->password;
        $token = Password::broker(config('fortify.passwords'))->createToken($user);
        $broker = config('auth.passwords.'.config('fortify.passwords'));

        $this->travel($broker['expire'] + 1)->minutes();

        $this->usingFreshGuestSession()
            ->post(route('password.update'), [
                'token' => $token,
                'email' => $user->email,
                'password' => 'newpassword123',
                'password_confirmation' => 'newpassword123',
            ])
            ->assertRedirect();

        $this->assertSame($originalPassword, $user->fresh()->password);
        $this->assertDatabaseHas('sessions', ['id' => $session, 'user_id' => $user->id]);

        $this->usingSession($session)
            ->get(route('dashboard'))
            ->assertOk();
    }

    public function test_rejected_password_validation_keeps_the_password_and_existing_session_valid(): void
    {
        $user = User::factory()->create();
        $session = $this->createDatabaseSessionThroughLogin($user);
        $originalPassword = $user->password;
        $token = Password::broker(config('fortify.passwords'))->createToken($user);

        $this->usingFreshGuestSession()
            ->post(route('password.update'), [
                'token' => $token,
                'email' => $user->email,
                'password' => 'newpassword123',
                'password_confirmation' => 'differentpassword123',
            ])
            ->assertRedirect();

        $this->assertSame($originalPassword, $user->fresh()->password);
        $this->assertDatabaseHas('sessions', ['id' => $session, 'user_id' => $user->id]);
        $this->assertDatabaseHas('password_reset_tokens', ['email' => $user->email]);

        $this->usingSession($session)
            ->get(route('dashboard'))
            ->assertOk();
    }

    public function test_used_reset_token_cannot_revoke_a_new_session_or_change_the_password_again(): void
    {
        $user = User::factory()->create();
        $token = Password::broker(config('fortify.passwords'))->createToken($user);

        $this->usingFreshGuestSession()
            ->post(route('password.update'), [
                'token' => $token,
                'email' => $user->email,
                'password' => 'newpassword123',
                'password_confirmation' => 'newpassword123',
            ])
            ->assertSessionHasNoErrors();

        $this->assertDatabaseMissing('password_reset_tokens', ['email' => $user->email]);
        $newSession = $this->createDatabaseSessionThroughLogin($user->fresh(), 'newpassword123');

        $this->usingFreshGuestSession()
            ->post(route('password.update'), [
                'token' => $token,
                'email' => $user->email,
                'password' => 'anotherpassword123',
                'password_confirmation' => 'anotherpassword123',
            ])
            ->assertRedirect();

        $this->assertTrue(Hash::check('newpassword123', $user->fresh()->password));
        $this->assertFalse(Hash::check('anotherpassword123', $user->fresh()->password));
        $this->assertDatabaseHas('sessions', ['id' => $newSession, 'user_id' => $user->id]);

        $this->usingSession($newSession)
            ->get(route('dashboard'))
            ->assertOk();
    }

    public function test_reset_preserves_two_factor_and_passkeys_and_next_login_still_challenges(): void
    {
        $user = User::factory()->withTwoFactor()->create();
        $passkey = $user->passkeys()->create([
            'name' => 'Admin security key',
            'credential_id' => 'credential-id',
            'credential' => ['public_key' => 'opaque-test-value'],
        ]);
        $twoFactorState = [
            'two_factor_secret' => $user->getRawOriginal('two_factor_secret'),
            'two_factor_recovery_codes' => $user->getRawOriginal('two_factor_recovery_codes'),
            'two_factor_confirmed_at' => $user->getRawOriginal('two_factor_confirmed_at'),
        ];
        $token = Password::broker(config('fortify.passwords'))->createToken($user);

        $this->usingFreshGuestSession()
            ->post(route('password.update'), [
                'token' => $token,
                'email' => $user->email,
                'password' => 'newpassword123',
                'password_confirmation' => 'newpassword123',
            ])
            ->assertSessionHasNoErrors();

        $user->refresh();
        $this->assertSame($twoFactorState, [
            'two_factor_secret' => $user->getRawOriginal('two_factor_secret'),
            'two_factor_recovery_codes' => $user->getRawOriginal('two_factor_recovery_codes'),
            'two_factor_confirmed_at' => $user->getRawOriginal('two_factor_confirmed_at'),
        ]);
        $this->assertTrue($user->passkeys()->whereKey($passkey->id)->exists());

        $this->usingFreshGuestSession()
            ->post(route('login.store'), ['email' => $user->email, 'password' => 'newpassword123'])
            ->assertRedirect(route('two-factor.login'))
            ->assertSessionHas('login.id', $user->id);

        $this->assertGuest();
    }

    public function test_unsupported_session_driver_fails_before_changing_the_password(): void
    {
        $user = User::factory()->create();
        $originalPassword = $user->password;
        $passwordChangeRan = false;
        config(['session.driver' => 'file']);

        try {
            $this->app->make(DatabaseUserSessionRevoker::class)
                ->revokeWithinPasswordChange($user, function () use (&$passwordChangeRan, $user): void {
                    $passwordChangeRan = true;
                    $user->forceFill(['password' => 'newpassword123'])->save();
                });

            $this->fail('Unsupported session drivers must not silently skip session revocation.');
        } catch (LogicException $exception) {
            $this->assertSame(
                'Password-reset session revocation requires the database session driver.',
                $exception->getMessage(),
            );
        }

        $this->assertFalse($passwordChangeRan);
        $this->assertSame($originalPassword, $user->fresh()->password);
    }

    private function createDatabaseSessionThroughLogin(User $user, string $password = 'password'): string
    {
        $response = $this->usingFreshGuestSession()
            ->post(route('login.store'), ['email' => $user->email, 'password' => $password])
            ->assertRedirect(route('dashboard', absolute: false));
        $cookie = $response->getCookie(config('session.cookie'));
        $this->assertNotNull($cookie);
        $id = $cookie->getValue();
        $this->assertDatabaseHas('sessions', ['id' => $id, 'user_id' => $user->id]);

        return $id;
    }

    /** @param array<string, mixed> $attributes */
    private function cloneDatabaseSession(string $sourceId, ?User $user, array $attributes = []): string
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

        $payload = array_replace($payload, $attributes);
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
