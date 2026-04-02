<?php

namespace App\Models;

use Database\Factories\RecipientFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Str;

#[Fillable([
    'name',
    'endpoint',
    'webhook_auth_type',
    'webhook_auth_username',
    'webhook_auth_password',
    'webhook_auth_token',
    'webhook_auth_header_name',
    'webhook_auth_header_value',
])]
class Recipient extends Model
{
    /** @use HasFactory<RecipientFactory> */
    use HasFactory;

    public const TYPE_MAIL = 'mail';

    public const TYPE_WEBHOOK = 'webhook';

    public const WEBHOOK_AUTH_NONE = 'none';

    public const WEBHOOK_AUTH_BASIC = 'basic';

    public const WEBHOOK_AUTH_BEARER = 'bearer';

    public const WEBHOOK_AUTH_HEADER = 'header';

    /**
     * Get the webhook authentication types supported by the application.
     *
     * @return array<int, string>
     */
    public static function webhookAuthTypes(): array
    {
        return [
            self::WEBHOOK_AUTH_NONE,
            self::WEBHOOK_AUTH_BASIC,
            self::WEBHOOK_AUTH_BEARER,
            self::WEBHOOK_AUTH_HEADER,
        ];
    }

    /**
     * Get the groups assigned to the recipient.
     */
    public function groups(): BelongsToMany
    {
        return $this->belongsToMany(RecipientGroup::class, 'recipient_group_recipient');
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'webhook_auth_password' => 'encrypted',
            'webhook_auth_token' => 'encrypted',
            'webhook_auth_header_value' => 'encrypted',
        ];
    }

    /**
     * Determine the endpoint type.
     */
    public function endpointType(): string
    {
        if (Str::startsWith($this->endpoint, 'mailto://')) {
            return self::TYPE_MAIL;
        }

        return self::TYPE_WEBHOOK;
    }

    /**
     * Determine whether the recipient uses a mail endpoint.
     */
    public function isMailEndpoint(): bool
    {
        return $this->endpointType() === self::TYPE_MAIL;
    }

    /**
     * Determine whether the recipient uses a webhook endpoint.
     */
    public function isWebhookEndpoint(): bool
    {
        return $this->endpointType() === self::TYPE_WEBHOOK;
    }

    /**
     * Get the endpoint target without its custom prefix.
     */
    public function endpointTarget(): string
    {
        return Str::after($this->endpoint, $this->isMailEndpoint() ? 'mailto://' : 'webhook://');
    }

    /**
     * Get the human-readable endpoint type label.
     */
    public function endpointTypeLabel(): string
    {
        return $this->isMailEndpoint() ? 'Email' : 'Webhook';
    }

    /**
     * Get the human-readable webhook authentication summary.
     */
    public function webhookAuthenticationSummary(): string
    {
        return match ($this->webhook_auth_type) {
            self::WEBHOOK_AUTH_BASIC => 'Basic auth',
            self::WEBHOOK_AUTH_BEARER => 'Bearer token',
            self::WEBHOOK_AUTH_HEADER => 'Custom header',
            default => 'No authentication',
        };
    }
}
