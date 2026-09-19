<?php

namespace Tests\Feature\Auth;

use App\Http\Responses\PasswordResetLinkResponse;
use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class PasswordResetAvailabilityTest extends TestCase
{
    use RefreshDatabase;

    public function test_disabled_recovery_hides_the_login_link_and_blocks_all_reset_routes(): void
    {
        Notification::fake();
        config(['fortify.password_reset_enabled' => false]);

        $this->get(route('login'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('auth/Login')
                ->where('canResetPassword', false)
                ->where('resetPasswordUrl', null));

        $this->get('/forgot-password')->assertNotFound();
        $this->post('/forgot-password', ['email' => 'admin@example.com'])->assertNotFound();
        $this->get('/reset-password/test-token?email=admin%40example.com')->assertNotFound();
        $this->post('/reset-password', [
            'token' => 'test-token',
            'email' => 'admin@example.com',
            'password' => 'temporary-password-123',
            'password_confirmation' => 'temporary-password-123',
        ])->assertNotFound();

        Notification::assertNothingSent();
    }

    public function test_reenabling_recovery_restores_the_existing_hardened_flow(): void
    {
        Notification::fake();
        config(['fortify.password_reset_enabled' => true]);
        $user = User::factory()->create();

        $this->get(route('login'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('canResetPassword', true)
                ->where('resetPasswordUrl', route('password.request')));

        $this->get(route('password.request'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('auth/ForgotPassword')
                ->where('submitUrl', route('password.email')));

        $this->from(route('password.request'))
            ->post(route('password.email'), ['email' => $user->email])
            ->assertRedirect(route('password.request'))
            ->assertSessionHas('status', PasswordResetLinkResponse::MESSAGE);

        Notification::assertSentTo($user, ResetPassword::class);
    }
}
