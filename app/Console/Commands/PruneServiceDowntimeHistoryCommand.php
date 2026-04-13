<?php

namespace App\Console\Commands;

use App\Models\ServiceDowntime;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class PruneServiceDowntimeHistoryCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'monitor:prune-downtime-history';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Delete resolved service downtime history and screenshots older than the configured retention window.';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $retentionDays = max(1, (int) config('services.monitoring.downtime_history_retention_days', 90));
        $cutoff = now()->subDays($retentionDays);
        $prunedCount = 0;

        ServiceDowntime::query()
            ->whereNotNull('ended_at')
            ->where('ended_at', '<', $cutoff)
            ->orderBy('id')
            ->chunkById(100, function ($downtimes) use (&$prunedCount): void {
                foreach ($downtimes as $downtime) {
                    if ($downtime->hasScreenshot()) {
                        Storage::disk((string) $downtime->screenshot_disk)->delete((string) $downtime->screenshot_path);
                    }

                    $downtime->delete();
                    $prunedCount++;
                }
            });

        $this->info("Pruned {$prunedCount} downtime record(s) older than {$retentionDays} day(s).");

        return self::SUCCESS;
    }
}
