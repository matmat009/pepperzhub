<?php

namespace App\Http\Middleware;

use App\Http\Responses\PasswordResetLinkResponse;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;

class ProtectPasswordResetRoutes
{
    public function __construct(private ThrottleRequests $throttle) {}

    public function handle(Request $request, Closure $next): Response
    {
        return match ($request->route()?->getName()) {
            'password.email' => $this->throttle->handle(
                $request,
                fn (Request $request): Response => $this->requestResetLink($request, $next),
                'password-reset-link',
            ),
            'password.update' => $this->throttle->handle($request, $next, 'password-reset'),
            default => $next($request),
        };
    }

    /**
     * Keep transport details out of both the public response and application
     * logs: exception messages may contain server or credential information.
     */
    private function requestResetLink(Request $request, Closure $next): Response
    {
        try {
            return $next($request);
        } catch (TransportExceptionInterface $exception) {
            Log::error('Password-reset notification transport failed.', [
                'exception_class' => $exception::class,
            ]);

            return app(PasswordResetLinkResponse::class)->toResponse($request);
        }
    }
}
