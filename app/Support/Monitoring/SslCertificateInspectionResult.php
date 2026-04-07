<?php

namespace App\Support\Monitoring;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

final readonly class SslCertificateInspectionResult
{
    public function __construct(
        public CarbonImmutable $expiresAt,
    ) {}

    /**
     * Determine whether the certificate is expiring within the given number of days.
     */
    public function expiresWithinDays(int $days, ?CarbonInterface $referenceTime = null): bool
    {
        $referenceTime ??= now();

        return $this->expiresAt->lessThanOrEqualTo($referenceTime->copy()->addDays($days));
    }

    /**
     * Get the signed number of days until expiry.
     */
    public function daysUntilExpiry(?CarbonInterface $referenceTime = null): int
    {
        $referenceTime ??= now();

        return $referenceTime->diffInDays($this->expiresAt, false);
    }

    /**
     * Get a human-readable expiry summary.
     */
    public function summary(?CarbonInterface $referenceTime = null): string
    {
        $referenceTime ??= now();

        return $this->expiresAt->isPast()
            ? 'Expired '.$this->expiresAt->diffForHumans($referenceTime, short: false, parts: 2)
            : 'Expires '.$this->expiresAt->diffForHumans($referenceTime, short: false, parts: 2);
    }
}
