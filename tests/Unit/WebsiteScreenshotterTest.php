<?php

namespace Tests\Unit;

use App\Support\Monitoring\WebsiteScreenshotter;
use PHPUnit\Framework\TestCase;

class WebsiteScreenshotterTest extends TestCase
{
    public function test_it_applies_the_expected_browsershot_runtime_configuration(): void
    {
        putenv('LARAVEL_SCREENSHOT_NODE_BINARY=/custom/node');
        putenv('LARAVEL_SCREENSHOT_NODE_MODULES_PATH=/custom/node_modules');
        putenv('LARAVEL_SCREENSHOT_CHROME_PATH=/custom/chrome');
        putenv('LARAVEL_SCREENSHOT_NO_SANDBOX=true');
        putenv('MONITORING_SCREENSHOT_TIMEOUT_SECONDS=45');

        $fakeBrowsershot = new class
        {
            public ?string $nodeBinary = null;

            public ?string $nodeModulePath = null;

            public ?string $chromePath = null;

            public ?int $windowWidth = null;

            public ?int $windowHeight = null;

            public ?int $timeout = null;

            public bool $ignoredHttpsErrors = false;

            public bool $usedNoSandbox = false;

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
        };

        $screenshotter = new class extends WebsiteScreenshotter
        {
            public function applyConfiguration(object $browsershot): object
            {
                return $this->configureBrowsershot($browsershot);
            }
        };

        $screenshotter->applyConfiguration($fakeBrowsershot);

        $this->assertSame('/custom/node', $fakeBrowsershot->nodeBinary);
        $this->assertSame('/custom/node_modules', $fakeBrowsershot->nodeModulePath);
        $this->assertSame('/custom/chrome', $fakeBrowsershot->chromePath);
        $this->assertSame(1440, $fakeBrowsershot->windowWidth);
        $this->assertSame(1024, $fakeBrowsershot->windowHeight);
        $this->assertSame(45, $fakeBrowsershot->timeout);
        $this->assertTrue($fakeBrowsershot->ignoredHttpsErrors);
        $this->assertTrue($fakeBrowsershot->usedNoSandbox);
    }
}
