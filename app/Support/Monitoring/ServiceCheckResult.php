<?php

namespace App\Support\Monitoring;

final readonly class ServiceCheckResult
{
    public function __construct(
        public string $status,
        public string $reason,
        public ?int $responseCode = null,
    ) {}
}
