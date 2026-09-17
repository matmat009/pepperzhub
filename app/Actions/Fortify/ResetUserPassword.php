<?php

namespace App\Actions\Fortify;

use App\Auth\DatabaseUserSessionRevoker;
use App\Concerns\PasswordValidationRules;
use App\Models\User;
use Illuminate\Support\Facades\Validator;
use Laravel\Fortify\Contracts\ResetsUserPasswords;

class ResetUserPassword implements ResetsUserPasswords
{
    use PasswordValidationRules;

    public function __construct(private DatabaseUserSessionRevoker $sessions) {}

    /**
     * Validate and reset the user's forgotten password.
     *
     * @param  array<string, string>  $input
     */
    public function reset(User $user, array $input): void
    {
        Validator::make($input, [
            'password' => $this->passwordRules(),
        ])->validate();

        $this->sessions->revokeWithinPasswordChange($user, function () use ($input, $user): void {
            $user->forceFill([
                'password' => $input['password'],
            ])->save();
        });
    }
}
