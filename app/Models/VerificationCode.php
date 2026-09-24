<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VerificationCode extends Model
{
    use HasFactory;

    /**
     * Types of verification codes.
     */
    public const TYPE_REGISTER = 'register';

    public const TYPE_LOGIN = 'login';

    public const TYPE_PASSWORD_RESET = 'password_reset';

    public const TYPE_EMAIL_CHANGE = 'email_change';

    /**
     * Maximum allowed attempts before code invalidation.
     */
    public const MAX_ATTEMPTS = 5;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'email',
        'code',
        'type',
        'token',
        'data',
        'attempts',
        'expires_at',
        'verified_at',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'verified_at' => 'datetime',
            'data' => 'array',
            'attempts' => 'integer',
        ];
    }

    /**
     * Get the user associated with this verification code.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Check if the verification code has expired.
     */
    public function isExpired(): bool
    {
        return $this->expires_at->isPast();
    }

    /**
     * Check if the verification code has already been verified.
     */
    public function isVerified(): bool
    {
        return $this->verified_at !== null;
    }

    /**
     * Check if the maximum attempts have been reached.
     */
    public function hasExceededAttempts(): bool
    {
        return $this->attempts >= self::MAX_ATTEMPTS;
    }
}
