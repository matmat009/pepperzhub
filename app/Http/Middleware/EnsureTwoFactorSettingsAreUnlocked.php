<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureTwoFactorSettingsAreUnlocked
{
    /** @var list<string> */
    public const MANAGEMENT_ROUTES = [
        'two-factor.enable',
        'two-factor.confirm',
        'two-factor.disable',
        'two-factor.qr-code',
        'two-factor.secret-key',
        'two-factor.recovery-codes',
        'two-factor.regenerate-recovery-codes',
    ];

    /**
     * Block only authenticated 2FA management requests. Guests continue to
     * Fortify's auth middleware so the lock does not replace access control.
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (config('fortify.two_factor_settings_locked')
            && $request->user()
            && in_array($request->route()?->getName(), self::MANAGEMENT_ROUTES, true)) {
            $message = 'Two-factor authentication settings are locked.';

            return $request->expectsJson()
                ? response()->json(['message' => $message], 403)
                : response($message, 403);
        }

        return $next($request);
    }
}
