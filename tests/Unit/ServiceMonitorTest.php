<?php

namespace Tests\Unit;

use App\Models\Service;
use App\Support\Monitoring\ServiceMonitor;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ServiceMonitorTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config()->set('services.monitoring.failure_retry_delay_seconds', 0);
    }

    public function test_it_sends_configured_default_request_headers(): void
    {
        Http::preventStrayRequests();

        config()->set('services.monitoring.default_request_headers', [
            'Accept' => 'text/html,*/*;q=0.8',
            'Accept-Language' => 'en-GB,en;q=0.9',
            'User-Agent' => 'IsItDownUnitTest/1.0',
        ]);

        $service = new Service([
            'name' => 'Docs Site',
            'url' => 'https://docs.example.com',
            'interval_seconds' => Service::INTERVAL_1_MINUTE,
        ]);

        Http::fake([
            'https://docs.example.com' => function ($request) {
                $this->assertSame('text/html,*/*;q=0.8', $request->header('Accept')[0] ?? null);
                $this->assertSame('en-GB,en;q=0.9', $request->header('Accept-Language')[0] ?? null);
                $this->assertSame('IsItDownUnitTest/1.0', $request->header('User-Agent')[0] ?? null);

                return Http::response('All systems operational', 200);
            },
        ]);

        $result = app(ServiceMonitor::class)->check($service);

        $this->assertSame(Service::STATUS_UP, $result->status);
        $this->assertSame('Received an HTTP 200 response.', $result->reason);
    }

    public function test_it_labels_cloudflare_rate_limits_clearly(): void
    {
        Http::preventStrayRequests();

        $service = new Service([
            'name' => 'Customer Portal',
            'url' => 'https://portal.example.com/health',
            'interval_seconds' => Service::INTERVAL_1_MINUTE,
        ]);

        Http::fake([
            'https://portal.example.com/health' => Http::sequence()
                ->push('Too many requests', 429, [
                    'Server' => 'cloudflare',
                    'CF-Ray' => 'abc123-lhr',
                    'Retry-After' => '120',
                ])
                ->push('Too many requests', 429, [
                    'Server' => 'cloudflare',
                    'CF-Ray' => 'abc123-lhr',
                    'Retry-After' => '120',
                ]),
        ]);

        $result = app(ServiceMonitor::class)->check($service);

        $this->assertSame(Service::STATUS_DOWN, $result->status);
        $this->assertSame(429, $result->responseCode);
        $this->assertSame(2, $result->attemptCount);
        $this->assertSame(
            'Cloudflare rate limited the monitor request with HTTP 429 Too Many Requests. This often indicates temporary protection rather than a real outage.',
            $result->reason,
        );
        $this->assertTrue(collect($result->responseHeaders)->contains(
            fn (array $header): bool => ($header['name'] ?? null) === 'Retry-After' && ($header['value'] ?? null) === '120',
        ));
    }
}
