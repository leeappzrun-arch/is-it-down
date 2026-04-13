<?php

namespace App\Support\Monitoring;

use App\Models\Service;
use App\Models\ServiceDowntime;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Throwable;

class WebsiteScreenshotter
{
    /**
     * Capture a screenshot for the given service and return the PNG bytes.
     */
    public function capture(Service $service): ?string
    {
        $temporaryFile = tempnam(sys_get_temp_dir(), 'service-downtime-');

        if ($temporaryFile === false) {
            Log::warning('Unable to create a temporary file for service screenshot capture.', [
                'service_id' => $service->id,
                'service_name' => $service->name,
                'service_url' => $service->url,
            ]);

            return null;
        }

        $pngPath = $temporaryFile.'.png';
        @rename($temporaryFile, $pngPath);

        try {
            if ($this->captureWithBrowsershot($service->url, $pngPath) || $this->captureWithLaravelScreenshot($service->url, $pngPath)) {
                $contents = file_get_contents($pngPath);

                return $contents === false ? null : $contents;
            }
        } catch (Throwable $throwable) {
            report($throwable);
        } finally {
            @unlink($pngPath);
        }

        Log::warning('Service screenshot capture did not produce an image file.', [
            'service_id' => $service->id,
            'service_name' => $service->name,
            'service_url' => $service->url,
        ]);

        return null;
    }

    /**
     * Store the latest screenshot for the service.
     *
     * @return array{disk: string, path: string}
     */
    public function storeLatestForService(Service $service, string $pngContents): array
    {
        $disk = (string) config('services.monitoring.downtime_screenshot_disk', 'public');
        $directory = trim((string) config('services.monitoring.latest_service_screenshot_directory', 'service-screenshots'), '/');
        $path = $directory.'/service-'.$service->id.'.png';

        Storage::disk($disk)->put($path, $pngContents);

        return [
            'disk' => $disk,
            'path' => $path,
        ];
    }

    /**
     * Store the latest screenshot recorded for a downtime incident.
     *
     * @return array{disk: string, path: string}
     */
    public function storeForDowntime(Service $service, ServiceDowntime $downtime, string $pngContents): array
    {
        $disk = (string) config('services.monitoring.downtime_screenshot_disk', 'public');
        $directory = trim((string) config('services.monitoring.downtime_screenshot_directory', 'downtime-screenshots'), '/');
        $path = $directory.'/service-'.$service->id.'-downtime-'.$downtime->id.'.png';

        Storage::disk($disk)->put($path, $pngContents);

        return [
            'disk' => $disk,
            'path' => $path,
        ];
    }

    /**
     * Capture a screenshot with spatie/laravel-screenshot when available.
     */
    private function captureWithLaravelScreenshot(string $url, string $path): bool
    {
        $facade = 'Spatie\\LaravelScreenshot\\Facades\\Screenshot';

        if (! class_exists($facade)) {
            return false;
        }

        $facade::url($url)->save($path);

        return file_exists($path);
    }

    /**
     * Capture a screenshot with spatie/browsershot when available.
     */
    private function captureWithBrowsershot(string $url, string $path): bool
    {
        $browsershot = $this->makeBrowsershot($url);

        if (! is_object($browsershot)) {
            return false;
        }

        $browsershot->save($path);

        return file_exists($path);
    }

    /**
     * Create a configured Browsershot instance when the package is available.
     */
    protected function makeBrowsershot(string $url): ?object
    {
        $browsershotClass = 'Spatie\\Browsershot\\Browsershot';

        if (! class_exists($browsershotClass)) {
            return null;
        }

        $browsershot = $browsershotClass::url($url);

        return $this->configureBrowsershot($browsershot);
    }

    /**
     * Apply the runtime Browsershot configuration used inside the container.
     */
    protected function configureBrowsershot(object $browsershot): object
    {
        $nodeBinary = trim((string) env('LARAVEL_SCREENSHOT_NODE_BINARY', '/usr/bin/node'));
        $nodeModulesPath = trim((string) env('LARAVEL_SCREENSHOT_NODE_MODULES_PATH', '/opt/browsershot/node_modules'));
        $chromePath = trim((string) env('LARAVEL_SCREENSHOT_CHROME_PATH', ''));
        $screenshotTimeout = max(5, (int) env('MONITORING_SCREENSHOT_TIMEOUT_SECONDS', 30));

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
            $browsershot->timeout($screenshotTimeout);
        }

        if (method_exists($browsershot, 'ignoreHttpsErrors')) {
            $browsershot->ignoreHttpsErrors();
        }

        if (filter_var(env('LARAVEL_SCREENSHOT_NO_SANDBOX', true), FILTER_VALIDATE_BOOL) && method_exists($browsershot, 'noSandbox')) {
            $browsershot->noSandbox();
        }

        return $browsershot;
    }
}
