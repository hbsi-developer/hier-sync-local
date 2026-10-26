<?php

namespace App\Services;

use App\Models\AttendanceLog;
use App\Models\Device;
use Illuminate\Support\Facades\DB;

class LogSyncer
{
    /** Pulls attendance from the device and stores records not seen before. Returns the number of new records. */
    public function sync(Device $device): int
    {
        $zk = (new ZkemClient($device->machine_number))->connect($device->ip, $device->port);
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

    /** sync() that records the outcome on the device instead of throwing. */
    public function syncAndRecord(Device $device): string
    {
        try {
            $new = $this->sync($device);
            $message = "{$new} new record(s)";
            $ok = true;
        } catch (\Throwable $e) {
            $message = mb_strimwidth($e->getMessage(), 0, 240, '…');
            $ok = false;
        }

        $device->update(['last_sync_attempt_at' => now(), 'last_sync_ok' => $ok, 'last_sync_message' => $message]);

        return $message;
    }
}