<?php

namespace App\Services;

use App\Models\AttendanceLog;
use App\Models\Device;
use App\Models\SyncLog;
use Illuminate\Support\Facades\DB;

class LogSyncer
{
    /** Pulls attendance from the device and stores records not seen before. Returns the number of new records. */
    public function sync(Device $device): int
    {
        $zk = (new ZkemClient($device->machine_number))->connect($device->ip, $device->port);

        // The push to Hier Payroll identifies logs by device serial, so every device needs one.
        // Fetch it here instead of relying on someone pressing "Test"; a failure must not block the sync.
        if (! $device->serial_number) {
            try {
                if ($sn = $zk->serialNumber()) {
                    $device->update(['serial_number' => $sn]);
                }
            } catch (\Throwable) {
            }
        }

        $rows = $zk->attendance();
        $zk->disconnect();

        $before = $device->logs()->count();
        $now = now();

        // One transaction and chunked inserts: row-by-row writes are far too slow for thousands of punches.
        DB::transaction(function () use ($rows, $device, $now) {
            foreach (array_chunk($rows, 500) as $chunk) {
                AttendanceLog::insertOrIgnore(array_map(fn ($row) => [
                    'device_id' => $device->id,
                    'enroll_number' => $row['enroll_number'],
                    'punched_at' => $row['punched_at']->toDateTimeString(),
                    'verify_mode' => $row['verify_mode'],
                    'in_out_mode' => $row['in_out_mode'],
                    'work_code' => $row['work_code'],
                    'created_at' => $now,
                    'updated_at' => $now,
                ], $chunk));
            }
        });

        $new = $device->logs()->count() - $before;
        $device->update(['last_synced_at' => $now]);

        return $new;
    }

    /**
     * sync() that records the outcome on the device instead of throwing.
     *
     * With $quietRepeats (timer runs), a failure that repeats the previous one is not written to the
     * activity history again: only the first failure and the recovery are logged.
     */
    public function syncAndRecord(Device $device, bool $quietRepeats = false): string
    {
        $alreadyFailing = $device->last_sync_ok === false;
        $start = microtime(true);
        $new = null;
        try {
            $new = $this->sync($device);
            $message = "{$new} new record(s)";
            $ok = true;
        } catch (\Throwable $e) {
            $message = mb_strimwidth($e->getMessage(), 0, 240, '…');
            $ok = false;
        }

        if (! ($quietRepeats && ! $ok && $alreadyFailing)) {
            SyncLog::record('device_sync', $ok ? 'success' : 'error', $ok ? "Pulled {$message}." : $message, $device->fresh() ?? $device, $new, (int) round((microtime(true) - $start) * 1000));
        }

        $device->update([
            'last_sync_attempt_at' => now(),
            'last_sync_ok' => $ok,
            'last_sync_message' => $message,
            'sync_failures' => $ok ? 0 : $device->sync_failures + 1,
        ]);

        return $message;
    }
}