<?php

namespace App\Http\Controllers\Settings;

use App\Auth\DatabaseUserSessionRevoker;
use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\PasswordUpdateRequest;
use App\Http\Requests\Settings\TwoFactorAuthenticationRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;
use Inertia\Response;
use Laravel\Fortify\Features;

class SecurityController extends Controller
{
    /**
     * Show the user's security settings page.
     */
    public function edit(TwoFactorAuthenticationRequest $request): Response
    {
        $twoFactorAvailable = Features::canManageTwoFactorAuthentication();
        $twoFactorSettingsLocked = (bool) config('fortify.two_factor_settings_locked');
        $props = [
            /* @chisel-2fa */
            'twoFactorAvailable' => $twoFactorAvailable,
            'canManageTwoFactor' => $twoFactorAvailable && ! $twoFactorSettingsLocked,
            'twoFactorSettingsLocked' => $twoFactorAvailable && $twoFactorSettingsLocked,
            /* @end-chisel-2fa */
            'passwordRules' => Password::defaults()->toPasswordRulesString(),
        ];

        /* @chisel-2fa */
        if ($twoFactorAvailable) {
            if (! $twoFactorSettingsLocked) {
                $request->ensureStateIsValid();
            }

            $props['twoFactorStatus'] = $this->twoFactorStatus($request->user());
            $props['twoFactorEnabled'] = $request->user()->hasEnabledTwoFactorAuthentication();
            $props['requiresConfirmation'] = Features::optionEnabled(Features::twoFactorAuthentication(), 'confirm');
        }
        /* @end-chisel-2fa */

        return Inertia::render('settings/Security', $props);
    }

    private function twoFactorStatus(User $user): string
    {
        if ($user->hasEnabledTwoFactorAuthentication()) {
            return 'enabled';
        }

        return $user->two_factor_secret === null ? 'disabled' : 'pending';
    }

    /**
     * Update the user's password.
     */
    public function update(
        PasswordUpdateRequest $request,
        DatabaseUserSessionRevoker $sessions,
    ): RedirectResponse {
        $user = $request->user();

        $sessions->revokeWithinPasswordChange(
            $user,
            function () use ($request, $user): void {
                $user->forceFill([
                    'password' => $request->validated('password'),
                ]);
                $user->setRememberToken(Str::random(60));
                $user->save();
            },
            $request->session()->getId(),
        );

        $request->session()->regenerate(true);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Password updated.')]);

        return back();
    }
}
