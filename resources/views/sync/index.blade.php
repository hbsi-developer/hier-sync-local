@extends('layout')

@section('content')
<div class="head">
    <div>
        <h1>Payroll Sync</h1>
        <div class="sub">Sends attendance logs to Hier Payroll automatically every 2 minutes.</div>
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

<div class="card" style="margin-bottom:18px">
    <form method="post" action="{{ route('sync.save') }}" style="display:grid; gap:14px">
        @csrf
        <div class="row" style="align-items:center">
            <label class="switch" title="Push enabled"><input type="checkbox" name="enabled" value="1" @checked(old('enabled', $enabled))><span></span></label>
            <div style="flex:1"><b>Push to Hier Payroll</b><div class="muted">When on, unsent logs are sent every 2 minutes while the app is running.</div></div>
        </div>
        <div class="row">
            <label class="f" style="flex:2">Website URL
                <input name="url" type="{{ $httpAllowed ? 'text' : 'url' }}" value="{{ old('url', $url) }}" placeholder="https://your-company.hierpayroll.com" required>
            </label>
            <label class="f">Username
                <input name="username" value="{{ old('username', $username) }}" autocomplete="off" required>
            </label>
        </div>
        <div><button class="primary"><x-icon name="sync"/> Save</button></div>
    </form>
</div>

<div class="card" id="pw-card">
    <b>Password</b>
    <div class="muted" style="margin-bottom:12px">Stored encrypted on this PC only (Windows protected storage). It is never saved in the database or the logs.</div>
    <div id="pw-unavailable" class="muted" hidden>Open this page in the Hier Sync Local desktop app to set the password.</div>
    <div id="pw-ui">
        <div class="row">
            <label class="f" style="flex:2">New password
                <input id="pw" type="password" autocomplete="new-password" placeholder="••••••••">
            </label>
            <span id="pw-state" class="pill"></span>
            <button type="button" class="primary" id="pw-save">Save password</button>
            <button type="button" class="danger" id="pw-clear">Remove</button>
        </div>
        <div class="row" style="margin-top:16px">
            <button type="button" id="btn-test"><x-icon name="plug"/> Test connection</button>
            <button type="button" id="btn-push"><x-icon name="sync"/> Push now</button>
            <span class="muted" id="act-msg"></span>
        </div>
    </div>
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
    if (!api) { $('pw-ui').hidden = true; $('pw-unavailable').hidden = false; return; }

    const refresh = async () => {
        const has = await api.hasPassword();
        const s = $('pw-state');
        s.className = 'pill ' + (has ? 'good' : 'warn');
        s.textContent = has ? 'Password saved' : 'No password';
    };
    const run = async (btn, fn) => {
        btn.disabled = true;
        try { await fn(); } catch (e) { toast(e.message || String(e), false); } finally { btn.disabled = false; }
    };

    $('pw-save').onclick = (e) => run(e.currentTarget, async () => {
        const v = $('pw').value;
        if (!v) return toast('Type the password first.', false);
        const r = await api.setPassword(v);
        $('pw').value = '';
        toast(r.message, r.ok);
        await refresh();
    });
    $('pw-clear').onclick = (e) => run(e.currentTarget, async () => {
        await api.clearPassword();
        toast('Password removed.', true);
        await refresh();
    });
    $('btn-test').onclick = (e) => run(e.currentTarget, async () => {
        $('act-msg').textContent = 'Testing…';
        const r = await api.testConnection();
        $('act-msg').textContent = '';
        toast(r.message, r.ok);
    });
    $('btn-push').onclick = (e) => run(e.currentTarget, async () => {
        $('act-msg').textContent = 'Sending…';
        const r = await api.pushNow();
        toast(r.message, r.ok);
        setTimeout(() => location.reload(), 800);
    });
    refresh();
})();
</script>
@endsection
