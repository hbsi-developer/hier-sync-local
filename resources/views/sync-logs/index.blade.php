@extends('layout')

@section('content')
<style>
    .filters { display:grid; grid-template-columns:repeat(auto-fit,minmax(150px,1fr)); gap:12px; align-items:end; }
    .msg { max-width:520px; white-space:normal; word-break:break-word; }
    .tbl table { min-width:760px; }
    details.rej summary { cursor:pointer; font-weight:600; }
</style>

<div class="head">
    <div>
        <h1>Sync logs</h1>
        <div class="sub">History of pushes, device syncs, user pulls, tests and setting changes. Entries are kept for {{ $retention }} days.</div>
    </div>
    <a class="btn" href="{{ route('sync-logs.export', request()->query()) }}"><x-icon name="file"/> Export CSV</a>
</div>

<div class="grid stats">
    <div class="card stat"><div class="ic"><x-icon name="list"/></div><div><b>{{ number_format($logs->total()) }}</b><span>Entries shown</span></div></div>
    <div class="card stat"><div class="ic"><x-icon name="sync"/></div><div><b>{{ number_format($pushed24h) }}</b><span>Records pushed (24 h)</span></div></div>
    <div class="card stat"><div class="ic"><x-icon name="clock"/></div><div><b>{{ number_format($errors24h) }}</b><span>Errors (24 h)</span></div></div>
</div>

@if ($rejectedTotal > 0)
    <div class="banner" style="flex-direction:column; align-items:stretch">
        <div style="display:flex; justify-content:space-between; gap:14px; align-items:center">
            <span>{{ number_format($rejectedTotal) }} record(s) were rejected by Hier Payroll and are not being retried.</span>
            <form method="post" action="{{ route('sync.retry-rejected') }}">@csrf<button class="sm">Retry rejected</button></form>
        </div>
        <details class="rej" style="margin-top:8px">
            <summary>Show the first {{ $rejected->count() }}</summary>
            <table style="margin-top:8px">
                <thead><tr><th>Device</th><th>Enroll No</th><th>Punched at</th><th>Reason</th></tr></thead>
                <tbody>
                @foreach ($rejected as $r)
                    <tr><td>{{ $r->device->name ?? '—' }}</td><td class="num">{{ $r->enroll_number }}</td><td class="num">{{ $r->punched_at->format('Y-m-d H:i:s') }}</td><td>{{ $r->push_error }}</td></tr>
                @endforeach
                </tbody>
            </table>
        </details>
    </div>
@endif

<div class="card" style="margin-bottom:18px">
    <form method="get" class="filters">
        <label class="f">Device
            <select name="device_id"><option value="">All devices</option>
                @foreach ($devices as $d) <option value="{{ $d->id }}" @selected(request('device_id') == $d->id)>{{ $d->name }}</option> @endforeach
            </select>
        </label>
        <label class="f">Type
            <select name="type"><option value="">All types</option>
                @foreach ($types as $key => $label) <option value="{{ $key }}" @selected(request('type') === $key)>{{ $label }}</option> @endforeach
            </select>
        </label>
        <label class="f">Level
            <select name="level"><option value="">All levels</option>
                @foreach (['success' => 'Success', 'error' => 'Error', 'warning' => 'Warning', 'info' => 'Info'] as $key => $label) <option value="{{ $key }}" @selected(request('level') === $key)>{{ $label }}</option> @endforeach
            </select>
        </label>
        <label class="f">From <input type="date" name="from" value="{{ request('from') }}"></label>
        <label class="f">To <input type="date" name="to" value="{{ request('to') }}"></label>
        <div style="display:flex; gap:8px">
            <button class="primary">Filter</button>
            @if (request()->hasAny(['device_id', 'type', 'level', 'from', 'to'])) <a class="btn" href="{{ route('sync-logs.index') }}">Reset</a> @endif
        </div>
    </form>
</div>

<div class="card tbl">
    <table>
        <thead><tr><th>Time</th><th>Type</th><th>Level</th><th>Device</th><th>Records</th><th>Message</th></tr></thead>
        <tbody>
        @forelse ($logs as $l)
            <tr>
                <td class="num" title="{{ $l->created_at }}">{{ $l->created_at?->format('M d, H:i:s') }}</td>
                <td>{{ $l->type_label }}</td>
                <td>
                    @php $cls = ['success' => 'good', 'error' => 'bad', 'warning' => 'warn', 'info' => 'info'][$l->level] ?? ''; @endphp
                    <span class="pill {{ $cls }}"><span class="dot"></span> {{ ucfirst($l->level) }}</span>
                </td>
                <td>{{ $l->device_name ?? '—' }}@if ($l->device_serial)<div class="muted" style="font-size:12px">{{ $l->device_serial }}</div>@endif</td>
                <td class="num">{{ $l->records !== null ? number_format($l->records) : '—' }}@if ($l->duration_ms !== null)<div class="muted" style="font-size:12px">{{ number_format($l->duration_ms) }} ms</div>@endif</td>
                <td class="msg">{{ $l->message }}</td>
            </tr>
        @empty
            <tr><td colspan="6"><div class="empty"><div class="ic"><x-icon name="list"/></div>No entries yet. They appear here as pushes and syncs run.</div></td></tr>
        @endforelse
        </tbody>
    </table>
    @if ($logs->hasPages())
        <div class="pager">
            <span class="muted">Page {{ $logs->currentPage() }} of {{ $logs->lastPage() }}</span>
            <span style="display:flex; gap:8px">
                @if ($logs->previousPageUrl()) <a class="btn sm" href="{{ $logs->previousPageUrl() }}">&larr; Previous</a> @endif
                @if ($logs->nextPageUrl()) <a class="btn sm" href="{{ $logs->nextPageUrl() }}">Next &rarr;</a> @endif
            </span>
        </div>
    @endif
</div>
@endsection
