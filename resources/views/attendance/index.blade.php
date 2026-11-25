@extends('layout')

@section('content')
<div class="head">
    <div>
        <h1>Attendance</h1>
        <div class="sub">{{ number_format($logs->total()) }} record(s) match your filters.</div>
    </div>
    <a class="btn" href="{{ route('attendance.export', request()->query()) }}"><x-icon name="file"/> Export CSV</a>
</div>

<div class="card" style="margin-bottom:18px">
    <form method="get" class="row">
        <label class="f">Device
            <select name="device_id">
                <option value="">All devices</option>
                @foreach ($devices as $d)
                    <option value="{{ $d->id }}" @selected(request('device_id') == $d->id)>{{ $d->name }}</option>
                @endforeach
            </select>
        </label>
        <label class="f">Enroll no.<input name="enroll" value="{{ request('enroll') }}" placeholder="e.g. 1001"></label>
        <label class="f">From<input type="date" name="from" value="{{ request('from') }}"></label>
        <label class="f">To<input type="date" name="to" value="{{ request('to') }}"></label>
        <button class="primary">Filter</button>
        @if (request()->hasAny(['device_id', 'enroll', 'from', 'to']))
            <a class="btn" href="{{ route('attendance.index') }}">Reset</a>
        @endif
    </form>
</div>

<div class="card tbl">
    <table>
        <thead><tr><th>Employee</th><th>Date &amp; time</th><th>Type</th><th>Verified by</th><th>Device</th></tr></thead>
        <tbody>
        @forelse ($logs as $l)
            @php $name = $names[$l->device_id.'|'.$l->enroll_number] ?? null; @endphp
            <tr>
                <td>
                    <div class="who">
                        <div class="av">{{ strtoupper(mb_substr($name ?: $l->enroll_number, 0, 1)) }}</div>
                        <div><b>{{ $name ?: 'Unknown' }}</b><div class="muted num" style="font-size:12px">#{{ $l->enroll_number }}</div></div>
                    </div>
                </td>
                <td class="num">{{ $l->punched_at->format('M j, Y') }} <span class="muted">{{ $l->punched_at->format('g:i:s A') }}</span></td>
                <td><span class="pill {{ in_array($l->in_out_mode, [0, 3, 4]) ? 'good' : 'warn' }}">{{ $l->in_out_label }}</span></td>
                <td>{{ $l->verify_label }}</td>
                <td class="muted">{{ $l->device->name }}</td>
            </tr>
        @empty
            <tr><td colspan="5"><div class="empty"><div class="ic"><x-icon name="list"/></div>No records. Use <b>Pull logs</b> on the Devices page.</div></td></tr>
        @endforelse
        </tbody>
    </table>
    @if ($logs->hasPages())
    <div class="pager">
        <span class="muted">Page {{ $logs->currentPage() }} of {{ $logs->lastPage() }}</span>
        <span style="display:flex; gap:8px">
            @if ($logs->previousPageUrl()) <a class="btn sm" href="{{ $logs->previousPageUrl() }}">&larr; Newer</a> @endif
            @if ($logs->nextPageUrl()) <a class="btn sm" href="{{ $logs->nextPageUrl() }}">Older &rarr;</a> @endif
        </span>
    </div>
    @endif
</div>
@endsection
