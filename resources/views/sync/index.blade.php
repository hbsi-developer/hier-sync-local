@extends('layout')

@section('content')
<style>
    .card h2 { margin:0 0 2px; font-size:16px; }
    .card .hint { color:var(--muted); font-size:13px; margin-bottom:14px; }
    .range { display:grid; grid-template-columns:minmax(0,1fr) minmax(0,1fr) auto; gap:12px; align-items:end; }
    input[type=date] { min-height:40px; }
    @media (max-width:560px) { .range { grid-template-columns:1fr; } .range button { justify-content:center; } }
</style>

<div class="head">
    <div>
        <h1>Payroll Sync</h1>
        <div class="sub">Sends today's attendance logs (and any unsent older ones) to Hier Payroll automatically every {{ $interval }} minute(s).</div>
    </div>
</div>

<div class="grid stats">
    <div class="card stat"><div class="ic"><x-icon name="clock"/></div><div><b>{{ $lastAt ? \Illuminate\Support\Carbon::parse($lastAt)->diffForHumans(short: true) : '—' }}</b><span>Last push</span></div></div>
    <div class="card stat"><div class="ic"><x-icon name="list"/></div><div><b>{{ number_format($unsent) }}</b><span>Unsent records</span></div></div>
    <div class="card stat"><div class="ic"><x-icon name="sync"/></div><div>
        <b style="font-size:14px; padding-top:4px">
            @if ($lastOk === null) <span class="pill"><span class="dot"></span> Not run yet</span>
            @elseif ($lastOk === '1') <span class="pill good"><span class="dot"></span> OK</span>
            @else <span class="pill bad"><span class="dot"></span> Failed</span> @endif
        </b><span>Last result</span></div>
    </div>
</div>

@unless ($configured)
    <div class="banner"><span>Push to Hier Payroll is not set up yet. Add the website URL and credentials in Settings, then turn it on.</span><a class="btn sm" href="{{ route('settings.index') }}">Open Settings</a></div>
@endunless
@if ($lastMessage)
    <div class="card" style="margin-bottom:18px"><b>Last result:</b> {{ $lastMessage }}
        @if ($lastOk === '0' && $nextAttempt > time()) <div class="muted">Next retry after {{ \Illuminate\Support\Carbon::createFromTimestamp($nextAttempt)->diffForHumans() }}. No record is lost; they stay queued in order.</div> @endif
    </div>
@endif
@if ($noSerial > 0)
    <div class="banner"><span>{{ number_format($noSerial) }} record(s) are waiting because their device has no serial number yet. Open Devices and press <b>Test</b> on the device to read it.</span></div>
@endif
@if ($rejected > 0)
    <div class="banner">
        <span>{{ number_format($rejected) }} record(s) were rejected by Hier Payroll (for example an unknown employee) and are not being retried.</span>
        <form method="post" action="{{ route('sync.retry-rejected') }}">@csrf<button class="sm">Retry rejected</button></form>
    </div>
@endif

<div class="card" id="push-card">
    <h2>Push by date range</h2>
    <div class="hint">Sends all logs punched between these dates (inclusive), including ones already sent. Leave one empty for no limit.</div>
    <div id="range-unavailable" class="muted" hidden>Open this page in the Hier Sync Local desktop app to push logs.</div>
    <div class="range" id="range-ui">
        <label class="f">From <input id="range-from" type="date"></label>
        <label class="f">To <input id="range-to" type="date"></label>
        <button type="button" class="primary" id="btn-range"><x-icon name="sync"/> Push range</button>
    </div>
    <div class="muted" id="act-msg" style="margin-top:10px"></div>
</div>

<script>
(() => {
    const api = window.hierSync;
    const $ = (id) => document.getElementById(id);
    const toast = (msg, ok) => {
        const t = document.createElement('div');
        t.className = 'toast ' + (ok ? 'ok' : 'err');
        t.textContent = msg;
        $('toasts').appendChild(t);
        setTimeout(() => t.remove(), ok ? 5000 : 12000);
    };
    if (!api) { $('range-ui').hidden = true; $('range-unavailable').hidden = false; return; }

    $('btn-range').onclick = async (e) => {
        const btn = e.currentTarget;
        const from = $('range-from').value, to = $('range-to').value;
        if (!from && !to) return toast('Pick a start or end date.', false);
        if (from && to && from > to) return toast('The start date is after the end date.', false);
        btn.disabled = true;
        $('act-msg').textContent = 'Sending…';
        try {
            const r = await api.pushRange(from, to);
            toast(r.message, r.ok);
            setTimeout(() => location.reload(), 800);
        } catch (err) {
            toast(err.message || String(err), false);
        } finally {
            btn.disabled = false;
            $('act-msg').textContent = '';
        }
    };
})();
</script>
@endsection
