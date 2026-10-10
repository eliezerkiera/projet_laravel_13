<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        RateLimiter::for('auth-api', function (Request $request): Limit {
            return Limit::perMinute(config('auth_api.rate_limits.refresh.per_minute', 60))
                ->by('auth-api:'.($request->user()?->getAuthIdentifier() ?? $request->ip()));
        });

        RateLimiter::for('auth-login', function (Request $request): Limit {
            return Limit::perMinute(config('auth_api.rate_limits.login.per_minute', 5))
                ->by('auth-login:'.$this->requestIdentifier($request));
        });

        RateLimiter::for('auth-otp-request', function (Request $request): Limit {
            $limit = config('auth_api.rate_limits.otp_send', [
                'per_minute' => 1,
                'per_hour' => 5,
            ]);

            return Limit::perMinute($limit['per_minute'])
                ->by('auth-otp-request:'.$this->requestIdentifier($request));
        });

        RateLimiter::for('auth-otp-verification', function (Request $request): Limit {
            return Limit::perMinute(config('auth_api.rate_limits.otp_verify.per_minute', 5))
                ->by('auth-otp-verification:'.$this->requestIdentifier($request));
        });

        // New named rate limiters for Section H
        RateLimiter::for('otp-send', function (Request $request): Limit {
            $limit = config('auth_api.rate_limits.otp_send', [
                'per_minute' => 1,
                'per_hour' => 5,
            ]);

            return Limit::perMinute($limit['per_minute'])
                ->by('otp-send:'.$this->requestIdentifier($request));
        });

        RateLimiter::for('otp-verify', function (Request $request): Limit {
            // Authenticated users (e.g. profile email change verification) are
            // keyed by their user id plus IP; anonymous flows fall back to the
            // transport identifier (email/token plus IP).
            $key = $request->user('sanctum')?->getAuthIdentifier()
                ?? $this->requestIdentifier($request);

            return Limit::perMinute(config('auth_api.rate_limits.otp_verify.per_minute', 5))
                ->by('otp-verify:'.$key);
        });

        RateLimiter::for('login', function (Request $request): Limit {
            return Limit::perMinute(config('auth_api.rate_limits.login.per_minute', 5))
                ->by('login:'.$this->requestIdentifier($request));
        });

        RateLimiter::for('refresh', function (Request $request): Limit {
            return Limit::perMinute(config('auth_api.rate_limits.refresh.per_minute', 60))
                ->by('refresh:'.$request->ip());
        });

        RateLimiter::for('sensitive', function (Request $request): Limit {
            return Limit::perMinute(config('auth_api.rate_limits.sensitive.per_minute', 5))
                ->by('sensitive:'.($request->user()?->getAuthIdentifier() ?? $request->ip()));
        });
    }

    /**
     * Build a client-specific throttle key from the available auth-flow identifier and IP address.
     */
    private function requestIdentifier(Request $request): string
    {
        foreach (['email', 'challenge_token', 'verification_token', 'token'] as $field) {
            $value = $request->input($field);

            if (is_string($value) && $value !== '') {
                return Str::transliterate(Str::lower($value)).'|'.$request->ip();
            }
        }

        return (string) $request->ip();
    }
}
