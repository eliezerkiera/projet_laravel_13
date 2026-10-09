<?php

namespace App\Http\Controllers\Api\V2\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\V2\RefreshTokenRequest;
use App\Http\Resources\V2\UserResource;
use App\Models\RefreshToken;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class RefreshTokenController extends Controller
{
    /**
     * Refresh the access token using a valid refresh token.
     *
     *
     * @throws ValidationException
     */
    public function refresh(RefreshTokenRequest $request): JsonResponse
    {
        $refreshToken = RefreshToken::query()
            ->where('token', $request->refresh_token)
            ->where('revoked', false)
            ->first();

        if (! $refreshToken) {
            throw ValidationException::withMessages([
                'refresh_token' => [__('auth.invalid_refresh_token')],
            ]);
        }

        if ($refreshToken->isExpired()) {
            $refreshToken->revoke('expired');

            throw ValidationException::withMessages([
                'refresh_token' => [__('auth.refresh_token_expired')],
            ]);
        }

        // Check for reuse detection: if token was already rotated, revoke the family
        if ($refreshToken->rotated_at !== null) {
            \Log::warning('refresh_token_reuse_detected', [
                'user_id' => $refreshToken->user_id,
                'family_id' => $refreshToken->family_id,
            ]);

            // Revoke the entire family
            $refreshToken->revokeFamily();

            throw ValidationException::withMessages([
                'refresh_token' => [__('auth.invalid_refresh_token')],
            ]);
        }

        $user = $refreshToken->user;

        // Atomic rotation with lock
        return \DB::transaction(function () use ($refreshToken, $user) {
            // Lock the refresh token row
            $lockedToken = RefreshToken::query()
                ->where('id', $refreshToken->id)
                ->lockForUpdate()
                ->first();

            if (! $lockedToken || $lockedToken->revoked || $lockedToken->isExpired()) {
                throw ValidationException::withMessages([
                    'refresh_token' => [__('auth.invalid_refresh_token')],
                ]);
            }

            // Generate or inherit family_id
            $familyId = $lockedToken->family_id ?? (string) Str::uuid();

            // Mark old token as rotated
            $lockedToken->update([
                'revoked' => true,
                'revoked_reason' => 'rotated',
                'revoked_at' => now(),
                'rotated_at' => now(),
                'family_id' => $familyId,
            ]);

            // Revoke old access token for this device
            $user->tokens()
                ->where('name', $lockedToken->device_name)
                ->delete();

            // Create new Sanctum access token with explicit expiration
            $deviceName = $lockedToken->device_name ?: 'Web/Mobile App';
            $accessTokenTtl = config('auth_api.access_token_ttl_minutes', 60);
            $newAccessToken = $user->createToken(
                $deviceName,
                ['*'],
                now()->addMinutes($accessTokenTtl)
            )->plainTextToken;

            // Create new refresh token (rotation) with same family_id
            $refreshTokenTtlDays = config('auth_api.refresh_token_ttl_days', 30);
            $newRefreshToken = RefreshToken::create([
                'user_id' => $user->id,
                'token' => Str::random(64),
                'device_name' => $deviceName,
                'expires_at' => now()->addDays($refreshTokenTtlDays),
                'revoked' => false,
                'family_id' => $familyId,
            ]);

            $user->load(['language', 'country']);

            // Calculate expires_in in seconds
            $expiresIn = $accessTokenTtl * 60;
            $refreshExpiresIn = $refreshTokenTtlDays * 24 * 60 * 60;

            return response()->json([
                'message' => __('auth.token_refreshed'),
                'access_token' => $newAccessToken,
                'refresh_token' => $newRefreshToken->token,
                'token_type' => 'Bearer',
                'expires_in' => $expiresIn,
                'refresh_expires_in' => $refreshExpiresIn,
                'user' => new UserResource($user),
            ]);
        });
    }
}
