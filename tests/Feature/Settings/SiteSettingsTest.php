<?php

namespace Tests\Feature\Settings;

use App\Models\SiteSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The storefront's contact and social details: saved from the profile page,
 * published as a shared prop, and rendered only where they are actually set.
 *
 * The footer and the FAQ nav item used to carry their own literals — an email,
 * a phone number, an address and three href="#" icons — so the shop advertised
 * contact details nobody maintained. The point of these tests is that the
 * operator's saved values are what reaches the client, and that a field left
 * blank produces no markup at all rather than an empty line or a dead link.
 */
class SiteSettingsTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @var array<string, string>
     */
    private const ALL_FIELDS = [
        'contact_email' => 'orders@pepperzhub.ph',
        'contact_phone' => '0917 123 4567',
        'contact_address' => 'Quezon City, Philippines',
        'facebook_url' => 'https://facebook.com/pepperzhub',
        'instagram_url' => 'https://instagram.com/pepperzhub',
        'tiktok_url' => 'https://tiktok.com/@pepperzhub',
    ];

    private function operator(): User
    {
        return User::factory()->create(['email_verified_at' => now()]);
    }

    public function test_the_single_row_is_created_on_first_access_without_a_seeder(): void
    {
        $this->assertSame(0, SiteSetting::count());

        $first = SiteSetting::current();
        $second = SiteSetting::current();

        $this->assertSame(1, SiteSetting::count(), 'current() created a second row');
        $this->assertSame($first->id, $second->id);
        $this->assertNull($first->contact_email);
    }

    public function test_saving_persists_every_field_and_reaches_the_next_response(): void
    {
        $this->actingAs($this->operator())
            ->put(route('site-settings.update'), self::ALL_FIELDS)
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('profile.edit'));

        $this->assertDatabaseHas('site_settings', self::ALL_FIELDS);
        $this->assertSame(1, SiteSetting::count(), 'the save created a second settings row');

        // The storefront is where these are read, and it is unauthenticated.
        $this->get(route('home'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('siteSettings', self::ALL_FIELDS)
            );
    }

    /**
     * Shared, not per-page: the footer renders on every storefront page and the
     * nav's FAQ item with it, so a page that forgot to pass these would lose
     * both.
     */
    public function test_the_settings_are_shared_with_every_response(): void
    {
        SiteSetting::current()->update(self::ALL_FIELDS);

        foreach (['home', 'storefront.products.index', 'storefront.cart'] as $name) {
            $this->get(route($name))
                ->assertOk()
                ->assertInertia(fn ($page) => $page
                    ->where('siteSettings.contact_email', 'orders@pepperzhub.ph')
                    ->where('siteSettings.facebook_url', 'https://facebook.com/pepperzhub')
                );
        }

        // Admin too — the profile form starts from this same prop rather than
        // from props of its own.
        $this->actingAs($this->operator())
            ->get(route('profile.edit'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('siteSettings', self::ALL_FIELDS)
            );
    }

    /**
     * An unset field reaches the client as null, never as an empty string.
     *
     * The storefront decides what to render with a plain truthiness check, so ''
     * and null are not interchangeable there: an empty string would render a
     * blank contact line under a live heading and an anchor whose href resolves
     * against the storefront's own root.
     */
    public function test_unset_fields_reach_the_client_as_null(): void
    {
        $props = $this->get(route('home'))->assertOk()->inertiaProps();

        $this->assertSame(
            array_fill_keys(array_keys(self::ALL_FIELDS), null),
            $props['siteSettings'],
            'a field nobody has filled in yet did not arrive as null',
        );
    }

    /**
     * The footer and nav gate every piece on its own field, and no longer carry
     * contact details of their own.
     *
     * Asserted against the component source rather than rendered markup: there
     * is no SSR bundle here (config/inertia.php leaves it commented out), so a
     * test request returns the Inertia shell and the footer never renders
     * server-side. Reading the file is the same move LowStockThresholdTest
     * makes for the placeholder threshold, and it pins the part that actually
     * regressed — the literals — rather than the prop, which the test above
     * already covers.
     */
    public function test_the_footer_and_nav_gate_every_piece_on_its_own_field(): void
    {
        $footer = file_get_contents(
            resource_path('js/components/storefront/StorefrontFooter.vue'),
        );

        // The literals this feature exists to remove.
        foreach (['support@pepperzhub.ph', '0917 123 4567', 'Metro Manila, Philippines'] as $literal) {
            $this->assertStringNotContainsString(
                $literal,
                $footer,
                "the footer still hardcodes [{$literal}] instead of reading the setting",
            );
        }

        // No social icon left pointing at href="#" — the dead-link state that
        // used to ship for all three.
        $this->assertStringNotContainsString('href="#"', $footer);

        // Each piece carries its own guard, so one blank field removes exactly
        // one element.
        foreach (array_keys(self::ALL_FIELDS) as $field) {
            $this->assertStringContainsString(
                "v-if=\"settings.{$field}\"",
                $footer,
                "the footer renders [{$field}] unconditionally",
            );
        }

        // And the two blocks drop out entirely when everything inside them is
        // unset, rather than leaving a heading over nothing.
        $this->assertStringContainsString('v-if="hasContact"', $footer);
        $this->assertStringContainsString('v-if="hasSocials"', $footer);

        $nav = file_get_contents(
            resource_path('js/components/storefront/StorefrontNav.vue'),
        );

        // FAQ is built from the setting and only exists when it is set.
        $this->assertStringContainsString('settings.value.facebook_url', $nav);
        $this->assertStringContainsString("label: 'FAQ'", $nav);
        $this->assertStringContainsString("link.external ? '_blank' : undefined", $nav);
    }

    /**
     * Clearing one field removes only that field.
     *
     * The input arrives as '' rather than null, which would be stored as an
     * empty string and read as "set" by every v-if on the storefront — the
     * footer would render a blank line under a live heading.
     */
    public function test_clearing_a_field_nulls_it_and_leaves_the_others_alone(): void
    {
        SiteSetting::current()->update(self::ALL_FIELDS);

        $this->actingAs($this->operator())
            ->put(route('site-settings.update'), [
                ...self::ALL_FIELDS,
                'contact_phone' => '',
                'facebook_url' => '',
            ])
            ->assertSessionHasNoErrors();

        $settings = SiteSetting::current()->refresh();

        $this->assertNull($settings->contact_phone, 'a cleared field was stored as an empty string');
        $this->assertNull($settings->facebook_url);
        $this->assertSame('orders@pepperzhub.ph', $settings->contact_email);
        $this->assertSame('Quezon City, Philippines', $settings->contact_address);
        $this->assertSame('https://instagram.com/pepperzhub', $settings->instagram_url);

        // And that is what the next response carries: the cleared pair null,
        // the rest untouched. The footer's Contact Us column survives on the
        // strength of the two fields still set; its phone line, its Facebook
        // icon and the nav's FAQ item do not.
        $props = $this->get(route('home'))->assertOk()->inertiaProps();

        $this->assertNull($props['siteSettings']['contact_phone']);
        $this->assertNull($props['siteSettings']['facebook_url']);
        $this->assertSame('orders@pepperzhub.ph', $props['siteSettings']['contact_email']);
    }

    /**
     * Every field is optional, so an entirely empty submission is valid — that
     * is how the operator clears details they no longer want published.
     */
    public function test_an_empty_submission_is_accepted_and_clears_everything(): void
    {
        SiteSetting::current()->update(self::ALL_FIELDS);

        $this->actingAs($this->operator())
            ->put(route('site-settings.update'), [])
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('site_settings', array_fill_keys(array_keys(self::ALL_FIELDS), null));
    }

    /**
     * The three social fields are rendered as hrefs, so a bare domain would
     * resolve against the storefront's own origin.
     */
    public function test_social_fields_must_be_absolute_urls_and_the_email_must_be_an_email(): void
    {
        $this->actingAs($this->operator())
            ->put(route('site-settings.update'), [
                'contact_email' => 'not-an-email',
                'facebook_url' => 'facebook.com/pepperzhub',
                'instagram_url' => 'instagram.com/pepperzhub',
                'tiktok_url' => 'tiktok.com/@pepperzhub',
            ])
            ->assertSessionHasErrors([
                'contact_email',
                'facebook_url',
                'instagram_url',
                'tiktok_url',
            ]);

        $this->assertDatabaseHas('site_settings', ['facebook_url' => null]);
    }

    /**
     * The two forms on the profile page are independent submissions.
     *
     * Saving the storefront details must not touch the operator's account, and
     * saving the account must not touch the storefront details — they share a
     * page and nothing else.
     */
    public function test_the_two_profile_forms_do_not_touch_each_other(): void
    {
        $user = $this->operator();
        $originalName = $user->name;
        $originalEmail = $user->email;

        SiteSetting::current()->update(self::ALL_FIELDS);

        $this->actingAs($user)
            ->put(route('site-settings.update'), [
                ...self::ALL_FIELDS,
                'contact_email' => 'new@pepperzhub.ph',
            ])
            ->assertSessionHasNoErrors();

        $user->refresh();

        $this->assertSame($originalName, $user->name);
        $this->assertSame($originalEmail, $user->email, "the storefront's email overwrote the login");
        $this->assertNotNull($user->email_verified_at, 'the storefront save invalidated the login email');

        // And the other direction.
        $this->actingAs($user)
            ->patch(route('profile.update'), [
                'name' => 'Operator',
                'email' => 'operator@pepperzhub.ph',
            ])
            ->assertSessionHasNoErrors();

        $settings = SiteSetting::current()->refresh();

        $this->assertSame('new@pepperzhub.ph', $settings->contact_email);
        $this->assertSame('Quezon City, Philippines', $settings->contact_address);
        $this->assertSame('Operator', $user->refresh()->name);
    }

    public function test_a_guest_cannot_change_the_settings(): void
    {
        $this->put(route('site-settings.update'), self::ALL_FIELDS)
            ->assertRedirect(route('login'));

        $this->assertDatabaseMissing('site_settings', ['contact_email' => 'orders@pepperzhub.ph']);
    }
}
