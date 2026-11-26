<?php

namespace App\Console\Commands;

use App\Models\Device;
use App\Models\Setting;
use App\Services\LogSyncer;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

class AutoSyncDevices extends Command
{
    protected $signature = 'devices:auto-sync {--force : Ignore the interval and the enabled switch}';

    protected $description = 'Pull attendance logs from every device that is due for an automatic sync';

    public function handle(LogSyncer $syncer): int
    {
        if (! $this->option('force') && Setting::get('auto_sync_enabled', '1') !== '1') {
            return self::SUCCESS;
        }

        $minutes = max(5, (int) Setting::get('auto_sync_minutes', 30));

        // The desktop shell ticks every minute; skip if a previous run is still going.
        $lock = Cache::lock('devices-auto-sync', 600);
        if (! $lock->get()) {
            return self::SUCCESS;
        }

        try {
            Device::where('auto_sync', true)->orderBy('id')->get()
                ->filter(fn (Device $d) => $this->option('force')
                    || ! $d->last_sync_attempt_at
                    || $d->last_sync_attempt_at->lte(now()->subMinutes(self::waitMinutes($minutes, $d->sync_failures))))
                ->each(function (Device $d) use ($syncer) {
                    $this->line("{$d->name}: ".$syncer->syncAndRecord($d, ! $this->option('force')));
                });
        } finally {
            $lock->release();
        }

        return self::SUCCESS;
    }

    /** Minutes to wait before the next attempt: the interval when healthy, 5, 10, 20, then 30 while failing. */
    public static function waitMinutes(int $interval, int $failures): int
    {
        return $failures <= 0 ? $interval : min(5 * 2 ** min($failures - 1, 3), 30);
    }
}