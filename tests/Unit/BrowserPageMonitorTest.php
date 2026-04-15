<?php

namespace Tests\Unit;

use App\Models\Service;
use App\Support\Monitoring\BrowserPageMonitor;
use Tests\TestCase;

class BrowserPageMonitorTest extends TestCase
{
    public function test_it_applies_a_persistent_profile_headers_and_settle_delay(): void
    {
        putenv('LARAVEL_SCREENSHOT_NODE_BINARY=/custom/node');
        putenv('LARAVEL_SCREENSHOT_NODE_MODULES_PATH=/custom/node_modules');
        putenv('LARAVEL_SCREENSHOT_CHROME_PATH=/custom/chrome');
        putenv('LARAVEL_SCREENSHOT_NO_SANDBOX=true');
        putenv('MONITORING_SCREENSHOT_TIMEOUT_SECONDS=45');

        config()->set('services.monitoring.browser_settle_seconds', 10);
        config()->set('services.monitoring.default_request_headers', [
            'Accept' => 'text/html,*/*;q=0.8',
            'User-Agent' => 'IsItDownBrowserTest/1.0',
        ]);

        $fakeBrowsershot = new class
        {
            public ?string $nodeBinary = null;

            public ?string $nodeModulePath = null;

            public ?string $chromePath = null;

            public ?int $windowWidth = null;

            public ?int $windowHeight = null;

            public ?int $timeout = null;

            public ?int $delay = null;

            public bool $waitedForNetworkIdle = false;

            public ?bool $waitedForNetworkIdleStrict = null;

            public bool $ignoredHttpsErrors = false;

            public bool $usedNoSandbox = false;

            public ?string $userAgent = null;

            /** @var array<string, string> */
            public array $extraHttpHeaders = [];

            /** @var array<string, string> */
            public array $extraNavigationHttpHeaders = [];

            public ?string $userDataDir = null;

            public function setNodeBinary(string $path): self
            {
                $this->nodeBinary = $path;

                return $this;
            }

            public function setNodeModulePath(string $path): self
            {
                $this->nodeModulePath = $path;

                return $this;
            }

            public function setChromePath(string $path): self
            {
                $this->chromePath = $path;

                return $this;
            }

            public function windowSize(int $width, int $height): self
            {
                $this->windowWidth = $width;
                $this->windowHeight = $height;

                return $this;
            }

            public function timeout(int $seconds): self
            {
                $this->timeout = $seconds;

                return $this;
            }

            public function setDelay(int $milliseconds): self
            {
                $this->delay = $milliseconds;

                return $this;
            }

            public function waitUntilNetworkIdle(bool $strict = true): self
            {
                $this->waitedForNetworkIdle = true;
                $this->waitedForNetworkIdleStrict = $strict;

                return $this;
            }

            public function ignoreHttpsErrors(): self
            {
                $this->ignoredHttpsErrors = true;

                return $this;
            }

            public function noSandbox(): self
            {
                $this->usedNoSandbox = true;

                return $this;
            }

            public function userAgent(string $userAgent): self
            {
                $this->userAgent = $userAgent;

                return $this;
            }

            /**
             * @param  array<string, string>  $headers
             */
            public function setExtraHttpHeaders(array $headers): self
            {
                $this->extraHttpHeaders = $headers;

                return $this;
            }

            /**
             * @param  array<string, string>  $headers
             */
            public function setExtraNavigationHttpHeaders(array $headers): self
            {
                $this->extraNavigationHttpHeaders = $headers;

                return $this;
            }

            public function userDataDir(string $path): self
            {
                $this->userDataDir = $path;

                return $this;
            }
        };

        $service = (new Service)->forceFill([
            'id' => 42,
            'url' => 'https://status.example.com',
            'additional_headers' => [
                ['name' => 'X-Monitor', 'value' => 'browser'],
            ],
        ]);

        $browserPageMonitor = new class extends BrowserPageMonitor
        {
            public function applyConfiguration(object $browsershot, Service $service, array $headers = []): object
            {
                return $this->configureBrowsershot($browsershot, $service, $headers);
            }
        };

        $browserPageMonitor->applyConfiguration($fakeBrowsershot, $service, $service->requestHeaders());

        $this->assertSame('/custom/node', $fakeBrowsershot->nodeBinary);
        $this->assertSame('/custom/node_modules', $fakeBrowsershot->nodeModulePath);
        $this->assertSame('/custom/chrome', $fakeBrowsershot->chromePath);
        $this->assertSame(1440, $fakeBrowsershot->windowWidth);
        $this->assertSame(1024, $fakeBrowsershot->windowHeight);
        $this->assertSame(45, $fakeBrowsershot->timeout);
        $this->assertSame(10000, $fakeBrowsershot->delay);
        $this->assertTrue($fakeBrowsershot->waitedForNetworkIdle);
        $this->assertFalse($fakeBrowsershot->waitedForNetworkIdleStrict);
        $this->assertTrue($fakeBrowsershot->ignoredHttpsErrors);
        $this->assertTrue($fakeBrowsershot->usedNoSandbox);
        $this->assertSame('IsItDownBrowserTest/1.0', $fakeBrowsershot->userAgent);
        $this->assertSame(['Accept' => 'text/html,*/*;q=0.8', 'X-Monitor' => 'browser'], $fakeBrowsershot->extraHttpHeaders);
        $this->assertSame(['Accept' => 'text/html,*/*;q=0.8', 'X-Monitor' => 'browser'], $fakeBrowsershot->extraNavigationHttpHeaders);
        $this->assertNotNull($fakeBrowsershot->userDataDir);
        $this->assertStringContainsString('service-42', $fakeBrowsershot->userDataDir);
        $this->assertTrue(is_dir($fakeBrowsershot->userDataDir));
    }

    public function test_it_treats_zero_status_metadata_as_missing_status(): void
    {
        $service = (new Service)->forceFill([
            'id' => 7,
            'url' => 'https://status.example.com',
        ]);

        $browserPageMonitor = new class extends BrowserPageMonitor
        {
            protected function makeBrowsershot(Service $service, array $headers = []): ?object
            {
                return new class
                {
                    public function bodyHtml(): string
                    {
                        return '<main><h1>Status page</h1></main>';
                    }

                    public function redirectHistory(): array
                    {
                        return [
                            'status' => 0,
                            'headers' => [
                                'Content-Type' => 'text/html; charset=UTF-8',
                            ],
                        ];
                    }

                    public function failedRequests(): ?array
                    {
                        return null;
                    }
                };
            }
        };

        $page = $browserPageMonitor->fetch($service);

        $this->assertNull($page['status']);
        $this->assertSame('<main><h1>Status page</h1></main>', $page['body']);
        $this->assertSame([
            ['name' => 'Content-Type', 'value' => 'text/html; charset=UTF-8'],
        ], $page['headers']);
    }
}
