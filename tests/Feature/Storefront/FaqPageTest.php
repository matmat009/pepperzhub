<?php

namespace Tests\Feature\Storefront;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class FaqPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_faq_is_a_public_inertia_page(): void
    {
        $this->get('/faq')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('storefront/Faq'),
            );

        $this->assertSame('/faq', route('storefront.faq', absolute: false));
    }

    public function test_the_storefront_nav_uses_the_internal_faq_route(): void
    {
        $nav = file_get_contents(
            resource_path('js/components/storefront/StorefrontNav.vue'),
        );

        $this->assertStringContainsString("label: 'FAQ'", $nav);
        $this->assertStringContainsString('href: faq()', $nav);
        $this->assertStringContainsString("section: 'faq'", $nav);
        $this->assertStringNotContainsString('href: settings.value.facebook_url', $nav);
    }
}
