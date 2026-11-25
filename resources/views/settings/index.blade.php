@extends('layout')

@section('content')
<style>
    .stack { display:grid; gap:18px; }
    .card h2 { margin:0 0 2px; font-size:16px; }
    .card .hint { color:var(--muted); font-size:13px; margin-bottom:14px; }
    .toggle-row { display:flex; align-items:center; gap:14px; padding-bottom:16px; border-bottom:1px solid var(--line); }
    .fields { display:grid; grid-template-columns:2fr 1fr; gap:12px; }
    .fields.two { grid-template-columns:1fr 1fr; }
    .actions { display:flex; flex-wrap:wrap; gap:10px; align-items:center; }
    .pill:empty { display:none; }
    @media (max-width:700px) { .fields, .fields.two { grid-template-columns:1fr; } .actions button { flex:1; justify-content:center; } }
</style>

<div class="head">
    <div>
        <h1>Settings</h1>
        <div class="sub">Connection to Hier Payroll and how often devices are synced.</div>
    </div>
</div>

<div class="stack">
    <div class="card">
        <form method="post" action="{{ route('sync.save') }}" id="settings-form" style="display:grid; gap:16px">
            @csrf
            <div class="toggle-row">
                <label class="switch" title="Push enabled"><input type="checkbox" name="enabled" value="1" @checked(old('enabled', $enabled))><span></span></label>
                <div style="flex:1"><b>Push to Hier Payroll</b><div class="muted">When on, today's logs and any unsent older logs are sent on the interval below while the app is running.</div></div>
            </div>
            <div class="fields">
                <label class="f">Website URL
                    <input name="url" type="{{ $httpAllowed ? 'text' : 'url' }}" value="{{ old('url', $url) }}" placeholder="https://your-company.hierpayroll.com" required>
                    @error('url') <span style="color:var(--bad); font-weight:500">{{ $message }}</span> @enderror
                </label>
                <label class="f">Auto push interval (minutes)
                    <input name="interval" type="number" min="1" max="60" step="1" value="{{ old('interval', $interval) }}">
                    @error('interval') <span style="color:var(--bad); font-weight:500">{{ $message }}</span> @enderror
                </label>
            </div>
            <div class="fields two" id="cred-row">
                <label class="f">Username
                    <input name="username" value="{{ old('username', $username) }}" autocomplete="off" required>
                </label>
                <label class="f" id="pw-ui">
                    <span style="display:flex; align-items:center; justify-content:space-between; gap:10px">Password <span id="pw-state" class="pill"></span></span>
                    <input id="pw" type="password" autocomplete="new-password" placeholder="Leave empty to keep the saved password">
                </label>
            </div>
            <div id="pw-unavailable" class="muted" hidden>Open this page in the Hier Sync Local desktop app to set the password and test the connection.</div>
            <div class="hint" id="pw-note" style="margin:0">Stored encrypted on this PC only (Windows protected storage). Never saved in the database or the logs.
                <button type="button" class="sm danger" id="pw-clear" style="margin-left:6px">Remove password</button></div>
            <div class="actions">
                <button class="primary" id="save-btn"><x-icon name="sync"/> Save settings</button>
                <button type="button" id="btn-test"><x-icon name="plug"/> Test connection</button>
                <span class="muted" id="act-msg"></span>
            </div>
        </form>
    </div>

    <div class="card">
        <form method="post" action="{{ route('auto-sync.settings') }}" class="row" style="align-items:center">
            @csrf
            <label class="switch" title="Auto sync"><input type="checkbox" name="enabled" value="1" @checked($autoEnabled)><span></span></label>
            <div style="flex:1; min-width:220px"><b>Device auto-sync</b><div class="muted">Pulls new logs from the devices in the background while the app is open.</div></div>
            <label class="f" style="flex:0 0 190px">Every (min. 5 minutes)
                <input name="minutes" type="number" min="5" max="1440" value="{{ $autoMinutes }}">
            </label>
            <button><x-icon name="sync"/> Save</button>
        </form>
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
    if (!api) {
        $('pw-ui').hidden = true; $('pw-note').hidden = true; $('btn-test').hidden = true;
        $('cred-row').style.gridTemplateColumns = '1fr';
        $('pw-unavailable').hidden = false;
        return;
    }

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

    // One Save button: the password (kept by Electron, never sent to Laravel) is stored first, then the form posts.
    $('settings-form').addEventListener('submit', async (e) => {
        const v = $('pw').value;
        if (!v) return;
        e.preventDefault();
        const btn = $('save-btn');
        btn.disabled = true;
        try {
            const r = await api.setPassword(v);
            if (!r.ok) { toast(r.message, false); btn.disabled = false; return; }
            $('pw').value = '';
            e.target.submit();
        } catch (err) { toast(err.message || String(err), false); btn.disabled = false; }
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
    refresh();
})();
</script>
@endsection
