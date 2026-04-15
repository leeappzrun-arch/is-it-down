<?php

namespace App\Support\Monitoring;

use App\Models\Service;

class BrowserPageMonitor
{
    /**
     * Fetch the rendered page for the given service.
     *
     * @return array{status: int|null, body: string, headers: array<int, array{name: string, value: string}>}
     */
    public function fetch(Service $service): array
    {
        $browsershot = $this->makeBrowsershot($service, $service->requestHeaders());

        if (! is_object($browsershot)) {
            throw new \RuntimeException('Browser monitoring is unavailable because Browsershot is not installed.');
        }

        $body = $browsershot->bodyHtml();
        $navigationResponse = method_exists($browsershot, 'redirectHistory')
            ? $browsershot->redirectHistory()
            : null;
        $failedRequest = method_exists($browsershot, 'failedRequests')
            ? $browsershot->failedRequests()
            : null;

        return [
            'status' => is_array($navigationResponse)
                ? (int) ($navigationResponse['status'] ?? 0)
                : (is_array($failedRequest) ? (int) ($failedRequest['status'] ?? 0) : null),
            'body' => $body,
            'headers' => ResponseHeaderData::normalize($navigationResponse['headers'] ?? []),
        ];
    }

    /**
     * Create a configured Browsershot instance when the package is available.
     *
     * @param  array<string, string>  $headers
     */
    protected function makeBrowsershot(Service $service, array $headers = []): ?object
    {
        $browsershotClass = 'Spatie\\Browsershot\\Browsershot';

        if (! class_exists($browsershotClass)) {
            return null;
        }

        $browsershot = $browsershotClass::url($service->url);

        return $this->configureBrowsershot($browsershot, $service, $headers);
    }

    /**
     * Apply the runtime Browsershot configuration used inside the container.
     *
     * @param  array<string, string>  $headers
     */
    protected function configureBrowsershot(object $browsershot, Service $service, array $headers = []): object
    {
        $nodeBinary = trim($this->runtimeEnvironmentValue('LARAVEL_SCREENSHOT_NODE_BINARY', '/usr/bin/node'));
        $nodeModulesPath = trim($this->runtimeEnvironmentValue('LARAVEL_SCREENSHOT_NODE_MODULES_PATH', '/opt/browsershot/node_modules'));
        $chromePath = trim($this->runtimeEnvironmentValue('LARAVEL_SCREENSHOT_CHROME_PATH', ''));
        $timeout = max(5, (int) $this->runtimeEnvironmentValue('MONITORING_SCREENSHOT_TIMEOUT_SECONDS', '30'));

        if ($nodeBinary !== '' && method_exists($browsershot, 'setNodeBinary')) {
            $browsershot->setNodeBinary($nodeBinary);
        }

        if ($nodeModulesPath !== '' && method_exists($browsershot, 'setNodeModulePath')) {
            $browsershot->setNodeModulePath($nodeModulesPath);
        }

        if ($chromePath !== '' && method_exists($browsershot, 'setChromePath')) {
            $browsershot->setChromePath($chromePath);
        }

        if (method_exists($browsershot, 'windowSize')) {
            $browsershot->windowSize(1440, 1024);
        }

        if (method_exists($browsershot, 'timeout')) {
            $browsershot->timeout($timeout);
        }

        if (method_exists($browsershot, 'setDelay')) {
            $browsershot->setDelay($this->settleDelayInMilliseconds());
        }

        if (method_exists($browsershot, 'waitUntilNetworkIdle')) {
            $browsershot->waitUntilNetworkIdle(false);
        }

        if (method_exists($browsershot, 'ignoreHttpsErrors')) {
            $browsershot->ignoreHttpsErrors();
        }

        if (filter_var($this->runtimeEnvironmentValue('LARAVEL_SCREENSHOT_NO_SANDBOX', 'true'), FILTER_VALIDATE_BOOL) && method_exists($browsershot, 'noSandbox')) {
            $browsershot->noSandbox();
        }

        $requestHeaders = $this->requestHeaders($headers);
        $userAgent = $requestHeaders['User-Agent'] ?? null;
        unset($requestHeaders['User-Agent']);

        if (is_string($userAgent) && $userAgent !== '' && method_exists($browsershot, 'userAgent')) {
            $browsershot->userAgent($userAgent);
        }

        if ($requestHeaders !== [] && method_exists($browsershot, 'setExtraHttpHeaders')) {
            $browsershot->setExtraHttpHeaders($requestHeaders);
        }

        if ($requestHeaders !== [] && method_exists($browsershot, 'setExtraNavigationHttpHeaders')) {
            $browsershot->setExtraNavigationHttpHeaders($requestHeaders);
        }

        if (method_exists($browsershot, 'userDataDir')) {
            $browsershot->userDataDir($this->profilePathForService($service));
        }

        return $browsershot;
    }

    /**
     * Create the persistent browser profile path for a service.
     */
    protected function profilePathForService(Service $service): string
    {
        $directory = trim((string) config('services.monitoring.browser_profile_directory', 'app/monitoring-browser-profiles'));
        $relativeDirectory = $directory === '' ? 'app/monitoring-browser-profiles' : str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $directory);
        $serviceIdentifier = $service->getKey() !== null
            ? 'service-'.$service->getKey()
            : 'service-'.sha1((string) $service->url);
        $profilePath = storage_path($relativeDirectory.DIRECTORY_SEPARATOR.$serviceIdentifier);

        if (! is_dir($profilePath)) {
            mkdir($profilePath, 0775, true);
        }

        return $profilePath;
    }

    /**
     * Resolve the explicit settle delay, in milliseconds, used before reading the page.
     */
    protected function settleDelayInMilliseconds(): int
    {
        return max(0, (int) config('services.monitoring.browser_settle_seconds', 10)) * 1000;
    }

    /**
     * Resolve the request headers used by the browser monitor.
     *
     * @param  array<string, string>  $serviceHeaders
     * @return array<string, string>
     */
    protected function requestHeaders(array $serviceHeaders = []): array
    {
        $defaultHeaders = collect(config('services.monitoring.default_request_headers', []))
            ->filter(fn (mixed $value): bool => is_string($value) && trim($value) !== '')
            ->map(fn (string $value): string => trim($value))
            ->all();

        return array_replace($defaultHeaders, $serviceHeaders);
    }

    /**
     * Resolve a runtime environment value with getenv compatibility for plain PHPUnit tests.
     */
    protected function runtimeEnvironmentValue(string $key, string $default = ''): string
    {
        $value = getenv($key);

        if ($value !== false) {
            return (string) $value;
        }

        return (string) env($key, $default);
    }
}
