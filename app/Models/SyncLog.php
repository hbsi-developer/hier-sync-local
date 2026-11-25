<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/** One line in the activity history (push, device sync, user pull, connection test, settings, skipped runs). */
class SyncLog extends Model
{
    public const UPDATED_AT = null;

    public const RETENTION_DAYS = 30;

    public const TYPES = [
        'push' => 'Push to payroll',
        'device_sync' => 'Device sync',
        'pull_users' => 'Pull users',
        'test' => 'Connection test',
        'settings' => 'Settings',
        'skipped' => 'Skipped',
    ];

    protected $guarded = [];

    protected $casts = ['created_at' => 'datetime'];

    public function device(): BelongsTo
    {
        return $this->belongsTo(Device::class);
    }

    public function getTypeLabelAttribute(): string
    {
        return self::TYPES[$this->type] ?? $this->type;
    }

    /**
     * Never throws: a logging problem must not break a sync or a push.
     * $dedupe skips the entry when the newest one of the same type and device has the same message.
     */
    public static function record(string $type, string $level, string $message, ?Device $device = null, ?int $records = null, ?int $durationMs = null, bool $dedupe = false): void
    {
        try {
            if ($dedupe) {
                $last = static::where('type', $type)->where('device_id', $device?->id)->latest('id')->first();
                if ($last && $last->message === Str::limit($message, 500, '…') && $last->level === $level) {
                    return;
                }
            }

            static::create([
                'type' => $type,
                'level' => $level,
                'device_id' => $device?->id,
                'device_name' => $device?->name,
                'device_serial' => $device?->serial_number,
                'message' => Str::limit($message, 500, '…'),
                'records' => $records,
                'duration_ms' => $durationMs,
                'created_at' => now(),
            ]);

            static::pruneOncePerDay();
        } catch (\Throwable) {
        }
    }

    private static function pruneOncePerDay(): void
    {
        $today = now()->toDateString();
        if (Setting::get('sync_log_pruned_on') === $today) {
            return;
        }
        Setting::put('sync_log_pruned_on', $today);
        static::where('created_at', '<', now()->subDays(self::RETENTION_DAYS))->delete();
    }
}
