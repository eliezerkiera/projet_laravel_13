<?php

namespace App\Http\Controllers\Api\V2\Auth;

use App\Exceptions\ApiException;
use App\Http\Controllers\Controller;
use App\Http\Requests\V2\RegisterCompleteRequest;
use App\Http\Requests\V2\RegisterSendCodeRequest;
use App\Http\Requests\V2\RegisterVerifyCodeRequest;
use App\Http\Resources\V2\UserResource;
use App\Models\RefreshToken;
use App\Models\User;
use App\Models\VerificationCode;
use App\Services\OtpService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Str;

class RegisterController extends Controller
{
    /**
     * Send OTP verification code to the prospective registrant's email.
     */
    public function sendCode(RegisterSendCodeRequest $request, OtpService $otpService): JsonResponse
    {
        // Anti-enumeration: check if account already exists
        $existingUser = User::where('email', $request->email)->first();

        if ($existingUser) {
            // Don't send OTP, but return same response
            // In production, send email informing that account exists
            $expiresIn = $otpService->getExpirationMinutes() * 60;
            $resendAvailableIn = $otpService->getResendCooldownSeconds();

            return response()->json([
                'message' => __('auth.otp_sent'),
                'email' => $request->email,
                'expires_in' => $expiresIn,
                'resend_available_in' => $resendAvailableIn,
            ]);
        }

        $otpService->generateAndSend($request->email, VerificationCode::TYPE_REGISTER);

        $expiresIn = $otpService->getExpirationMinutes() * 60;
        $resendAvailableIn = $otpService->getResendCooldownSeconds();

        return response()->json([
            'message' => __('auth.otp_sent'),
            'email' => $request->email,
            'expires_in' => $expiresIn,
            'resend_available_in' => $resendAvailableIn,
        ]);
    }

    /**
     * Resend a fresh OTP verification code for registration.
     */
    public function resendCode(RegisterSendCodeRequest $request, OtpService $otpService): JsonResponse
    {
        // Anti-enumeration: check if account already exists
        $existingUser = User::where('email', $request->email)->first();

        if ($existingUser) {
            // Don't send OTP, but return same response
            $expiresIn = $otpService->getExpirationMinutes() * 60;
            $resendAvailableIn = $otpService->getResendCooldownSeconds();

            return response()->json([
                'message' => __('auth.otp_sent'),
                'email' => $request->email,
                'expires_in' => $expiresIn,
                'resend_available_in' => $resendAvailableIn,
            ]);
        }

        $otpService->generateAndSend($request->email, VerificationCode::TYPE_REGISTER);

        $expiresIn = $otpService->getExpirationMinutes() * 60;
        $resendAvailableIn = $otpService->getResendCooldownSeconds();

        return response()->json([
            'message' => __('auth.otp_sent'),
            'email' => $request->email,
            'expires_in' => $expiresIn,
            'resend_available_in' => $resendAvailableIn,
        ]);
    }

    /**
     * Verify the 6-digit OTP code and issue a temporary verification token.
     */
    public function verifyCode(RegisterVerifyCodeRequest $request, OtpService $otpService): JsonResponse
    {
        $verification = $otpService->verifyCode(
            $request->email,
            $request->code,
            VerificationCode::TYPE_REGISTER
        );

        $expiresIn = $otpService->getVerificationTokenTtlMinutes() * 60;

        return response()->json([
            'message' => __('auth.verification_code_confirmed'),
            'verification_token' => $verification->token,
            'email' => $verification->email,
            'expires_in' => $expiresIn,
        ]);
    }

    /**
     * Complete the registration profile and authenticate the new user.
     */
    public function complete(RegisterCompleteRequest $request, OtpService $otpService): JsonResponse
    {
        // Retrieve and validate the temporary verification token
        $verification = $otpService->findValidByToken(
            $request->verification_token,
            VerificationCode::TYPE_REGISTER
        );

        if (! $verification) {
            throw ApiException::forField('invalid_token', 'verification_token', __('auth.invalid_token'));
        }

        // Create the user with the verified email
        $user = User::create([
            'first_name' => $request->first_name,
            'last_name' => $request->last_name,
            'email' => $verification->email,
            'password' => $request->password,
            'country_id' => $request->country_id,
            'language_id' => $request->language_id,
        ]);

        $user->markEmailAsVerified();

        // Invalidate the used token
        $otpService->consume($verification);

        // Issue Sanctum Personal Access Token with explicit expiration
        $deviceName = $request->device_name ?: 'Web/Mobile App';
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
            'message' => __('auth.registration_completed'),
            'access_token' => $token,
            'refresh_token' => $refreshToken->token,
            'token_type' => 'Bearer',
            'expires_in' => $expiresIn,
            'refresh_expires_in' => $refreshExpiresIn,
            'user' => new UserResource($user),
        ], 201);
    }
}
