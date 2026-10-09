<?php

namespace App\Http\Controllers\Api\V2\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\V2\ForgotPasswordSendCodeRequest;
use App\Http\Requests\V2\ForgotPasswordVerifyCodeRequest;
use App\Http\Requests\V2\ResetPasswordRequest;
use App\Models\User;
use App\Models\VerificationCode;
use App\Services\OtpService;
use Illuminate\Http\JsonResponse;

class ForgotPasswordController extends Controller
{
    /**
     * Send password reset OTP code to the requested email.
     */
    public function sendCode(ForgotPasswordSendCodeRequest $request, OtpService $otpService): JsonResponse
    {
        $user = User::where('email', $request->email)->first();

        // Anti-enumeration: always return same response
        // Only send OTP if user exists
        if ($user) {
            $otpService->generateAndSend(
                $request->email,
                VerificationCode::TYPE_PASSWORD_RESET,
                $user
            );
        }

        $expiresIn = $otpService->getExpirationMinutes() * 60;
        $resendAvailableIn = $otpService->getResendCooldownSeconds();

        return response()->json([
            'message' => 'If an account exists for this email, a verification code has been sent.',
            'email' => $request->email,
            'expires_in' => $expiresIn,
            'resend_available_in' => $resendAvailableIn,
        ]);
    }

    /**
     * Resend password reset OTP code.
     */
    public function resendCode(ForgotPasswordSendCodeRequest $request, OtpService $otpService): JsonResponse
    {
        $user = User::where('email', $request->email)->first();

        // Anti-enumeration: always return same response
        // Only send OTP if user exists
        if ($user) {
            $otpService->generateAndSend(
                $request->email,
                VerificationCode::TYPE_PASSWORD_RESET,
                $user
            );
        }

        $expiresIn = $otpService->getExpirationMinutes() * 60;
        $resendAvailableIn = $otpService->getResendCooldownSeconds();

        return response()->json([
            'message' => 'If an account exists for this email, a verification code has been sent.',
            'email' => $request->email,
            'expires_in' => $expiresIn,
            'resend_available_in' => $resendAvailableIn,
        ]);
    }

    /**
     * Verify password reset OTP code and return a secure reset token.
     */
    public function verifyCode(ForgotPasswordVerifyCodeRequest $request, OtpService $otpService): JsonResponse
    {
        $verification = $otpService->verifyCode(
            $request->email,
            $request->code,
            VerificationCode::TYPE_PASSWORD_RESET
        );

        $expiresIn = $otpService->getVerificationTokenTtlMinutes() * 60;

        return response()->json([
            'message' => __('auth.password_reset_code_verified'),
            'reset_token' => $verification->token,
            'email' => $verification->email,
            'expires_in' => $expiresIn,
        ]);
    }

    /**
     * Reset the user password using the valid reset token.
     */
    public function resetPassword(ResetPasswordRequest $request, OtpService $otpService): JsonResponse
    {
        $verification = $otpService->findValidByToken(
            $request->token,
            VerificationCode::TYPE_PASSWORD_RESET
        );

        if (! $verification) {
            return response()->json([
                'code' => 'reset_token_invalid',
                'message' => __('auth.invalid_token'),
            ], 422);
        }

        $user = User::where('email', $verification->email)->first();

        // Anti-enumeration: same response for non-existent email
        if (! $user) {
            return response()->json([
                'code' => 'otp_invalid',
                'message' => __('auth.otp_invalid'),
            ], 422);
        }

        // Update to new hashed password
        $user->forceFill([
            'password' => $request->password,
        ])->save();

        // Revoke all existing sessions for security
        $user->tokens()->delete();

        // Revoke all refresh tokens
        $user->refreshTokens()->update([
            'revoked' => true,
            'revoked_reason' => 'password_changed',
            'revoked_at' => now(),
        ]);

        // Consume the verification token
        $otpService->consume($verification);

        return response()->json([
            'message' => __('auth.password_reset_success'),
        ]);
    }
}
