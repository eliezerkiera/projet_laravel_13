<?php

namespace App\Services;

use App\Models\User;
use App\Models\VerificationCode;
use App\Notifications\SendOtpNotification;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class OtpService
{
    /**
     * Expiration duration in minutes for OTP codes.
     */
    public const EXPIRATION_MINUTES = 10;

    /**
     * Resend cooldown duration in seconds.
     */
    public const RESEND_COOLDOWN_SECONDS = 60;

    /**
     * Generate and dispatch a 6-digit OTP code to the given email address.
     *
     * @param  string  $email  Recipient email
     * @param  string  $type  Purpose of the OTP (register, login, password_reset, email_change)
     * @param  User|null  $user  Associated user model if available
     * @param  array<string, mixed>  $data  Additional metadata
     *
     * @throws ValidationException
     */
    public function generateAndSend(
        string $email,
        string $type,
        ?User $user = null,
        array $data = []
    ): VerificationCode {
        // Enforce cooldown to prevent spamming
        $latest = VerificationCode::query()
            ->where('email', $email)
            ->where('type', $type)
            ->latest('id')
            ->first();

        if ($latest && $latest->created_at->addSeconds(self::RESEND_COOLDOWN_SECONDS)->isFuture()) {
            $remainingSeconds = (int) ceil(now()->diffInSeconds($latest->created_at->addSeconds(self::RESEND_COOLDOWN_SECONDS)));
            throw ValidationException::withMessages([
                'email' => [__('auth.otp_wait_resend', ['seconds' => $remainingSeconds])],
            ]);
        }

        // Generate a cryptographically secure 6-digit numeric code
        $code = (string) random_int(100000, 999999);

        // Invalidate previous unverified codes for this email and type
        VerificationCode::query()
            ->where('email', $email)
            ->where('type', $type)
            ->whereNull('verified_at')
            ->delete();

        // Create new verification record
        $verificationCode = VerificationCode::create([
            'user_id' => $user?->id,
            'email' => $email,
            'code' => $code,
            'type' => $type,
            'data' => $data,
            'attempts' => 0,
            'expires_at' => now()->addMinutes(self::EXPIRATION_MINUTES),
        ]);

        // Send OTP notification via email
        $notifiable = $user ?? (object) ['email' => $email, 'first_name' => null];
        Notification::route('mail', $email)->notify(
            new SendOtpNotification($code, $type, self::EXPIRATION_MINUTES)
        );

        return $verificationCode;
    }

    /**
     * Verify a submitted 6-digit OTP code and generate a temporary verification token.
     *
     * @param  string  $email  Recipient email
     * @param  string  $code  6-digit code submitted by user
     * @param  string  $type  Purpose of the OTP
     *
     * @throws ValidationException
     */
    public function verifyCode(string $email, string $code, string $type): VerificationCode
    {
        $record = VerificationCode::query()
            ->where('email', $email)
            ->where('type', $type)
            ->whereNull('verified_at')
            ->latest('id')
            ->first();

        if (! $record) {
            throw ValidationException::withMessages([
                'code' => [__('auth.otp_invalid')],
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

        if (! hash_equals($record->code, trim($code))) {
            $record->increment('attempts');

            throw ValidationException::withMessages([
                'code' => [__('auth.otp_invalid')],
            ]);
        }

        // Generate temporary verification token valid for subsequent action
        $verificationToken = Str::random(64);

        $record->update([
            'verified_at' => now(),
            'token' => $verificationToken,
            // Refresh expiration for the verification token (15 minutes to complete form)
            'expires_at' => now()->addMinutes(15),
        ]);

        return $record;
    }

    /**
     * Find a verified record by its temporary verification token.
     *
     * @param  string  $token  Temporary verification token
     * @param  string  $type  Purpose of the OTP
     */
    public function findValidByToken(string $token, string $type): ?VerificationCode
    {
        return VerificationCode::query()
            ->where('token', $token)
            ->where('type', $type)
            ->whereNotNull('verified_at')
            ->where('expires_at', '>', now())
            ->first();
    }

    /**
     * Invalidate/consume the verification record once the action is completed.
     */
    public function consume(VerificationCode $record): void
    {
        $record->delete();
    }
}
