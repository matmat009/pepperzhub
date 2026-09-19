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
    protected $description = 'Clear one targeted administrator login throttle';

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

        $ip = $this->ask('Affected client IP address');

        if (! is_string($ip) || filter_var($ip, FILTER_VALIDATE_IP) === false) {
            $this->error('A valid IP address is required.');

            return self::FAILURE;
        }

        if (! $this->confirm('Clear only this administrator login throttle?')) {
            $this->warn('Login throttle reset cancelled.');

            return self::FAILURE;
        }

        try {
            $hadAttempts = $this->throttle->clear($email, $ip);

            Log::notice('Developer login throttle reset completed.', [
                'user_id' => $user->getKey(),
                'email_hash' => $this->throttle->emailIdentifier($email),
                'ip_network' => $this->throttle->minimizedIp($ip),
                'had_attempts' => $hadAttempts,
            ]);
        } catch (Throwable) {
            Log::error('Developer login throttle reset failed.', [
                'user_id' => $user->getKey(),
                'email_hash' => $this->throttle->emailIdentifier($email),
                'ip_network' => $this->throttle->minimizedIp($ip),
            ]);
            $this->error('Login throttle reset failed. No account data was changed.');

            return self::FAILURE;
        }

        if (! $hadAttempts) {
            $this->info('No matching login throttle was active.');

            return self::SUCCESS;
        }

        $this->info('Login throttle cleared. Normal authentication is still required.');

        return self::SUCCESS;
    }
}
