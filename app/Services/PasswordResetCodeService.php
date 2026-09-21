<?php

namespace App\Services;

use App\Models\User;
use App\Notifications\PasswordResetCodeNotification;
use Illuminate\Support\Facades\Cache;

class PasswordResetCodeService
{
    public const CODE_TTL_MINUTES = 15;

    public function send(User $user): void
    {
        $code = (string) random_int(100000, 999999);

        Cache::put(
            $this->cacheKey($user),
            [
                'code'     => hash('sha256', $code),
                'attempts' => 0,
            ],
            now()->addMinutes(self::CODE_TTL_MINUTES)
        );

        $user->notify(new PasswordResetCodeNotification($code));
    }

    public function verify(User $user, string $code): bool
    {
        $payload = Cache::get($this->cacheKey($user));

        if (!$payload) {
            return false;
        }

        if (($payload['attempts'] ?? 0) >= 5) {
            Cache::forget($this->cacheKey($user));
            return false;
        }

        $isValid = hash_equals($payload['code'], hash('sha256', $code));

        if (!$isValid) {
            $payload['attempts'] = ($payload['attempts'] ?? 0) + 1;
            Cache::put(
                $this->cacheKey($user),
                $payload,
                now()->addMinutes(self::CODE_TTL_MINUTES)
            );
            return false;
        }

        Cache::forget($this->cacheKey($user));

        return true;
    }

    private function cacheKey(User $user): string
    {
        return "password_reset_code:{$user->id}";
    }
}