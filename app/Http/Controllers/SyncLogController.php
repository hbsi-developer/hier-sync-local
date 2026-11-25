<?php

namespace App\Http\Controllers;

use App\Models\AttendanceLog;
use App\Models\Device;
use App\Models\SyncLog;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SyncLogController extends Controller
{
    public function index(Request $request): View
    {
        return view('sync-logs.index', [
            'logs' => $this->query($request)->paginate(50)->withQueryString(),
            'devices' => Device::orderBy('name')->get(),
            'types' => SyncLog::TYPES,
            'retention' => SyncLog::RETENTION_DAYS,
            'errors24h' => SyncLog::where('level', 'error')->where('created_at', '>=', now()->subDay())->count(),
            'pushed24h' => (int) SyncLog::where('type', 'push')->where('level', 'success')->where('created_at', '>=', now()->subDay())->sum('records'),
            'rejected' => AttendanceLog::with('device')->whereNull('sent_at')->whereNotNull('push_error')->orderBy('punched_at')->limit(25)->get(),
            'rejectedTotal' => AttendanceLog::whereNull('sent_at')->whereNotNull('push_error')->count(),
        ]);
    }

    public function export(Request $request): StreamedResponse
    {
        $query = $this->query($request);

        return response()->streamDownload(function () use ($query) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['Time', 'Type', 'Level', 'Device', 'Serial', 'Records', 'Duration ms', 'Message']);
            $query->lazy()->each(fn (SyncLog $l) => fputcsv($out, [
                $l->created_at?->toDateTimeString(), $l->type_label, $l->level, $l->device_name, $l->device_serial,
                $l->records, $l->duration_ms, $l->message,
            ]));
            fclose($out);
        }, 'sync-logs-'.now()->format('Ymd-His').'.csv', ['Content-Type' => 'text/csv']);
    }

    private function query(Request $request)
    {
        return SyncLog::query()->orderByDesc('id')
            ->when($request->filled('device_id'), fn ($q) => $q->where('device_id', (int) $request->device_id))
            ->when($request->filled('level') && in_array($request->level, ['success', 'error', 'warning', 'info'], true), fn ($q) => $q->where('level', $request->level))
            ->when($request->filled('type') && array_key_exists($request->type, SyncLog::TYPES), fn ($q) => $q->where('type', $request->type))
            ->when($request->filled('from'), fn ($q) => $q->where('created_at', '>=', $request->date('from')->startOfDay()))
            ->when($request->filled('to'), fn ($q) => $q->where('created_at', '<=', $request->date('to')->endOfDay()));
    }
}
