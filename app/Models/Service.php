<?php

namespace App\Models;

use App\Support\Services\ServiceData;
use Carbon\CarbonInterface;
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
    'additional_headers',
    'ssl_expiry_notifications_enabled',
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

    public const STATUS_UP = 'up';

    public const STATUS_DOWN = 'down';

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
            'additional_headers' => 'array',
            'ssl_expiry_notifications_enabled' => 'boolean',
            'last_response_code' => 'integer',
            'last_checked_at' => 'datetime',
            'next_check_at' => 'datetime',
            'last_status_changed_at' => 'datetime',
            'last_ssl_expiry_notification_sent_at' => 'datetime',
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
     * Get the configured additional headers.
     *
     * @return array<int, array{name: string, value: string}>
     */
    public function configuredAdditionalHeaders(): array
    {
        return ServiceData::normalizeAdditionalHeaders($this->additional_headers);
    }

    /**
     * Determine whether the service has additional headers configured.
     */
    public function hasAdditionalHeaders(): bool
    {
        return $this->configuredAdditionalHeaders() !== [];
    }

    /**
     * Get the additional headers keyed for the HTTP client.
     *
     * @return array<string, string>
     */
    public function requestHeaders(): array
    {
        return ServiceData::requestHeaders($this->additional_headers);
    }

    /**
     * Get the configured additional header summary.
     */
    public function additionalHeadersSummary(): string
    {
        $headerCount = count($this->configuredAdditionalHeaders());

        if ($headerCount === 0) {
            return 'No additional headers';
        }

        return trim(trans_choice('{1} :count additional header|[2,*] :count additional headers', $headerCount, ['count' => $headerCount]));
    }

    /**
     * Determine whether the service uses HTTPS.
     */
    public function usesHttps(): bool
    {
        return Str::startsWith(Str::lower($this->url), 'https://');
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
     * Get the human-readable monitoring status label.
     */
    public function monitoringStatusLabel(): string
    {
        return match ($this->current_status) {
            self::STATUS_UP => 'Up'.$this->statusDurationSuffix(),
            self::STATUS_DOWN => 'Down'.$this->statusDurationSuffix(),
            default => 'Pending first check',
        };
    }

    /**
     * Get a human-readable summary of how long the service has held its current status.
     */
    public function statusDurationSummary(?CarbonInterface $referenceTime = null): ?string
    {
        if ($this->last_status_changed_at === null || blank($this->current_status)) {
            return null;
        }

        return $this->formatDurationFromTimestamp($this->last_status_changed_at, $referenceTime);
    }

    /**
     * Get the duration, in seconds, that the service has held its current status.
     */
    public function statusDurationInSeconds(?CarbonInterface $referenceTime = null): ?int
    {
        if ($this->last_status_changed_at === null || blank($this->current_status)) {
            return null;
        }

        return $this->elapsedSeconds($this->last_status_changed_at, $referenceTime);
    }

    /**
     * Get the badge classes for the monitoring status label.
     */
    public function monitoringStatusClasses(): string
    {
        return match ($this->current_status) {
            self::STATUS_UP => 'bg-emerald-100 text-emerald-700 dark:bg-emerald-500/15 dark:text-emerald-300',
            self::STATUS_DOWN => 'bg-rose-100 text-rose-700 dark:bg-rose-500/15 dark:text-rose-300',
            default => 'bg-amber-100 text-amber-700 dark:bg-amber-500/15 dark:text-amber-300',
        };
    }

    /**
     * Get a short summary of the most recent monitoring reason.
     */
    public function monitoringReasonSummary(): string
    {
        if (blank($this->last_check_reason)) {
            return 'Awaiting the first monitoring check.';
        }

        return Str::limit((string) $this->last_check_reason, 120);
    }

    /**
     * Get the human-readable summary for the next check.
     */
    public function nextCheckSummary(): string
    {
        if ($this->next_check_at === null) {
            return 'Due now';
        }

        /** @var CarbonInterface $nextCheckAt */
        $nextCheckAt = $this->next_check_at;

        return $nextCheckAt->isPast()
            ? 'Checking...'
            : 'Next check '.$nextCheckAt->diffForHumans();
    }

    /**
     * Get the status duration suffix shown in the badge label.
     */
    private function statusDurationSuffix(): string
    {
        $duration = $this->statusDurationSummary();

        return $duration === null ? '' : ' for '.$duration;
    }

    /**
     * Format the elapsed time from the given timestamp to now.
     */
    private function formatDurationFromTimestamp(CarbonInterface $timestamp, ?CarbonInterface $referenceTime = null): string
    {
        $remainingSeconds = $this->elapsedSeconds($timestamp, $referenceTime);

        if ($remainingSeconds < 60) {
            return trim(trans_choice('{1} :count second|[2,*] :count seconds', $remainingSeconds, ['count' => $remainingSeconds]));
        }

        $units = [
            'day' => 86400,
            'hour' => 3600,
            'minute' => 60,
        ];

        $segments = [];

        foreach ($units as $unit => $secondsPerUnit) {
            if ($remainingSeconds < $secondsPerUnit) {
                continue;
            }

            $value = intdiv($remainingSeconds, $secondsPerUnit);
            $remainingSeconds %= $secondsPerUnit;

            $segments[] = trans_choice('{1} :count '.$unit.'|[2,*] :count '.$unit.'s', $value, ['count' => $value]);

            if (count($segments) === 2) {
                break;
            }
        }

        if ($segments === []) {
            $segments[] = trans_choice('{1} :count minute|[2,*] :count minutes', 1, ['count' => 1]);
        }

        return trim(implode(' ', $segments));
    }

    /**
     * Get the elapsed seconds between the given timestamp and the reference time.
     */
    private function elapsedSeconds(CarbonInterface $timestamp, ?CarbonInterface $referenceTime = null): int
    {
        $referenceTime ??= now();

        $milliseconds = max(0, $timestamp->diffInMilliseconds($referenceTime));

        return max(1, (int) ceil($milliseconds / 1000));
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
