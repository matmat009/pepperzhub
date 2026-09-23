<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class AppearanceScopeTest extends TestCase
{
    use RefreshDatabase;

    public function test_saved_dark_appearance_is_not_applied_to_login_or_storefront_first_paint(): void
    {
        config()->set('pepperzhub.admin_dark_mode_enabled', true);

        foreach ([route('login'), route('home')] as $url) {
            $response = $this->withUnencryptedCookie('appearance', 'dark')->get($url);

            $this->assertForcedLightResponse($response);
        }
    }

    public function test_authentication_continuation_screens_are_forced_light(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->withUnencryptedCookie('appearance', 'dark')
            ->get(route('password.confirm'));

        $this->assertForcedLightResponse($response);
    }

    public function test_disabled_admin_dark_mode_forces_light_without_discarding_the_saved_appearance(): void
    {
        config()->set('pepperzhub.admin_dark_mode_enabled', false);

        $user = User::factory()->create();

        foreach (['dark', 'system'] as $appearance) {
            $response = $this->actingAs($user)
                ->withUnencryptedCookie('appearance', $appearance)
                ->get(route('dashboard'));

            $this->assertForcedLightResponse($response);

            $response
                ->assertSee('const forceLight = true;', false)
                ->assertInertia(fn (Assert $page) => $page
                    ->where('adminDarkModeEnabled', false)
                )
                ->assertCookieMissing('appearance');
        }
    }

    public function test_disabled_appearance_page_receives_the_flag_that_hides_its_selector(): void
    {
        config()->set('pepperzhub.admin_dark_mode_enabled', false);

        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('appearance.edit'))
            ->assertInertia(fn (Assert $page) => $page
                ->component('settings/Appearance')
                ->where('adminDarkModeEnabled', false)
            );
    }

    public function test_enabled_admin_dark_mode_restores_the_saved_appearance(): void
    {
        config()->set('pepperzhub.admin_dark_mode_enabled', true);

        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->withUnencryptedCookie('appearance', 'dark')
            ->get(route('appearance.edit'));

        $response
            ->assertOk()
            ->assertSee('data-theme-scope="admin"', false)
            ->assertSee('class="dark"', false)
            ->assertInertia(fn (Assert $page) => $page
                ->where('adminDarkModeEnabled', true)
            );
    }

    private function assertForcedLightResponse(TestResponse $response): void
    {
        $response
            ->assertOk()
            ->assertSee('data-theme-scope="forced-light"', false)
            ->assertSee('style="color-scheme: light;"', false)
            ->assertDontSee('class="dark"', false);
    }
}
