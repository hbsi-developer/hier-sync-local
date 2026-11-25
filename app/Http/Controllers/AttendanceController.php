<?php

namespace App\Http\Controllers;

use App\Models\AttendanceLog;
use App\Models\Device;
use App\Models\DeviceUser;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AttendanceController extends Controller
{
    public function index(Request $request)
    {
        $logs = $this->query($request)->paginate(50)->withQueryString();

        return view('attendance.index', [
            'logs' => $logs,
            'devices' => Device::orderBy('name')->get(),
            'names' => $this->names(),
        ]);
    }

    public function export(Request $request): StreamedResponse
    {
        $names = $this->names();
        $query = $this->query($request);

        return response()->streamDownload(function () use ($query, $names) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['Device', 'Enroll No', 'Name', 'Punched At', 'Type', 'Verify']);
            $query->lazy()->each(fn ($l) => fputcsv($out, [
                $l->device->name, $l->enroll_number, $names[$l->device_id.'|'.$l->enroll_number] ?? '',
                $l->punched_at->toDateTimeString(), $l->in_out_label, $l->verify_label,
            ]));
            fclose($out);
        }, 'attendance-'.now()->format('Ymd-His').'.csv', ['Content-Type' => 'text/csv']);
    }

    /** Names keyed by "device_id|enroll_number": the same enroll number is a different person on each device. */
    private function names(): \Illuminate\Support\Collection
    {
        return DeviceUser::all()->mapWithKeys(fn (DeviceUser $u) => [$u->device_id.'|'.$u->enroll_number => $u->name]);
    }

    private function query(Request $request)
    {
        $q = AttendanceLog::with('device')->orderByDesc('punched_at');

        return $q
            ->when($request->filled('device_id'), fn ($q) => $q->where('device_id', $request->device_id))
            ->when($request->filled('enroll'), fn ($q) => $q->where('enroll_number', $request->enroll))
            ->when($request->filled('from'), fn ($q) => $q->where('punched_at', '>=', $request->date('from')->startOfDay()))
            ->when($request->filled('to'), fn ($q) => $q->where('punched_at', '<=', $request->date('to')->endOfDay()));
    }
}

