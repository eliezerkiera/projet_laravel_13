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
            $refreshToken->revoke();

            throw ValidationException::withMessages([
                'refresh_token' => [__('auth.refresh_token_expired')],
            ]);
        }

        $user = $refreshToken->user;

        // Revoke the old refresh token
        $refreshToken->revoke();

        // Revoke all existing Sanctum tokens for this user
        $user->tokens()->delete();

        // Create new Sanctum access token
        $deviceName = $refreshToken->device_name ?: 'Web/Mobile App';
        $newAccessToken = $user->createToken($deviceName)->plainTextToken;

        // Create new refresh token (rotation)
        $newRefreshToken = RefreshToken::create([
            'user_id' => $user->id,
            'token' => Str::random(64),
            'device_name' => $deviceName,
            'expires_at' => now()->addMonth(),
            'revoked' => false,
        ]);

        $user->load(['language', 'country']);

        return response()->json([
            'message' => __('auth.token_refreshed'),
            'access_token' => $newAccessToken,
            'refresh_token' => $newRefreshToken->token,
            'token_type' => 'Bearer',
            'user' => new UserResource($user),
        ]);
    }
}
