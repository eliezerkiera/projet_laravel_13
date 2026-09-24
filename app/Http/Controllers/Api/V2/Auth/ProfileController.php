<?php

namespace App\Http\Controllers\Api\V2\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\V2\ChangePasswordRequest;
use App\Http\Requests\V2\DeleteAccountRequest;
use App\Http\Requests\V2\UpdateProfileRequest;
use App\Http\Requests\V2\VerifyEmailChangeRequest;
use App\Http\Resources\V2\UserResource;
use App\Models\User;
use App\Models\VerificationCode;
use App\Services\OtpService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class ProfileController extends Controller
{
    /**
     * Get the authenticated user's profile information.
     */
    public function me(Request $request): JsonResponse
    {
        $user = $request->user()->load(['language', 'country']);

        return response()->json([
            'user' => new UserResource($user),
        ]);
    }

    /**
     * Update user profile fields. If email changes, initiate OTP verification to the new email.
     *
     *
     * @throws ValidationException
     */
    public function update(UpdateProfileRequest $request, OtpService $otpService): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        // Update basic profile attributes
        $user->fill($request->safe()->only([
            'first_name',
            'last_name',
            'country_id',
            'language_id',
        ]));

        $emailChanged = false;

        // Check if an email update was requested
        if ($request->filled('email') && $request->email !== $user->email) {
            // Verify new email is not already taken by another registered user
            $emailExists = User::query()
                ->where('email', $request->email)
                ->where('id', '!=', $user->id)
                ->exists();

            if ($emailExists) {
                throw ValidationException::withMessages([
                    'email' => [__('validation.unique', ['attribute' => 'email'])],
                ]);
            }

            // Keep existing active email, assign pending email
            $user->pending_email = $request->email;
            $emailChanged = true;
        }

        $user->save();

        if ($emailChanged) {
            // Dispatch 6-digit OTP code to the new email address
            $otpService->generateAndSend(
                $user->pending_email,
                VerificationCode::TYPE_EMAIL_CHANGE,
                $user
            );

            return response()->json([
                'message' => __('auth.email_change_otp_sent'),
                'pending_email' => $user->pending_email,
                'user' => new UserResource($user->load(['language', 'country'])),
            ]);
        }

        return response()->json([
            'message' => __('auth.profile_updated'),
            'user' => new UserResource($user->load(['language', 'country'])),
        ]);
    }

    /**
     * Verify the OTP code sent to the new email address and commit the change.
     *
     *
     * @throws ValidationException
     */
    public function verifyEmail(VerifyEmailChangeRequest $request, OtpService $otpService): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        if (! $user->pending_email) {
            throw ValidationException::withMessages([
                'code' => [__('auth.invalid_token')],
            ]);
        }

        // Verify the code against the pending email address
        $verification = $otpService->verifyCode(
            $user->pending_email,
            $request->code,
            VerificationCode::TYPE_EMAIL_CHANGE
        );

        // Commit the new email to the user
        $user->forceFill([
            'email' => $user->pending_email,
            'pending_email' => null,
            'email_verified_at' => now(),
        ])->save();

        $otpService->consume($verification);

        return response()->json([
            'message' => __('auth.email_verified_success'),
            'user' => new UserResource($user->load(['language', 'country'])),
        ]);
    }

    /**
     * Allow guest verification of pending email (if user logged out before verifying).
     *
     *
     * @throws ValidationException
     */
    public function verifyPendingEmailGuest(Request $request, OtpService $otpService): JsonResponse
    {
        $request->validate([
            'email' => ['required', 'email'],
            'code' => ['required', 'string', 'size:6'],
        ]);

        // Find user by either pending_email or email
        $user = User::query()
            ->where('pending_email', $request->email)
            ->orWhere(function ($query) use ($request) {
                $query->where('email', $request->email)
                    ->whereNotNull('pending_email');
            })
            ->first();

        if (! $user || ! $user->pending_email) {
            throw ValidationException::withMessages([
                'email' => [__('auth.invalid_token')],
            ]);
        }

        $verification = $otpService->verifyCode(
            $user->pending_email,
            $request->code,
            VerificationCode::TYPE_EMAIL_CHANGE
        );

        $user->forceFill([
            'email' => $user->pending_email,
            'pending_email' => null,
            'email_verified_at' => now(),
        ])->save();

        $otpService->consume($verification);

        return response()->json([
            'message' => __('auth.email_verified_success'),
        ]);
    }

    /**
     * Change authenticated user password after verifying current password.
     * All other active sessions (tokens) are revoked so other devices are signed out.
     */
    public function changePassword(ChangePasswordRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $user->forceFill([
            'password' => $request->password,
        ])->save();

        // Revoke every token except the one used for this request
        $currentTokenId = $request->user()->currentAccessToken()->id;

        $user->tokens()->where('id', '!=', $currentTokenId)->delete();

        return response()->json([
            'message' => __('auth.password_changed'),
        ]);
    }

    /**
     * Soft delete user account after password confirmation.
     */
    public function destroy(DeleteAccountRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        // Revoke all tokens
        $user->tokens()->delete();

        // Perform Soft Delete
        $user->delete();

        return response()->json([
            'message' => __('auth.account_deleted'),
        ]);
    }
}
