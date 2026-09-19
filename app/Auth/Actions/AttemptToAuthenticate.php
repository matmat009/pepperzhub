<?php

namespace App\Auth\Actions;

use App\Auth\LoginThrottle;
use Illuminate\Contracts\Auth\StatefulGuard;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Actions\AttemptToAuthenticate as FortifyAttemptToAuthenticate;
use Laravel\Fortify\Fortify;
use Laravel\Fortify\LoginRateLimiter;

final class AttemptToAuthenticate extends FortifyAttemptToAuthenticate
{
    public function __construct(
        StatefulGuard $guard,
        LoginRateLimiter $limiter,
        private LoginThrottle $throttle,
    ) {
        parent::__construct($guard, $limiter);
    }

    public function handle($request, $next)
    {
        $response = parent::handle($request, $next);

        $this->throttle->clearPrimary($request);

        return $response;
    }

    protected function throwFailedAuthenticationException($request): never
    {
        $this->throttle->recordFailure($request);

        throw ValidationException::withMessages([
            Fortify::username() => ['The provided credentials are incorrect.'],
        ]);
    }
}
