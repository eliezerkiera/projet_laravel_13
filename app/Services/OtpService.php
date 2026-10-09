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
    public function getExpirationMinutes(): int
    {
        return config('auth_api.otp_ttl_minutes', 10);
    }

    /**
     * Resend cooldown duration in seconds.
     */
    public function getResendCooldownSeconds(): int
    {
        return config('auth_api.otp_resend_delay_seconds', 60);
    }

    /**
     * Maximum allowed attempts before code invalidation.
     */
    public function getMaxAttempts(): int
    {
        return config('auth_api.otp_max_attempts', 5);
    }

    /**
     * Verification token expiration in minutes.
     */
    public function getVerificationTokenTtlMinutes(): int
    {
        return config('auth_api.verification_token_ttl_minutes', 15);
    }

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

        if ($latest && $latest->created_at->addSeconds($this->getResendCooldownSeconds())->isFuture()) {
            $remainingSeconds = (int) ceil(now()->diffInSeconds($latest->created_at->addSeconds($this->getResendCooldownSeconds())));
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
            'expires_at' => now()->addMinutes($this->getExpirationMinutes()),
        ]);

        // Send OTP notification via email (synchronously for now - requires queue setup for async)
        $notifiable = $user ?? (object) ['email' => $email, 'first_name' => null];
        Notification::route('mail', $email)->notify(
            new SendOtpNotification($code, $type, $this->getExpirationMinutes())
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

        if ($record->attempts >= $this->getMaxAttempts()) {
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
            // Refresh expiration for the verification token
            'expires_at' => now()->addMinutes($this->getVerificationTokenTtlMinutes()),
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
