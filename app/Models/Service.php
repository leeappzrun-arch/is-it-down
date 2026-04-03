<?php

namespace App\Models;

use Database\Factories\ServiceFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

#[Fillable([
    'name',
    'url',
    'interval_seconds',
    'expect_type',
    'expect_value',
])]
class Service extends Model
{
    /** @use HasFactory<ServiceFactory> */
    use HasFactory;

    public const INTERVAL_30_SECONDS = 30;

    public const INTERVAL_1_MINUTE = 60;

    public const INTERVAL_3_MINUTES = 180;

    public const INTERVAL_5_MINUTES = 300;

    public const INTERVAL_10_MINUTES = 600;

    public const EXPECT_NONE = 'none';

    public const EXPECT_TEXT = 'text';

    public const EXPECT_REGEX = 'regex';

    /**
     * Get the supported monitoring interval options.
     *
     * @return array<int, string>
     */
    public static function intervalOptions(): array
    {
        return [
            self::INTERVAL_30_SECONDS => '30 seconds',
            self::INTERVAL_1_MINUTE => '1 minute',
            self::INTERVAL_3_MINUTES => '3 minutes',
            self::INTERVAL_5_MINUTES => '5 minutes',
            self::INTERVAL_10_MINUTES => '10 minutes',
        ];
    }

    /**
     * Get the supported expect field modes.
     *
     * @return array<string, string>
     */
    public static function expectTypes(): array
    {
        return [
            self::EXPECT_NONE => 'No expectation',
            self::EXPECT_TEXT => 'Plain text',
            self::EXPECT_REGEX => 'Regular expression',
        ];
    }

    /**
     * Get the recipients assigned directly to the service.
     */
    public function recipients(): BelongsToMany
    {
        return $this->belongsToMany(Recipient::class, 'recipient_service');
    }

    /**
     * Get the recipient groups assigned directly to the service.
     */
    public function recipientGroups(): BelongsToMany
    {
        return $this->belongsToMany(RecipientGroup::class, 'recipient_group_service');
    }

    /**
     * Get the service groups assigned to the service.
     */
    public function groups(): BelongsToMany
    {
        return $this->belongsToMany(ServiceGroup::class, 'service_service_group');
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'interval_seconds' => 'integer',
        ];
    }

    /**
     * Get the human-readable interval label.
     */
    public function intervalLabel(): string
    {
        return self::intervalOptions()[$this->interval_seconds] ?? __('Custom interval');
    }

    /**
     * Determine whether the service has an expectation configured.
     */
    public function hasExpectation(): bool
    {
        return filled($this->expect_value) && $this->expect_type !== self::EXPECT_NONE;
    }

    /**
     * Get the configured expectation summary.
     */
    public function expectSummary(): string
    {
        if (! $this->hasExpectation()) {
            return 'No expectation';
        }

        $label = $this->expect_type === self::EXPECT_REGEX ? 'Regex' : 'Text';

        return $label.': '.Str::limit((string) $this->expect_value, 80);
    }

    /**
     * Get the effective recipients for the service and the path each one uses.
     *
     * @return Collection<int, array{recipient: Recipient, sources: array<int, string>}>
     */
    public function effectiveRecipientRoutes(): Collection
    {
        /** @var Collection<int, array{recipient: Recipient, source: string}> $routes */
        $routes = collect();

        foreach ($this->recipients as $recipient) {
            $routes->push([
                'recipient' => $recipient,
                'source' => 'Direct recipient',
            ]);
        }

        foreach ($this->recipientGroups as $recipientGroup) {
            foreach ($recipientGroup->recipients as $recipient) {
                $routes->push([
                    'recipient' => $recipient,
                    'source' => 'Recipient group: '.$recipientGroup->name,
                ]);
            }
        }

        foreach ($this->groups as $serviceGroup) {
            foreach ($serviceGroup->recipients as $recipient) {
                $routes->push([
                    'recipient' => $recipient,
                    'source' => 'Service group: '.$serviceGroup->name,
                ]);
            }

            foreach ($serviceGroup->recipientGroups as $recipientGroup) {
                foreach ($recipientGroup->recipients as $recipient) {
                    $routes->push([
                        'recipient' => $recipient,
                        'source' => 'Service group '.$serviceGroup->name.' via recipient group '.$recipientGroup->name,
                    ]);
                }
            }
        }

        return $routes
            ->groupBy(fn (array $route): int => $route['recipient']->id)
            ->map(function (Collection $groupedRoutes): array {
                /** @var Recipient $recipient */
                $recipient = $groupedRoutes->first()['recipient'];

                return [
                    'recipient' => $recipient,
                    'sources' => $groupedRoutes
                        ->pluck('source')
                        ->unique()
                        ->values()
                        ->all(),
                ];
            })
            ->values();
    }
}
