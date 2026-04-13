<?php

namespace App\Support\Monitoring;

use App\Models\Service;
use App\Models\ServiceDowntime;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

class WebsiteScreenshotter
{
    /**
     * Capture a screenshot for the given service downtime.
     *
     * @return array{disk: string, path: string}|null
     */
    public function capture(Service $service, ServiceDowntime $downtime): ?array
    {
        $disk = (string) config('services.monitoring.downtime_screenshot_disk', 'public');
        $directory = trim((string) config('services.monitoring.downtime_screenshot_directory', 'downtime-screenshots'), '/');
        $filename = Str::slug($service->name).'-'.$downtime->id.'-'.now()->format('YmdHis').'.png';
        $relativePath = $directory.'/'.$filename;
        $temporaryFile = tempnam(sys_get_temp_dir(), 'service-downtime-');

        if ($temporaryFile === false) {
            return null;
        }

        $pngPath = $temporaryFile.'.png';
        @rename($temporaryFile, $pngPath);

        try {
            if ($this->captureWithLaravelScreenshot($service->url, $pngPath) || $this->captureWithBrowsershot($service->url, $pngPath)) {
                Storage::disk($disk)->put($relativePath, file_get_contents($pngPath) ?: '');

                return [
                    'disk' => $disk,
                    'path' => $relativePath,
                ];
            }
        } catch (Throwable $throwable) {
            report($throwable);
        } finally {
            @unlink($pngPath);
        }

        return null;
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
        $browsershot = 'Spatie\\Browsershot\\Browsershot';

        if (! class_exists($browsershot)) {
            return false;
        }

        $browsershot::url($url)->save($path);

        return file_exists($path);
    }
}
