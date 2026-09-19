<?php

namespace App\Providers;

/* @chisel-registration */

use App\Actions\Fortify\CreateNewUser;
/* @end-chisel-registration */
use App\Actions\Fortify\ResetUserPassword;
use App\Auth\Actions\AttemptToAuthenticate;
use App\Auth\Actions\EnsureLoginIsNotThrottled;
use App\Auth\Actions\RedirectIfTwoFactorAuthenticatable;
use App\Http\Responses\PasswordResetLinkResponse;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;
use Laravel\Fortify\Actions\CanonicalizeUsername;
use Laravel\Fortify\Actions\PrepareAuthenticatedSession;
use Laravel\Fortify\Contracts\FailedPasswordResetLinkRequestResponse;
use Laravel\Fortify\Contracts\SuccessfulPasswordResetLinkRequestResponse;
use Laravel\Fortify\Features;
use Laravel\Fortify\Fortify;

class FortifyServiceProvider extends ServiceProvider
{
    // The broker still permits only one notification per account each minute.
    // These outer limits absorb bursts and cap address rotation from one IP.
    private const PASSWORD_RESET_EMAIL_ATTEMPTS_PER_MINUTE = 5;

    private const PASSWORD_RESET_IP_ATTEMPTS_PER_HOUR = 20;

    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(
            FailedPasswordResetLinkRequestResponse::class,
            PasswordResetLinkResponse::class,
        );
        $this->app->bind(
            SuccessfulPasswordResetLinkRequestResponse::class,
            PasswordResetLinkResponse::class,
        );
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureActions();
        $this->configureViews();
        $this->configureRateLimiting();
    }

    /**
     * Configure Fortify actions.
     */
    private function configureActions(): void
    {
        Fortify::resetUserPasswordsUsing(ResetUserPassword::class);
        Fortify::authenticateThrough(fn () => [
            EnsureLoginIsNotThrottled::class,
            config('fortify.lowercase_usernames') ? CanonicalizeUsername::class : null,
            Features::enabled(Features::twoFactorAuthentication())
                ? RedirectIfTwoFactorAuthenticatable::class
                : null,
            AttemptToAuthenticate::class,
            PrepareAuthenticatedSession::class,
        ]);
        /* @chisel-registration */
        Fortify::createUsersUsing(CreateNewUser::class);
        /* @end-chisel-registration */
    }

    /**
     * Configure Fortify views.
     */
    private function configureViews(): void
    {
        Fortify::loginView(function (Request $request) {
            $canResetPassword = config('fortify.password_reset_enabled')
                && Features::enabled(Features::resetPasswords());

            return Inertia::render('auth/Login', [
                'canResetPassword' => $canResetPassword,
                'resetPasswordUrl' => $canResetPassword ? route('password.request') : null,
                'status' => $request->session()->get('status'),
            ]);
        });

        Fortify::resetPasswordView(fn (Request $request) => Inertia::render('auth/ResetPassword', [
            'email' => $request->email,
            'submitUrl' => route('password.update'),
            'token' => $request->route('token'),
            'passwordRules' => Password::defaults()->toPasswordRulesString(),
        ]));

        Fortify::requestPasswordResetLinkView(fn (Request $request) => Inertia::render('auth/ForgotPassword', [
            'status' => $request->session()->get('status'),
            'submitUrl' => route('password.email'),
        ]));

        /* @chisel-email-verification */
        Fortify::verifyEmailView(fn (Request $request) => Inertia::render('auth/VerifyEmail', [
            'status' => $request->session()->get('status'),
        ]));
        /* @end-chisel-email-verification */

        /* @chisel-registration */
        Fortify::registerView(fn () => Inertia::render('auth/Register', [
            'passwordRules' => Password::defaults()->toPasswordRulesString(),
        ]));
        /* @end-chisel-registration */

        /* @chisel-2fa */
        Fortify::twoFactorChallengeView(fn () => Inertia::render('auth/TwoFactorChallenge'));
        /* @end-chisel-2fa */

        /* @chisel-password-confirmation */
        Fortify::confirmPasswordView(fn () => Inertia::render('auth/ConfirmPassword'));
        /* @end-chisel-password-confirmation */
    }

    /**
     * Configure rate limiting.
     */
    private function configureRateLimiting(): void
    {
        /* @chisel-2fa */
        RateLimiter::for('two-factor', function (Request $request) {
            return Limit::perMinute(5)->by($request->session()->get('login.id'));
        });
        /* @end-chisel-2fa */

        foreach (['password-reset-link', 'password-reset'] as $limiter) {
            RateLimiter::for($limiter, function (Request $request) {
                $email = Str::lower(trim((string) $request->input(Fortify::email())));
                $emailHash = hash('sha256', $email);
                $ip = $request->ip() ?: 'unknown';
                $response = function (Request $request, array $headers) {
                    $message = sprintf(
                        'Too many password-reset requests. Please try again in %d seconds.',
                        (int) ($headers['Retry-After'] ?? 60),
                    );

                    if ($request->header('X-Inertia')) {
                        return back()
                            ->withErrors([Fortify::email() => $message])
                            ->withHeaders($headers);
                    }

                    return $request->expectsJson()
                        ? response()->json(['message' => $message], 429, $headers)
                        : response($message, 429, $headers);
                };

                return [
                    Limit::perMinute(self::PASSWORD_RESET_EMAIL_ATTEMPTS_PER_MINUTE)
                        ->by("email:{$emailHash}|ip:{$ip}")
                        ->response($response),
                    Limit::perHour(self::PASSWORD_RESET_IP_ATTEMPTS_PER_HOUR)
                        ->by("ip:{$ip}")
                        ->response($response),
                ];
            });
        }

    }
}
