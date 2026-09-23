<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class AppearanceScopeTest extends TestCase
{
    use RefreshDatabase;

    public function test_saved_dark_appearance_is_not_applied_to_login_or_storefront_first_paint(): void
    {
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

    public function test_authenticated_admin_first_paint_preserves_saved_dark_appearance(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->withUnencryptedCookie('appearance', 'dark')
            ->get(route('dashboard'));

        $response
            ->assertOk()
            ->assertSee('data-theme-scope="admin"', false)
            ->assertSee('class="dark"', false);
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
