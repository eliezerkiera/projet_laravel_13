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

        $otpService->generateAndSend(
            $request->email,
            VerificationCode::TYPE_PASSWORD_RESET,
            $user
        );

        return response()->json([
            'message' => __('auth.otp_sent'),
            'email' => $request->email,
        ]);
    }

    /**
     * Resend password reset OTP code.
     */
    public function resendCode(ForgotPasswordSendCodeRequest $request, OtpService $otpService): JsonResponse
    {
        $user = User::where('email', $request->email)->first();

        $otpService->generateAndSend(
            $request->email,
            VerificationCode::TYPE_PASSWORD_RESET,
            $user
        );

        return response()->json([
            'message' => __('auth.otp_sent'),
            'email' => $request->email,
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

        return response()->json([
            'message' => __('auth.password_reset_code_verified'),
            'reset_token' => $verification->token,
            'email' => $verification->email,
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
                'message' => __('auth.invalid_token'),
            ], 422);
        }

        $user = User::where('email', $verification->email)->firstOrFail();

        // Update to new hashed password
        $user->forceFill([
            'password' => $request->password,
        ])->save();

        // Revoke all existing sessions for security
        $user->tokens()->delete();

        // Consume the verification token
        $otpService->consume($verification);

        return response()->json([
            'message' => __('auth.password_reset_success'),
        ]);
    }
}
