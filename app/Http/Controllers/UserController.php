<?php

namespace App\Http\Controllers;

use App\Models\Device;
use App\Models\DeviceUser;
use App\Models\SyncLog;
use App\Services\ZkemClient;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class UserController extends Controller
{
    public function index(Request $request): View
    {
        $users = DeviceUser::with('device')
            ->when($request->filled('device_id'), fn ($q) => $q->where('device_id', $request->device_id))
            ->when($request->filled('q'), fn ($q) => $q->where(function ($q) use ($request) {
                $term = '%'.$request->q.'%';
                $q->where('name', 'like', $term)->orWhere('display_name', 'like', $term)->orWhere('enroll_number', 'like', $term)->orWhere('card_number', 'like', $term);
            }))
            ->orderBy('device_id')
            ->orderByRaw('CAST(enroll_number AS INTEGER)')
            ->paginate(50)
            ->withQueryString();

        return view('users.index', [
            'users' => $users,
            'devices' => Device::orderBy('name')->get(),
            'problem' => ZkemClient::environmentProblem(),
        ]);
    }

    /** Push the edited card number / name to the device, then mirror it locally. */
    public function update(Request $request, DeviceUser $user): RedirectResponse
    {
        $data = $request->validate([
            'name' => 'nullable|string|max:60',
            'card_number' => 'nullable|digits_between:1,10',
            'enabled' => 'nullable|boolean',
        ]);
        // Absent means "leave the device's current state alone".
        $enabled = $request->has('enabled') ? $request->boolean('enabled') : null;
        $card = $data['card_number'] ?? '';

        if ($card !== '' && DeviceUser::where('device_id', $user->device_id)
            ->where('id', '!=', $user->id)
            ->where('card_number', $card)
            ->exists()) {
            return back()->with('error', "Card {$card} is already assigned to another user on this device.");
        }

        try {
            $device = $user->device;
            $zk = app(ZkemClient::class, ['machineNumber' => $device->machine_number])->connect($device->ip, $device->port);
            $asked = filled($data['name'] ?? null) ? trim($data['name']) : null;
            // The terminal gets at most 24 characters; older models keep fewer (8), and tell us what they kept.
            $saved = $zk->setUser($user->enroll_number, $card, $asked === null ? null : mb_substr($asked, 0, 24), $enabled);
            $zk->disconnect();

            $wasEnabled = (bool) $user->enabled;
            $deviceName = $saved['name'] !== '' ? $saved['name'] : $user->name;
            $user->update([
                'name' => $deviceName,
                // Keep the full name here when the device stored a shorter one, so a pull doesn't undo it.
                'display_name' => $asked !== null && $asked !== $deviceName ? $asked : null,
                'card_number' => $saved['card_number'] !== '' ? $saved['card_number'] : null,
                'enabled' => $saved['enabled'] ?? $enabled ?? $wasEnabled,
            ]);

            if ($user->enabled !== $wasEnabled) {
                SyncLog::record('settings', 'info', 'User #'.$user->enroll_number.' '.($user->enabled ? 'enabled' : 'disabled').' on the device.', $device);
            }

            if ($user->display_name !== null) {
                SyncLog::record('settings', 'info', "Device stores '{$deviceName}' for #{$user->enroll_number}; full name '{$asked}' is kept in this app.", $device);

                return back()->with('ok', "Updated #{$user->enroll_number}. The device stores \"{$deviceName}\"; the full name is kept in this app.");
            }

            return back()->with('ok', "Updated #{$user->enroll_number} on {$device->name}.");
        } catch (\Throwable $e) {
            return back()->with('error', $e->getMessage());
        }
    }
}
