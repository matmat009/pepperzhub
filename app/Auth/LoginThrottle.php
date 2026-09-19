<?php

namespace App\Auth;

use Illuminate\Cache\RateLimiter;
use Illuminate\Http\Request;

final class LoginThrottle
{
    public const PRIMARY_MAX_ATTEMPTS = 5;

    public const PRIMARY_DECAY_SECONDS = 15 * 60;

    public const IP_MAX_ATTEMPTS = 20;

    public const IP_DECAY_SECONDS = 60 * 60;

    public function __construct(private RateLimiter $limiter) {}

    public function normalizeEmail(string $email): string
    {
        return mb_strtolower(trim($email), 'UTF-8');
    }

    public function emailIdentifier(string $email): string
    {
        return hash('sha256', $this->normalizeEmail($email));
    }

    public function emailIpKey(string $email, string $ip): string
    {
        return 'login:password:email-ip:v1:'.hash(
            'sha256',
            $this->normalizeEmail($email).'|'.$ip,
        );
    }

    public function ipKey(string $ip): string
    {
        return 'login:password:ip:v1:'.hash('sha256', $ip);
    }

    public function retryAfter(Request $request): int
    {
        $email = (string) $request->input('email');
        $ip = $this->resolvedIp($request);
        $blockedFor = [];
        $emailIpKey = $this->emailIpKey($email, $ip);
        $ipKey = $this->ipKey($ip);

        if ($this->limiter->tooManyAttempts($emailIpKey, self::PRIMARY_MAX_ATTEMPTS)) {
            $blockedFor[] = $this->limiter->availableIn($emailIpKey);
        }

        if ($this->limiter->tooManyAttempts($ipKey, self::IP_MAX_ATTEMPTS)) {
            $blockedFor[] = $this->limiter->availableIn($ipKey);
        }

        return $blockedFor === [] ? 0 : max(1, ...$blockedFor);
    }

    public function recordFailure(Request $request): void
    {
        $email = (string) $request->input('email');
        $ip = $this->resolvedIp($request);

        $this->limiter->hit(
            $this->emailIpKey($email, $ip),
            self::PRIMARY_DECAY_SECONDS,
        );
        $this->limiter->hit($this->ipKey($ip), self::IP_DECAY_SECONDS);
    }

    public function clearPrimary(Request $request): void
    {
        $this->limiter->clear($this->emailIpKey(
            (string) $request->input('email'),
            $this->resolvedIp($request),
        ));
    }

    public function clear(string $email, string $ip): bool
    {
        $emailIpKey = $this->emailIpKey($email, $ip);
        $ipKey = $this->ipKey($ip);
        $hadAttempts = $this->limiter->attempts($emailIpKey) > 0
            || $this->limiter->attempts($ipKey) > 0;

        $this->limiter->clear($emailIpKey);
        $this->limiter->clear($ipKey);

        return $hadAttempts;
    }

    public function resolvedIp(Request $request): string
    {
        return $request->ip() ?: 'unknown';
    }

    public function minimizedIp(string $ip): string
    {
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            $segments = explode('.', $ip);
            $segments[3] = '0';

            return implode('.', $segments).'/24';
        }

        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
            $packed = inet_pton($ip);

            if ($packed !== false) {
                $network = inet_ntop(substr($packed, 0, 8).str_repeat("\0", 8));

                return $network === false ? 'unavailable' : $network.'/64';
            }
        }

        return 'unavailable';
    }
}
