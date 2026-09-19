<?php

namespace App\Auth;

use Illuminate\Cache\RateLimiter;
use Illuminate\Contracts\Cache\Factory as CacheFactory;
use Illuminate\Contracts\Cache\Repository;
use Illuminate\Http\Request;
use RuntimeException;

final class LoginThrottle
{
    public const PRIMARY_MAX_ATTEMPTS = 5;

    public const PRIMARY_DECAY_SECONDS = 15 * 60;

    public const IP_MAX_ATTEMPTS = 20;

    public const IP_DECAY_SECONDS = 60 * 60;

    private const GENERATION_CACHE_KEY = 'login:password:namespace-generation:v1';

    // A non-null TTL lets supported stores initialize atomically. Ten years is
    // effectively durable while remaining vastly longer than any limiter TTL.
    private const GENERATION_TTL_SECONDS = 10 * 365 * 24 * 60 * 60;

    private Repository $cache;

    public function __construct(
        private RateLimiter $limiter,
        CacheFactory $cache,
    ) {
        $this->cache = $cache->store(config('cache.limiter'));
    }

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
        return $this->emailIpKeyForGeneration($email, $ip, $this->currentGeneration());
    }

    public function ipKey(string $ip): string
    {
        return $this->ipKeyForGeneration($ip, $this->currentGeneration());
    }

    public function currentGeneration(): string
    {
        $generation = $this->cache->get(self::GENERATION_CACHE_KEY);

        if (is_string($generation) && $generation !== '') {
            return $generation;
        }

        $generation = $this->newGeneration();

        if ($this->cache->add(
            self::GENERATION_CACHE_KEY,
            $generation,
            self::GENERATION_TTL_SECONDS,
        )) {
            return $generation;
        }

        $storedGeneration = $this->cache->get(self::GENERATION_CACHE_KEY);

        if (! is_string($storedGeneration) || $storedGeneration === '') {
            throw new RuntimeException('Unable to initialize the login-throttle namespace.');
        }

        return $storedGeneration;
    }

    public function rotateGeneration(): void
    {
        if (! $this->cache->forever(self::GENERATION_CACHE_KEY, $this->newGeneration())) {
            throw new RuntimeException('Unable to rotate the login-throttle namespace.');
        }
    }

    private function emailIpKeyForGeneration(string $email, string $ip, string $generation): string
    {
        return "login:password:v2:{$generation}:email-ip:".hash(
            'sha256',
            $this->normalizeEmail($email).'|'.$ip,
        );
    }

    private function ipKeyForGeneration(string $ip, string $generation): string
    {
        return "login:password:v2:{$generation}:ip:".hash('sha256', $ip);
    }

    public function retryAfter(Request $request): int
    {
        $email = (string) $request->input('email');
        $ip = $this->resolvedIp($request);
        $generation = $this->currentGeneration();
        $blockedFor = [];
        $emailIpKey = $this->emailIpKeyForGeneration($email, $ip, $generation);
        $ipKey = $this->ipKeyForGeneration($ip, $generation);

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
        $generation = $this->currentGeneration();

        $this->limiter->hit(
            $this->emailIpKeyForGeneration($email, $ip, $generation),
            self::PRIMARY_DECAY_SECONDS,
        );
        $this->limiter->hit(
            $this->ipKeyForGeneration($ip, $generation),
            self::IP_DECAY_SECONDS,
        );
    }

    public function clearPrimary(Request $request): void
    {
        $this->limiter->clear($this->emailIpKeyForGeneration(
            (string) $request->input('email'),
            $this->resolvedIp($request),
            $this->currentGeneration(),
        ));
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

    private function newGeneration(): string
    {
        return bin2hex(random_bytes(32));
    }
}
