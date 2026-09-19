<?php

namespace App\Http\Responses;

use Illuminate\Http\Request;
use Inertia\Inertia;
use Laravel\Fortify\Features;
use Laravel\Fortify\Fortify;
use Symfony\Component\HttpFoundation\Response;

final class LoginThrottleResponse
{
    public function toResponse(Request $request, int $retryAfter): Response
    {
        $message = "Too many sign-in attempts. Please try again in {$retryAfter} seconds.";
        $headers = ['Retry-After' => (string) $retryAfter];

        if ($request->header('X-Inertia')) {
            $canResetPassword = config('fortify.password_reset_enabled')
                && Features::enabled(Features::resetPasswords());

            return Inertia::render('auth/Login', [
                'canResetPassword' => $canResetPassword,
                'resetPasswordUrl' => $canResetPassword ? route('password.request') : null,
                'status' => $request->session()->get('status'),
                'errors' => [Fortify::username() => $message],
            ])->toResponse($request)->setStatusCode(429)->withHeaders($headers);
        }

        if ($request->expectsJson()) {
            return response()->json([
                'message' => $message,
                'errors' => [Fortify::username() => [$message]],
            ], 429, $headers);
        }

        return response($message, 429, $headers);
    }
}
