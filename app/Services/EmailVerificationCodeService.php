<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Notification;
use App\Notifications\EmailVerificationCodeNotification;

class EmailVerificationCodeService
{
    public const CODE_TTL_MINUTES = 15;

    /**
     * Génère un code à 6 chiffres, le stocke et l'envoie par email
     */
    public function send(User $user): void
    {
        $code = (string) random_int(100000, 999999);

        // Stocke le hash du code (sécurité)
        Cache::put(
            $this->cacheKey($user),
            [
                'code'       => hash('sha256', $code),
                'attempts'   => 0,
            ],
            now()->addMinutes(self::CODE_TTL_MINUTES)
        );

        $user->notify(new EmailVerificationCodeNotification($code));
    }

    /**
     * Vérifie le code saisi par l'utilisateur
     */
    public function verify(User $user, string $code): bool
    {
        $payload = Cache::get($this->cacheKey($user));

        if (!$payload) {
            return false; // expiré ou inexistant
        }

        // Limite de tentatives (anti brute-force)
        if (($payload['attempts'] ?? 0) >= 5) {
            Cache::forget($this->cacheKey($user));
            return false;
        }

        $isValid = hash_equals($payload['code'], hash('sha256', $code));

        if (!$isValid) {
            $payload['attempts'] = ($payload['attempts'] ?? 0) + 1;
            Cache::put($this->cacheKey($user), $payload, now()->addMinutes(self::CODE_TTL_MINUTES));
            return false;
        }

        // Code valide → on le supprime
        Cache::forget($this->cacheKey($user));

        return true;
    }

    private function cacheKey(User $user): string
    {
        return "email_verification_code:{$user->id}";
    }
}