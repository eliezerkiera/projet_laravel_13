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
            return Limit::perMinute(60)->by('auth-api:'.($request->user()?->getAuthIdentifier() ?? $request->ip()));
        });

        RateLimiter::for('auth-login', function (Request $request): Limit {
            return Limit::perMinute(5)->by('auth-login:'.$this->requestIdentifier($request));
        });

        RateLimiter::for('auth-otp-request', function (Request $request): Limit {
            return Limit::perMinute(5)->by('auth-otp-request:'.$this->requestIdentifier($request));
        });

        RateLimiter::for('auth-otp-verification', function (Request $request): Limit {
            return Limit::perMinute(5)->by('auth-otp-verification:'.$this->requestIdentifier($request));
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
