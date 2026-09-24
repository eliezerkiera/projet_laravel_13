<?php

namespace App\Http\Controllers\Api\V2\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\V2\LoginRequest;
use App\Http\Requests\V2\LoginVerifyCodeRequest;
use App\Http\Resources\V2\UserResource;
use App\Models\User;
use App\Models\VerificationCode;
use App\Services\OtpService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class LoginController extends Controller
{
    /**
     * Authenticate user credentials and dispatch 2FA OTP code.
     *
     *
     * @throws ValidationException
     */
    public function login(LoginRequest $request, OtpService $otpService): JsonResponse
    {
        $user = User::query()
            ->where('email', $request->email)
            ->first();

        if (! $user || ! Hash::check($request->password, $user->password)) {
            throw ValidationException::withMessages([
                'email' => [__('auth.failed')],
            ]);
        }

        // Rule: Old email remains active, but if an email change was requested,
        // the user must verify the new email address before logging in.
        if ($user->pending_email !== null) {
            return response()->json([
                'message' => __('auth.pending_email_verification_required'),
                'email' => $user->email,
                'pending_email' => $user->pending_email,
            ], 403);
        }

        // Generate 6-digit OTP code and associate challenge token
        $verification = $otpService->generateAndSend(
            $user->email,
            VerificationCode::TYPE_LOGIN,
            $user
        );

        $challengeToken = Str::random(64);
        $verification->update(['token' => $challengeToken]);

        return response()->json([
            'message' => __('auth.login_otp_required'),
            'challenge_token' => $challengeToken,
            'email' => $user->email,
        ]);
    }

    /**
     * Resend 2FA login verification code.
     *
     *
     * @throws ValidationException
     */
    public function resendCode(Request $request, OtpService $otpService): JsonResponse
    {
        $request->validate([
            'challenge_token' => ['nullable', 'string'],
            'email' => ['nullable', 'email'],
        ]);

        $record = null;

        if ($request->filled('challenge_token')) {
            $record = VerificationCode::query()
                ->where('token', $request->challenge_token)
                ->where('type', VerificationCode::TYPE_LOGIN)
                ->whereNull('verified_at')
                ->first();
        } elseif ($request->filled('email')) {
            $record = VerificationCode::query()
                ->where('email', $request->email)
                ->where('type', VerificationCode::TYPE_LOGIN)
                ->whereNull('verified_at')
                ->latest('id')
                ->first();
        }

        if (! $record) {
            throw ValidationException::withMessages([
                'challenge_token' => [__('auth.invalid_token')],
            ]);
        }

        $user = $record->user ?? User::where('email', $record->email)->first();

        $newVerification = $otpService->generateAndSend(
            $record->email,
            VerificationCode::TYPE_LOGIN,
            $user
        );

        $newVerification->update(['token' => $record->token]);

        return response()->json([
            'message' => __('auth.otp_sent'),
            'challenge_token' => $record->token,
        ]);
    }

    /**
     * Verify 2FA OTP code and generate Sanctum access token.
     *
     *
     * @throws ValidationException
     */
    public function verifyCode(LoginVerifyCodeRequest $request, OtpService $otpService): JsonResponse
    {
        $record = VerificationCode::query()
            ->where('token', $request->challenge_token)
            ->where('type', VerificationCode::TYPE_LOGIN)
            ->whereNull('verified_at')
            ->first();

        if (! $record) {
            throw ValidationException::withMessages([
                'challenge_token' => [__('auth.invalid_token')],
            ]);
        }

        if ($record->isExpired()) {
            throw ValidationException::withMessages([
                'code' => [__('auth.otp_expired')],
            ]);
        }

        if ($record->hasExceededAttempts()) {
            throw ValidationException::withMessages([
                'code' => [__('auth.otp_max_attempts')],
            ]);
        }

        if (! hash_equals($record->code, trim($request->code))) {
            $record->increment('attempts');

            throw ValidationException::withMessages([
                'code' => [__('auth.otp_invalid')],
            ]);
        }

        $user = $record->user ?? User::where('email', $record->email)->firstOrFail();

        // Mark OTP as verified and consume it
        $otpService->consume($record);

        // Issue Sanctum Personal Access Token
        $deviceName = $request->device_name ?: 'Web/Mobile App';
        $token = $user->createToken($deviceName)->plainTextToken;

        $user->load(['language', 'country']);

        return response()->json([
            'message' => __('auth.login_success'),
            'access_token' => $token,
            'token_type' => 'Bearer',
            'user' => new UserResource($user),
        ]);
    }
}
