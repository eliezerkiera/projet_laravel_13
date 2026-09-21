<?php

namespace App\Services;

use App\Enums\TokenAbility;
use App\Models\User;

class TokenService
{
    /**
     * Durées en minutes
     */
    public const ACCESS_TOKEN_EXPIRATION = 60;        // 60 minutes
    public const REFRESH_TOKEN_EXPIRATION = 60 * 24 * 30; // 30 jours

    /**
     * Crée une paire Access Token + Refresh Token
     */
    public function createTokenPair(User $user, string $deviceName): array
    {
        // Access Token (court)
        $accessToken = $user->createToken(
            name: $deviceName,
            abilities: [TokenAbility::ACCESS_API->value],
            expiresAt: now()->addMinutes(self::ACCESS_TOKEN_EXPIRATION)
        );

        // Refresh Token (long)
        $refreshToken = $user->createToken(
            name: $deviceName . ' - Refresh',
            abilities: [TokenAbility::ISSUE_ACCESS_TOKEN->value],
            expiresAt: now()->addMinutes(self::REFRESH_TOKEN_EXPIRATION)
        );

        return [
            'access_token'  => $accessToken->plainTextToken,
            'refresh_token' => $refreshToken->plainTextToken,
            'token_type'    => 'Bearer',
            'expires_in'    => self::ACCESS_TOKEN_EXPIRATION * 60, // en secondes
        ];
    }

    /**
     * Rotation : révoque les anciens tokens du device et en crée une nouvelle paire
     */
    public function rotateTokens(User $user, string $deviceName): array
    {
        // On supprime tous les tokens liés à cet appareil
        $user->tokens()
            ->where('name', $deviceName)
            ->orWhere('name', $deviceName . ' - Refresh')
            ->delete();

        return $this->createTokenPair($user, $deviceName);
    }
}