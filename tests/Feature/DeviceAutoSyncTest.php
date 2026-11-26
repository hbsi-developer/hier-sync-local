<?php

namespace Tests\Feature;

use App\Console\Commands\AutoSyncDevices;
use App\Models\Device;
use App\Models\SyncLog;
use App\Services\LogSyncer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DeviceAutoSyncTest extends TestCase
{
    use RefreshDatabase;

    /** Loopback port 1 refuses connections at once, so the device counts as unreachable without a real device. */
    private function offlineDevice(): Device
    {
        return Device::create(['name' => 'Offline', 'ip' => '127.0.0.1', 'port' => 1]);
    }

    public function test_wait_grows_while_failing_and_resets_when_healthy(): void
    {
        $this->assertSame(30, AutoSyncDevices::waitMinutes(30, 0));
        $this->assertSame([5, 10, 20, 30, 30], array_map(fn ($n) => AutoSyncDevices::waitMinutes(30, $n), [1, 2, 3, 4, 9]));
    }

    public function test_repeated_failures_from_the_timer_are_logged_once(): void
    {
        $device = $this->offlineDevice();
        $syncer = app(LogSyncer::class);

        $syncer->syncAndRecord($device->fresh(), true);
        $syncer->syncAndRecord($device->fresh(), true);
        $syncer->syncAndRecord($device->fresh(), true);

        $this->assertSame(1, SyncLog::where('type', 'device_sync')->count());
        $this->assertSame(3, $device->fresh()->sync_failures);
        $this->assertFalse($device->fresh()->last_sync_ok);
    }

    public function test_manual_failures_are_always_logged(): void
    {
        $device = $this->offlineDevice();
        $syncer = app(LogSyncer::class);

        $syncer->syncAndRecord($device->fresh());
        $syncer->syncAndRecord($device->fresh());

        $this->assertSame(2, SyncLog::where('type', 'device_sync')->count());
    }

    public function test_command_skips_a_failing_device_until_its_backoff_has_passed(): void
    {
        $device = $this->offlineDevice();
        $device->update(['last_sync_attempt_at' => now()->subMinutes(3), 'last_sync_ok' => false, 'sync_failures' => 1]);

        $this->artisan('devices:auto-sync')->assertSuccessful();
        $this->assertSame(1, $device->fresh()->sync_failures, 'still inside the 5 minute backoff');

        $device->update(['last_sync_attempt_at' => now()->subMinutes(6)]);
        $this->artisan('devices:auto-sync')->assertSuccessful();
        $this->assertSame(2, $device->fresh()->sync_failures);
        $this->assertSame(0, SyncLog::where('type', 'device_sync')->count(), 'repeat failure stays quiet');
    }
}
