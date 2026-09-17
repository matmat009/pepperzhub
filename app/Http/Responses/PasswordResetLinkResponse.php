<?php

namespace App\Http\Responses;

use Illuminate\Http\JsonResponse;
use Laravel\Fortify\Contracts\FailedPasswordResetLinkRequestResponse;
use Laravel\Fortify\Contracts\SuccessfulPasswordResetLinkRequestResponse;
use Symfony\Component\HttpFoundation\Response;

class PasswordResetLinkResponse implements FailedPasswordResetLinkRequestResponse, SuccessfulPasswordResetLinkRequestResponse
{
    public const MESSAGE = 'If an account exists with that email, we’ll send a password-reset link.';

    /**
     * Fortify supplies its internal broker status while resolving both response
     * contracts. It is deliberately ignored so no status can reveal an account.
     */
    public function __construct(string $status = '')
    {
        //
    }

    public function toResponse($request): Response
    {
        return $request->wantsJson()
            ? new JsonResponse(['message' => self::MESSAGE])
            : back()->with('status', self::MESSAGE);
    }
}
