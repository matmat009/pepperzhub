<?php

namespace App\Auth\Actions;

use App\Auth\LoginThrottle;
use App\Http\Responses\LoginThrottleResponse;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

final class EnsureLoginIsNotThrottled
{
    public function __construct(
        private LoginThrottle $throttle,
        private LoginThrottleResponse $response,
    ) {}

    public function handle(Request $request, callable $next): mixed
    {
        $retryAfter = $this->throttle->retryAfter($request);

        if ($retryAfter === 0) {
            return $next($request);
        }

        event(new Lockout($request));

        Log::warning('Temporary password-login throttle triggered.', [
            'email_hash' => $this->throttle->emailIdentifier((string) $request->input('email')),
            'ip_network' => $this->throttle->minimizedIp($this->throttle->resolvedIp($request)),
            'retry_after_seconds' => $retryAfter,
        ]);

        return $this->response->toResponse($request, $retryAfter);
    }
}
