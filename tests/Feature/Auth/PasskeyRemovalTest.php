<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class PasskeyRemovalTest extends TestCase
{
    use RefreshDatabase;

    /** @var array<string, array{string, string}> */
    private const REMOVED_ENDPOINTS = [
        'well-known.passkeys' => ['GET', '/.well-known/passkey-endpoints'],
        'passkey.login-options' => ['GET', '/passkeys/login/options'],
        'passkey.login' => ['POST', '/passkeys/login'],
        'passkey.confirm-options' => ['GET', '/passkeys/confirm/options'],
        'passkey.confirm' => ['POST', '/passkeys/confirm'],
        'passkey.registration-options' => ['GET', '/user/passkeys/options'],
        'passkey.store' => ['POST', '/user/passkeys'],
        'passkey.update' => ['PATCH', '/user/passkeys/1'],
        'passkey.destroy' => ['DELETE', '/user/passkeys/1'],
    ];

    public function test_passkey_routes_are_not_registered_and_old_urls_are_unavailable(): void
    {
        foreach (self::REMOVED_ENDPOINTS as $routeName => [$method, $uri]) {
            $this->assertNull(Route::getRoutes()->getByName($routeName));

            $response = $this->call($method, $uri);

            $this->assertContains($response->getStatusCode(), [404, 405]);
        }

        $this->assertGuest();
    }

    public function test_old_endpoints_cannot_authenticate_or_modify_a_dormant_credential(): void
    {
        $user = User::factory()->create();
        $passkeyId = DB::table('passkeys')->insertGetId([
            'user_id' => $user->id,
            'name' => 'Dormant credential',
            'credential_id' => 'dormant-passkey-removal-test',
            'credential' => json_encode(['public_key' => 'opaque-preserved-value'], JSON_THROW_ON_ERROR),
            'last_used_at' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $originalCredential = (array) DB::table('passkeys')->find($passkeyId);
        $originalUser = $user->getAttributes();

        $this->post('/passkeys/login', [
            'credential' => ['id' => 'dormant-passkey-removal-test'],
        ])->assertNotFound();
        $this->assertGuest();

        $this->actingAs($user);

        foreach ([
            ['GET', '/passkeys/confirm/options'],
            ['POST', '/passkeys/confirm'],
            ['GET', '/user/passkeys/options'],
            ['POST', '/user/passkeys'],
            ['PATCH', "/user/passkeys/{$passkeyId}"],
            ['DELETE', "/user/passkeys/{$passkeyId}"],
        ] as [$method, $uri]) {
            $this->assertContains($this->call($method, $uri)->getStatusCode(), [404, 405]);
        }

        $this->assertEquals($originalCredential, (array) DB::table('passkeys')->find($passkeyId));
        $this->assertEquals($originalUser, $user->fresh()->getAttributes());
    }

    public function test_security_settings_expose_no_passkey_props_or_metadata(): void
    {
        $user = User::factory()->create();
        DB::table('passkeys')->insert([
            'user_id' => $user->id,
            'name' => 'Metadata must stay private',
            'credential_id' => 'hidden-passkey-metadata',
            'credential' => json_encode(['public_key' => 'never-expose-this-value'], JSON_THROW_ON_ERROR),
            'last_used_at' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($user)
            ->withSession(['auth.password_confirmed_at' => time()])
            ->get(route('security.edit'))
            ->assertOk()
            ->assertDontSee('Metadata must stay private')
            ->assertDontSee('hidden-passkey-metadata')
            ->assertDontSee('never-expose-this-value')
            ->assertInertia(fn ($page) => $page
                ->component('settings/Security')
                ->missing('canManagePasskeys')
                ->missing('passkeys')
                ->where('twoFactorSettingsLocked', true));
    }

    public function test_passkey_frontend_is_removed_while_historical_storage_is_retained(): void
    {
        foreach ([
            'js/components/ManagePasskeys.vue',
            'js/components/PasskeyItem.vue',
            'js/components/PasskeyRegister.vue',
            'js/components/PasskeyVerify.vue',
        ] as $component) {
            $this->assertFileDoesNotExist(resource_path($component));
        }

        foreach ([
            'js/pages/auth/Login.vue',
            'js/pages/auth/ConfirmPassword.vue',
            'js/pages/settings/Security.vue',
        ] as $page) {
            $this->assertStringNotContainsStringIgnoringCase(
                'passkey',
                file_get_contents(resource_path($page)),
            );
        }

        $this->assertFalse(method_exists(User::class, 'passkeys'));
        $this->assertFileExists(database_path('migrations/2024_01_01_000000_create_passkeys_table.php'));
        $this->assertTrue(Schema::hasTable('passkeys'));
        $this->assertTrue(Schema::hasColumns('passkeys', [
            'id',
            'user_id',
            'name',
            'credential_id',
            'credential',
            'last_used_at',
            'created_at',
            'updated_at',
        ]));
    }
}
