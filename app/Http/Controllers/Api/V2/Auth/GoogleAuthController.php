<?php

namespace App\Http\Controllers\Api\V2\Auth;

use App\Exceptions\ApiException;
use App\Http\Controllers\Controller;
use App\Http\Requests\V2\GoogleTokenRequest;
use App\Http\Resources\V2\UserResource;
use App\Models\RefreshToken;
use App\Models\User;
use App\Services\Auth\GoogleAuthService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Laravel\Socialite\Facades\Socialite;

class GoogleAuthController extends Controller
{
    /**
     * Redirect to Google OAuth page.
     * Returns the redirect URL in JSON for API clients.
     */
    public function redirect(): JsonResponse
    {
        $url = Socialite::driver('google')
            ->stateless()
            ->redirect()
            ->getTargetUrl();

        return response()->json([
            'redirect_url' => $url,
        ]);
    }

    /**
     * Handle Google OAuth callback.
     * Receives the code from Google and authenticates the user.
     */
    public function callback(Request $request, GoogleAuthService $googleAuthService): JsonResponse
    {
        try {
            $socialiteUser = Socialite::driver('google')
                ->stateless()
                ->user();
        } catch (\Exception $e) {
            throw ApiException::forField(
                'google_auth_failed',
                'code',
                'auth.google_auth_failed'
            );
        }

        $user = $googleAuthService->handleAuth($socialiteUser);

        return $this->issueTokens($user, $request);
    }

    /**
     * Handle Google token-based authentication.
     * Receives a Google access token and authenticates the user.
     */
    public function token(GoogleTokenRequest $request, GoogleAuthService $googleAuthService): JsonResponse
    {
        try {
            $socialiteUser = Socialite::driver('google')
                ->stateless()
                ->userFromToken($request->token);
        } catch (\Exception $e) {
            throw ApiException::forField(
                'google_token_invalid',
                'token',
                'auth.google_token_invalid'
            );
        }

        $user = $googleAuthService->handleAuth($socialiteUser);

        return $this->issueTokens($user, $request);
    }

    /**
     * Issue Sanctum access token and refresh token.
     */
    private function issueTokens(User $user, Request $request): JsonResponse
    {
        // Issue Sanctum Personal Access Token with explicit expiration
        $deviceName = $request->input('device_name', 'Google Auth');
        $accessTokenTtl = config('auth_api.access_token_ttl_minutes', 1440);
        $token = $user->createToken(
            $deviceName,
            ['*'],
            now()->addMinutes($accessTokenTtl)
        )->plainTextToken;

        // Create refresh token with family_id
        $refreshTokenTtlDays = config('auth_api.refresh_token_ttl_days', 30);
        $familyId = (string) Str::uuid();
        $refreshToken = RefreshToken::create([
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
            'message' => __('auth.login_success'),
            'access_token' => $token,
            'refresh_token' => $refreshToken->token,
            'token_type' => 'Bearer',
            'expires_in' => $expiresIn,
            'refresh_expires_in' => $refreshExpiresIn,
            'user' => new UserResource($user),
        ]);
    }
}
