<?php

namespace App\Models;

use Database\Factories\ApiKeyFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

#[Fillable([
    'name',
    'user_id',
    'created_by_id',
    'token_prefix',
    'token_hash',
    'permissions',
    'expires_at',
    'last_used_at',
    'revoked_at',
])]
#[Hidden(['token_hash'])]
class ApiKey extends Model
{
    /** @use HasFactory<ApiKeyFactory> */
    use HasFactory;

    /**
     * Create a new plain text token for an API key.
     */
    public static function generatePlainTextToken(): string
    {
        return 'iid_'.Str::random(40);
    }

    /**
     * Build the hash used to persist the token securely.
     */
    public static function hashToken(string $plainTextToken): string
    {
        return hash('sha256', $plainTextToken);
    }

    /**
     * Get the user that owns the API key.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the user that created the API key.
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_id');
    }

    /**
     * Scope the query to active, usable keys.
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query
            ->whereNull('revoked_at')
            ->where(function (Builder $builder): void {
                $builder
                    ->whereNull('expires_at')
                    ->orWhere('expires_at', '>', now());
            });
    }

    /**
     * Locate an active API key from its plain text value.
     */
    public static function findFromPlainTextToken(string $plainTextToken): ?self
    {
        return self::query()
            ->active()
            ->where('token_hash', self::hashToken($plainTextToken))
            ->first();
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'permissions' => 'array',
            'expires_at' => 'datetime',
            'last_used_at' => 'datetime',
            'revoked_at' => 'datetime',
        ];
    }

    /**
     * Determine whether the key has been revoked.
     */
    public function isRevoked(): bool
    {
        return $this->revoked_at !== null;
    }

    /**
     * Determine whether the key has expired.
     */
    public function isExpired(): bool
    {
        return $this->expires_at !== null && $this->expires_at->isPast();
    }

    /**
     * Determine whether the key is currently active.
     */
    public function isActive(): bool
    {
        return ! $this->isRevoked() && ! $this->isExpired();
    }

    /**
     * Determine whether the key grants the requested permission.
     */
    public function hasPermission(string $permission): bool
    {
        return in_array($permission, $this->permissions ?? [], true);
    }

    /**
     * Get the human-readable expiration label.
     */
    public function expirationLabel(): string
    {
        if ($this->expires_at === null) {
            return 'Never';
        }

        return $this->expires_at->toFormattedDateString();
    }

    /**
     * Get the human-readable last-used label.
     */
    public function lastUsedLabel(): string
    {
        if ($this->last_used_at === null) {
            return 'Never used';
        }

        return $this->last_used_at->diffForHumans();
    }
}
