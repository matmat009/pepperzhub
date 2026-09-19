<?php

namespace App\Console\Commands;

use App\Auth\DatabaseUserSessionRevoker;
use App\Concerns\PasswordValidationRules;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Throwable;

class ResetAdminPasswordCommand extends Command
{
    use PasswordValidationRules;

    /** @var string */
    protected $signature = 'admin:reset-password';

    /** @var string */
    protected $description = 'Reset an existing admin password after identity verification';

    public function __construct(private DatabaseUserSessionRevoker $sessions)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $email = $this->ask('Exact admin email');
        $user = is_string($email) ? User::query()->where('email', $email)->first() : null;

        if (! $user || ! hash_equals($user->email, $email)) {
            $this->error('No matching admin account was found.');

            return self::FAILURE;
        }

        $password = $this->secret('New password');
        $confirmation = $this->secret('Confirm new password');

        try {
            $validator = Validator::make([
                'password' => $password,
                'password_confirmation' => $confirmation,
            ], [
                'password' => $this->passwordRules(),
            ]);

            if ($validator->fails()) {
                $this->error($validator->errors()->first('password'));

                return self::FAILURE;
            }

            if (! $this->confirm('Reset this admin password and sign out all sessions?')) {
                $this->warn('Password reset cancelled.');

                return self::FAILURE;
            }

            $this->sessions->revokeWithinPasswordChange($user, function () use ($password, $user): void {
                $user->forceFill(['password' => $password]);
                $user->setRememberToken(Str::random(60));
                $user->save();
            });
        } catch (Throwable) {
            $this->error('Password reset failed. No changes were applied.');

            return self::FAILURE;
        } finally {
            unset($password, $confirmation);
        }

        $this->info('Admin password reset successfully.');

        return self::SUCCESS;
    }
}
