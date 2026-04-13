<?php

namespace App\Models;

use App\Support\Monitoring\ResponseHeaderData;
use Carbon\CarbonInterface;
use Database\Factories\ServiceDowntimeFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

#[Fillable([
    'service_id',
    'started_at',
    'ended_at',
    'started_reason',
    'latest_reason',
    'recovery_reason',
    'started_response_code',
    'started_response_headers',
    'latest_response_code',
    'latest_response_headers',
    'recovery_response_code',
    'last_checked_at',
    'last_check_attempts',
    'screenshot_disk',
    'screenshot_path',
    'screenshot_captured_at',
    'ai_summary',
    'ai_summary_created_at',
])]
class ServiceDowntime extends Model
{
    /** @use HasFactory<ServiceDowntimeFactory> */
    use HasFactory;

    /**
     * Get the service for the downtime incident.
     */
    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    /**
     * Limit the query to downtime incidents that overlap the given window.
     */
    public function scopeOverlappingWindow(Builder $query, CarbonInterface $windowStart, CarbonInterface $windowEnd): Builder
    {
        return $query
            ->where('started_at', '<', $windowEnd)
            ->where(function (Builder $downtimeQuery) use ($windowStart): void {
                $downtimeQuery
                    ->whereNull('ended_at')
                    ->orWhere('ended_at', '>', $windowStart);
            });
    }

    /**
     * Determine whether the downtime is still ongoing.
     */
    public function isOngoing(): bool
    {
        return $this->ended_at === null;
    }

    /**
     * Determine whether a screenshot is stored for the downtime.
     */
    public function hasScreenshot(): bool
    {
        return filled($this->screenshot_disk) && filled($this->screenshot_path);
    }

    /**
     * Get the public screenshot URL when one is available.
     */
    public function screenshotUrl(): ?string
    {
        if (! $this->hasScreenshot()) {
            return null;
        }

        return Storage::disk((string) $this->screenshot_disk)->url((string) $this->screenshot_path);
    }

    /**
     * Get the response headers captured when the downtime started.
     *
     * @return array<int, array{name: string, value: string}>
     */
    public function startedResponseHeaders(): array
    {
        return ResponseHeaderData::normalize($this->started_response_headers);
    }

    /**
     * Get the latest response headers captured while the downtime was active.
     *
     * @return array<int, array{name: string, value: string}>
     */
    public function latestResponseHeaders(): array
    {
        return ResponseHeaderData::normalize($this->latest_response_headers);
    }

    /**
     * Get the duration in seconds for the incident.
     */
    public function durationInSeconds(?CarbonInterface $referenceTime = null): int
    {
        $endedAt = $this->ended_at ?? $referenceTime ?? now();

        return max(1, (int) ceil($this->started_at->diffInMilliseconds($endedAt) / 1000));
    }

    /**
     * Get the human-readable incident duration summary.
     */
    public function durationSummary(?CarbonInterface $referenceTime = null): string
    {
        $remainingSeconds = $this->durationInSeconds($referenceTime);

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

        return trim(implode(' ', $segments));
    }

    /**
     * Get the overlap duration in seconds within the given window.
     */
    public function overlapDurationInSeconds(CarbonInterface $windowStart, CarbonInterface $windowEnd): int
    {
        $effectiveStart = $this->started_at->greaterThan($windowStart)
            ? $this->started_at
            : $windowStart;

        $effectiveEnd = ($this->ended_at ?? $windowEnd)->lessThan($windowEnd)
            ? ($this->ended_at ?? $windowEnd)
            : $windowEnd;

        return max(0, (int) floor($effectiveStart->diffInSeconds($effectiveEnd, absolute: true)));
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'ended_at' => 'datetime',
            'last_checked_at' => 'datetime',
            'screenshot_captured_at' => 'datetime',
            'ai_summary_created_at' => 'datetime',
            'started_response_code' => 'integer',
            'started_response_headers' => 'array',
            'latest_response_code' => 'integer',
            'latest_response_headers' => 'array',
            'recovery_response_code' => 'integer',
            'last_check_attempts' => 'integer',
        ];
    }
}
