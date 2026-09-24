<?php

namespace App\Http\Controllers\Api\V2\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\V2\RegisterCompleteRequest;
use App\Http\Requests\V2\RegisterSendCodeRequest;
use App\Http\Requests\V2\RegisterVerifyCodeRequest;
use App\Http\Resources\V2\UserResource;
use App\Models\User;
use App\Models\VerificationCode;
use App\Services\OtpService;
use Illuminate\Http\JsonResponse;

class RegisterController extends Controller
{
    /**
     * Send OTP verification code to the prospective registrant's email.
     */
    public function sendCode(RegisterSendCodeRequest $request, OtpService $otpService): JsonResponse
    {
        $otpService->generateAndSend($request->email, VerificationCode::TYPE_REGISTER);

        return response()->json([
            'message' => __('auth.otp_sent'),
            'email' => $request->email,
        ]);
    }

    /**
     * Resend a fresh OTP verification code for registration.
     */
    public function resendCode(RegisterSendCodeRequest $request, OtpService $otpService): JsonResponse
    {
        $otpService->generateAndSend($request->email, VerificationCode::TYPE_REGISTER);

        return response()->json([
            'message' => __('auth.otp_sent'),
            'email' => $request->email,
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

        return response()->json([
            'message' => __('auth.otp_sent'), // code verified successfully
            'verification_token' => $verification->token,
            'email' => $verification->email,
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
            return response()->json([
                'message' => __('auth.invalid_token'),
            ], 422);
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

        // Issue Sanctum Personal Access Token
        $deviceName = $request->device_name ?: 'Web/Mobile App';
        $token = $user->createToken($deviceName)->plainTextToken;

        $user->load(['language', 'country']);

        return response()->json([
            'message' => __('auth.registration_completed'),
            'access_token' => $token,
            'token_type' => 'Bearer',
            'user' => new UserResource($user),
        ], 201);
    }
}
