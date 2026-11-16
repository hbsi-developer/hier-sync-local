<?php

namespace App\Http\Controllers;

use App\Models\Device;
use App\Models\DeviceUser;
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
                $q->where('name', 'like', $term)->orWhere('enroll_number', 'like', $term)->orWhere('card_number', 'like', $term);
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
            'name' => 'nullable|string|max:24',
            'card_number' => 'nullable|digits_between:1,10',
        ]);
        $card = $data['card_number'] ?? '';

        if ($card !== '' && DeviceUser::where('device_id', $user->device_id)
            ->where('id', '!=', $user->id)
            ->where('card_number', $card)
            ->exists()) {
            return back()->with('error', "Card {$card} is already assigned to another user on this device.");
        }

        try {
            $device = $user->device;
            $zk = (new ZkemClient($device->machine_number))->connect($device->ip, $device->port);
            $saved = $zk->setUser($user->enroll_number, $card, filled($data['name'] ?? null) ? $data['name'] : null);
            $zk->disconnect();

            $user->update([
                'name' => $saved['name'] !== '' ? $saved['name'] : $user->name,
                'card_number' => $saved['card_number'] !== '' ? $saved['card_number'] : null,
            ]);

            return back()->with('ok', "Updated #{$user->enroll_number} on {$device->name}.");
        } catch (\Throwable $e) {
            return back()->with('error', $e->getMessage());
        }
    }
}
