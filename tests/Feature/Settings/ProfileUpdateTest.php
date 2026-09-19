<?php

namespace Tests\Feature\Settings;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class ProfileUpdateTest extends TestCase
{
    use RefreshDatabase;

    public function test_profile_page_is_displayed()
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->get(route('profile.edit'));

        $response->assertOk();
    }

    public function test_profile_name_can_be_updated_without_changing_email_or_verification()
    {
        $user = User::factory()->create();
        $originalEmail = $user->email;
        $verifiedAt = $user->email_verified_at;

        $response = $this
            ->actingAs($user)
            ->patch(route('profile.update'), [
                'name' => 'Test User',
            ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('profile.edit'));

        $user->refresh();

        $this->assertSame('Test User', $user->name);
        $this->assertSame($originalEmail, $user->email);
        $this->assertTrue($verifiedAt->equalTo($user->email_verified_at));
    }

    public function test_profile_email_cannot_be_changed_by_a_crafted_request()
    {
        Notification::fake();
        $user = User::factory()->withTwoFactor()->create();
        $originalEmail = $user->email;
        $verifiedAt = $user->email_verified_at;
        $preserved = [
            'password' => $user->password,
            'two_factor_secret' => $user->getRawOriginal('two_factor_secret'),
            'two_factor_recovery_codes' => $user->getRawOriginal('two_factor_recovery_codes'),
            'two_factor_confirmed_at' => $user->getRawOriginal('two_factor_confirmed_at'),
        ];

        $response = $this
            ->actingAs($user)
            ->from(route('profile.edit'))
            ->patch(route('profile.update'), [
                'name' => 'Test User',
                'email' => 'attacker@example.com',
            ]);

        $response
            ->assertSessionHasErrors('email')
            ->assertRedirect(route('profile.edit'));

        $user->refresh();

        $this->assertNotSame('Test User', $user->name);
        $this->assertSame($originalEmail, $user->email);
        $this->assertTrue($verifiedAt->equalTo($user->email_verified_at));
        $this->assertSame($preserved, [
            'password' => $user->password,
            'two_factor_secret' => $user->getRawOriginal('two_factor_secret'),
            'two_factor_recovery_codes' => $user->getRawOriginal('two_factor_recovery_codes'),
            'two_factor_confirmed_at' => $user->getRawOriginal('two_factor_confirmed_at'),
        ]);
        Notification::assertNothingSent();
    }

    public function test_unchanged_profile_email_is_accepted_but_not_written(): void
    {
        $user = User::factory()->create();
        $originalEmail = $user->email;
        $verifiedAt = $user->email_verified_at;

        $this->actingAs($user)
            ->patch(route('profile.update'), [
                'name' => 'Updated Operator',
                'email' => $user->email,
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('profile.edit'));

        $user->refresh();

        $this->assertSame('Updated Operator', $user->name);
        $this->assertSame($originalEmail, $user->email);
        $this->assertTrue($verifiedAt->equalTo($user->email_verified_at));
    }

    public function test_profile_page_displays_email_as_read_only(): void
    {
        $source = file_get_contents(resource_path('js/pages/settings/Profile.vue'));

        $this->assertStringContainsString(':model-value="user.email"', $source);
        $this->assertStringContainsString('disabled', $source);
        $this->assertStringContainsString(
            'The administrator email is managed by the system and cannot',
            $source,
        );
        $this->assertStringContainsString('be changed here.', $source);
    }
}
