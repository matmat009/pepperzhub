<?php

namespace App\Auth\Actions;

use App\Auth\LoginThrottle;
use Illuminate\Contracts\Auth\StatefulGuard;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Actions\RedirectIfTwoFactorAuthenticatable as FortifyRedirectIfTwoFactorAuthenticatable;
use Laravel\Fortify\Fortify;
use Laravel\Fortify\LoginRateLimiter;

final class RedirectIfTwoFactorAuthenticatable extends FortifyRedirectIfTwoFactorAuthenticatable
{
    public function __construct(
        StatefulGuard $guard,
        LoginRateLimiter $limiter,
        private LoginThrottle $throttle,
    ) {
        parent::__construct($guard, $limiter);
    }

    protected function throwFailedAuthenticationException($request): never
    {
        $this->throttle->recordFailure($request);

        throw ValidationException::withMessages([
            Fortify::username() => ['The provided credentials are incorrect.'],
        ]);
    }

    protected function twoFactorChallengeResponse($request, $user)
    {
        $this->throttle->clearPrimary($request);

        return parent::twoFactorChallengeResponse($request, $user);
    }
}
