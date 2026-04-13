<?php

namespace App\Support\Monitoring;

use App\Models\AiAssistantSetting;
use App\Models\Service;
use App\Models\ServiceDowntime;
use App\Support\AiAssistant\AiAssistantClient;
use Illuminate\Support\Arr;
use Throwable;

class OutageAnalyzer
{
    public function __construct(
        private readonly AiAssistantClient $client,
    ) {}

    /**
     * Generate a concise outage analysis when Dave is enabled.
     */
    public function analyze(Service $service, ServiceCheckResult $result, ?ServiceDowntime $downtime = null): ?string
    {
        $settings = AiAssistantSetting::enabled();

        if ($settings === null) {
            return null;
        }

        $messages = [
            [
                'role' => 'system',
                'content' => 'You analyze website outages for a monitoring application. Reply with at most two short sentences. Name the most likely cause and one useful next step. If the evidence is weak, say that clearly.',
            ],
            [
                'role' => 'user',
                'content' => $this->buildPrompt($service, $result, $downtime),
            ],
        ];

        try {
            $response = $this->client->complete($settings, $messages);
        } catch (Throwable $throwable) {
            report($throwable);

            return null;
        }

        $content = trim((string) Arr::get($response, 'content', ''));

        return $content === '' ? null : $content;
    }

    /**
     * Build the outage-analysis prompt.
     */
    private function buildPrompt(Service $service, ServiceCheckResult $result, ?ServiceDowntime $downtime): string
    {
        $recentHistoryQuery = $service->downtimes()->latest('started_at');

        if ($downtime instanceof ServiceDowntime) {
            $recentHistoryQuery->whereKeyNot($downtime->id);
        }

        $recentHistory = $recentHistoryQuery
            ->limit(3)
            ->get()
            ->map(function (ServiceDowntime $incident): string {
                $resolvedAt = $incident->ended_at?->toIso8601String() ?? 'ongoing';

                return "- Started {$incident->started_at->toIso8601String()}, ended {$resolvedAt}, reason: ".($incident->latest_reason ?? $incident->started_reason ?? 'Unknown');
            })
            ->implode("\n");

        return trim(implode("\n", array_filter([
            "Service: {$service->name}",
            "URL: {$service->url}",
            "Expectation: {$service->expectSummary()}",
            "Status: {$result->status}",
            "Reason: {$result->reason}",
            $result->responseCode !== null ? "HTTP response code: {$result->responseCode}" : null,
            $result->attemptCount > 1 ? "Attempts: {$result->attemptCount}" : null,
            $result->bodyExcerpt !== null ? "Response excerpt: {$result->bodyExcerpt}" : null,
            $recentHistory !== '' ? "Recent downtime history:\n{$recentHistory}" : null,
        ])));
    }
}
