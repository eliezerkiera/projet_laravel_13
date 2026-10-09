<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RefreshToken extends Model
{
    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'token',
        'device_name',
        'expires_at',
        'revoked',
        'family_id',
        'revoked_reason',
        'revoked_at',
        'rotated_at',
    ];

    /**
     * The attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'revoked' => 'boolean',
        ];
    }

    /**
     * Get the user that owns the refresh token.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Check if the refresh token is expired.
     */
    public function isExpired(): bool
    {
        return $this->expires_at->isPast();
    }

    /**
     * Check if the refresh token is valid (not expired and not revoked).
     */
    public function isValid(): bool
    {
        return ! $this->revoked && ! $this->isExpired();
    }

    /**
     * Revoke the refresh token.
     */
    public function revoke(?string $reason = null): void
    {
        $this->update([
            'revoked' => true,
            'revoked_reason' => $reason,
            'revoked_at' => now(),
        ]);
    }

    /**
     * Mark the refresh token as rotated.
     */
    public function markAsRotated(): void
    {
        $this->update([
            'revoked' => true,
            'revoked_reason' => 'rotated',
            'revoked_at' => now(),
            'rotated_at' => now(),
        ]);
    }

    /**
     * Revoke all refresh tokens in the same family.
     */
    public function revokeFamily(): void
    {
        if ($this->family_id) {
            static::where('family_id', $this->family_id)
                ->where('id', '!=', $this->id)
                ->where('revoked', false)
                ->update([
                    'revoked' => true,
                    'revoked_reason' => 'reuse_detected',
                    'revoked_at' => now(),
                ]);
        }
    }
}
