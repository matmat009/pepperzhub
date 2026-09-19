<?php

namespace App\Console\Commands;

use App\Auth\LoginThrottle;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Throwable;

final class ClearAdminLoginThrottleCommand extends Command
{
    /** @var string */
    protected $signature = 'admin:clear-login-throttle';

    /** @var string */
    protected $description = 'Clear all temporary administrator login throttles';

    public function __construct(private LoginThrottle $throttle)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $email = $this->ask('Exact admin email');

        if (! is_string($email) || Validator::make(['email' => $email], [
            'email' => ['required', 'string', 'email'],
        ])->fails()) {
            $this->error('A valid email address is required.');

            return self::FAILURE;
        }

        $user = User::query()->where('email', $email)->first();

        if (! $user || ! hash_equals($user->email, $email)) {
            $this->error('No matching admin account was found.');

            return self::FAILURE;
        }

        if (! $this->confirm('Clear all temporary login limits?')) {
            $this->warn('Login throttle reset cancelled.');

            return self::FAILURE;
        }

        try {
            $this->throttle->rotateGeneration();

            Log::notice('Login-throttle namespace reset through authorized server command.', [
                'user_id' => $user->getKey(),
                'email_hash' => $this->throttle->emailIdentifier($email),
            ]);
        } catch (Throwable) {
            Log::error('Login-throttle namespace reset failed.', [
                'user_id' => $user->getKey(),
                'email_hash' => $this->throttle->emailIdentifier($email),
            ]);
            $this->error('Login throttle reset failed. No account data was changed.');

            return self::FAILURE;
        }

        $this->info('All temporary login limits cleared. Normal authentication is still required.');

        return self::SUCCESS;
    }
}
