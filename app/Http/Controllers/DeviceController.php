<?php

namespace App\Http\Controllers;

use App\Models\AttendanceLog;
use App\Models\Setting;
use App\Models\Device;
use App\Services\LogSyncer;
use App\Services\ZkemClient;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class DeviceController extends Controller
{
    public function index(): View
    {
        return view('devices.index', [
            'devices' => Device::withCount(['logs', 'users'])->orderBy('name')->get(),
            'problem' => ZkemClient::environmentProblem(),
            'today' => AttendanceLog::where('punched_at', '>=', now()->startOfDay())->count(),
            'autoEnabled' => Setting::get('auto_sync_enabled', '1') === '1',
            'autoMinutes' => max(30, (int) Setting::get('auto_sync_minutes', 30)),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        Device::create($this->validated($request));

        return back()->with('ok', 'Device added.');
    }

    public function update(Request $request, Device $device): RedirectResponse
    {
        $device->update($this->validated($request));

        return back()->with('ok', 'Device updated.');
    }

    public function destroy(Device $device): RedirectResponse
    {
        $device->delete();

        return back()->with('ok', 'Device removed (local data deleted).');
    }

    /** Quick reachability probe, polled by the Devices page to show Connected: Yes/No. */
    public function status(Device $device): \Illuminate\Http\JsonResponse
    {
        $probe = @fsockopen($device->ip, $device->port, $errno, $errstr, 2);
        if ($probe) {
            fclose($probe);
        }

        return response()->json(['connected' => (bool) $probe]);
    }

    public function test(Device $device): RedirectResponse
    {
        return $this->run(function (ZkemClient $zk) use ($device) {
            $sn = $zk->serialNumber();
            $time = $zk->deviceTime();
            $device->update(['serial_number' => $sn]);

            return "Connected. Serial: ".($sn ?? 'n/a').'. Device time: '.($time?->toDateTimeString() ?? 'n/a');
        }, $device);
    }

    public function syncLogs(Device $device, LogSyncer $syncer): RedirectResponse
    {
        try {
            $new = $syncer->sync($device);

            return back()->with('ok', "Attendance synced: {$new} new record(s).");
        } catch (\Throwable $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    public function autoSyncSettings(Request $request): RedirectResponse
    {
        $data = $request->validate(['minutes' => 'required|integer|between:30,1440']);
        Setting::put('auto_sync_enabled', $request->boolean('enabled') ? '1' : '0');
        Setting::put('auto_sync_minutes', $data['minutes']);

        return back()->with('ok', 'Auto sync settings saved.');
    }

    public function toggleAuto(Device $device): RedirectResponse
    {
        $device->update(['auto_sync' => ! $device->auto_sync]);

        return back()->with('ok', 'Auto sync '.($device->auto_sync ? 'enabled' : 'disabled')." for {$device->name}.");
    }
    public function syncUsers(Device $device): RedirectResponse
    {
        return $this->run(function (ZkemClient $zk) use ($device) {
            $users = $zk->users();
            DB::transaction(function () use ($users, $device) {
                foreach ($users as $u) {
                    $device->users()->updateOrCreate(['enroll_number' => $u['enroll_number']], $u);
                }
            });

            return 'Users synced: '.count($users).' on device.';
        }, $device);
    }

    public function syncTime(Device $device): RedirectResponse
    {
        return $this->run(
            fn (ZkemClient $zk) => $zk->syncTime() ? 'Device clock set to this PC\'s time.' : 'Device refused the time update.',
            $device
        );
    }

    private function run(callable $action, Device $device): RedirectResponse
    {
        try {
            $zk = (new ZkemClient($device->machine_number))->connect($device->ip, $device->port);
            $message = $action($zk);
            $zk->disconnect();

            return back()->with('ok', $message);
        } catch (\Throwable $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'name' => 'required|string|max:100',
            'ip' => 'required|string|max:100',
            'port' => 'required|integer|between:1,65535',
            'machine_number' => 'required|integer|min:1',
        ]);
    }
}
