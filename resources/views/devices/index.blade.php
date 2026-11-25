@extends('layout')

@section('content')
<div class="head">
    <div>
        <h1>Devices</h1>
        <div class="sub">Connect ZKTeco terminals and pull their attendance.</div>
    </div>
    <button class="primary" onclick="document.getElementById('add-dlg').showModal()"><x-icon name="plus"/> Add device</button>
</div>

<div class="grid stats">
    <div class="card stat"><div class="ic"><x-icon name="cpu"/></div><div><b>{{ $devices->count() }}</b><span>Devices</span></div></div>
    <div class="card stat"><div class="ic"><x-icon name="users"/></div><div><b>{{ number_format($devices->sum('users_count')) }}</b><span>Enrolled users</span></div></div>
    <div class="card stat"><div class="ic"><x-icon name="list"/></div><div><b>{{ number_format($devices->sum('logs_count')) }}</b><span>Attendance records</span></div></div>
    <div class="card stat"><div class="ic"><x-icon name="clock"/></div><div><b>{{ number_format($today) }}</b><span>Punches today</span></div></div>
</div>

@if ($devices->isEmpty())
    <div class="card empty">
        <div class="ic"><x-icon name="cpu"/></div>
        <b style="color:var(--text)">No devices yet</b>
        <p>Add your first ZKTeco terminal by its IP address.</p>
        <button class="primary" onclick="document.getElementById('add-dlg').showModal()"><x-icon name="plus"/> Add device</button>
    </div>
@else
<div class="grid devices">
    @foreach ($devices as $d)
        <div class="card dev">
            <div class="dev-top">
                <div>
                    <h3>{{ $d->name }}</h3>
                    <div class="addr">{{ $d->ip }}:{{ $d->port }} · Machine {{ $d->machine_number }}@if ($d->serial_number) · {{ $d->serial_number }}@endif</div>
                </div>
                @if (! $d->last_sync_attempt_at && ! $d->last_synced_at)
                    <span class="pill"><span class="dot"></span> Not synced</span>
                @elseif ($d->last_sync_attempt_at && $d->last_sync_ok === false)
                    <span class="pill bad" title="{{ $d->last_sync_message }}"><span class="dot"></span> Sync failed</span>
                @else
                    <span class="pill good"><span class="dot"></span> Synced</span>
                @endif
            </div>

            <div class="kv" style="grid-template-columns:repeat(4,1fr)">
                <div><b><span class="pill conn" data-url="{{ route('devices.status', $d) }}">Checking…</span></b><span>Connected</span></div>
                <div><b>{{ number_format($d->users_count) }}</b><span>Users</span></div>
                <div><b>{{ number_format($d->logs_count) }}</b><span>Records</span></div>
                <div><b style="font-size:14px; padding-top:4px">{{ $d->last_synced_at?->diffForHumans(short: true) ?? '—' }}</b><span>Last sync</span></div>
            </div>

            @if ($d->last_sync_ok === false && $d->last_sync_message)
                <div class="pill bad" style="border-radius:10px; margin-bottom:12px; white-space:normal">{{ \Illuminate\Support\Str::limit($d->last_sync_message, 110) }}</div>
            @endif

            <div class="dev-actions">
                <form method="post" action="{{ route('devices.sync-logs', $d) }}">@csrf<button class="primary sm"><x-icon name="down"/> Pull logs</button></form>
                <form method="post" action="{{ route('devices.sync-users', $d) }}">@csrf<button class="sm"><x-icon name="users"/> Pull users</button></form>
                <form method="post" action="{{ route('devices.test', $d) }}">@csrf<button class="sm"><x-icon name="plug"/> Test</button></form>

                <details class="more" style="margin-left:auto">
                    <summary><span class="btn sm" style="padding:6px 9px"><x-icon name="more"/></span></summary>
                    <div class="menu">
                        <button type="button" data-device="{{ json_encode($d->only(['id', 'name', 'ip', 'port', 'machine_number'])) }}" onclick="openEdit(JSON.parse(this.dataset.device))"><x-icon name="edit"/> Edit device</button>
                        <form method="post" action="{{ route('devices.sync-time', $d) }}">@csrf<button><x-icon name="clock"/> Set device time</button></form>
                        <form method="post" action="{{ route('devices.destroy', $d) }}" onsubmit="return confirm('Remove this device and its local data?')">@csrf @method('DELETE')<button class="danger"><x-icon name="trash"/> Remove device</button></form>
                    </div>
                </details>
            </div>

            <div class="auto">
                <div>
                    <b>Auto sync</b>
                    @if ($d->last_sync_attempt_at)
                        <div class="muted" style="font-size:12px">Last run {{ $d->last_sync_attempt_at->diffForHumans() }}</div>
                    @endif
                </div>
                <form method="post" action="{{ route('devices.toggle-auto', $d) }}">
                    @csrf
                    <span class="muted">{{ $d->auto_sync ? 'On' : 'Off' }}</span>
                    <label class="switch"><input type="checkbox" @checked($d->auto_sync) onchange="this.form.submit()"><span></span></label>
                </form>
            </div>
        </div>
    @endforeach
</div>
@endif

<dialog id="add-dlg">
    <form method="post" action="{{ route('devices.store') }}">
        @csrf
        <h2>Add device</h2>
        <div class="muted">Find the IP on the terminal under Comm. &rarr; Ethernet.</div>
        <label class="f">Name<input name="name" required placeholder="Main entrance"></label>
        <label class="f">IP address<input name="ip" required placeholder="192.168.1.201"></label>
        <div class="two">
            <label class="f">Port<input name="port" type="number" value="4370" required></label>
            <label class="f">Machine no.<input name="machine_number" type="number" value="1" min="1" required></label>
        </div>
        <div class="foot"><button type="button" onclick="this.closest('dialog').close()">Cancel</button><button class="primary">Add device</button></div>
    </form>
</dialog>

<dialog id="edit-dlg">
    <form method="post" id="edit-form">
        @csrf @method('PUT')
        <h2>Edit device</h2>
        <label class="f">Name<input name="name" id="e-name" required></label>
        <label class="f">IP address<input name="ip" id="e-ip" required></label>
        <div class="two">
            <label class="f">Port<input name="port" id="e-port" type="number" required></label>
            <label class="f">Machine no.<input name="machine_number" id="e-mn" type="number" min="1" required></label>
        </div>
        <div class="foot"><button type="button" onclick="this.closest('dialog').close()">Cancel</button><button class="primary">Save changes</button></div>
    </form>
</dialog>

<script>
    // Probe each device in turn (the local server handles one request at a time).
    (async () => {
        for (const el of document.querySelectorAll('.pill.conn')) {
            try {
                const { connected } = await (await fetch(el.dataset.url)).json();
                el.className = 'pill conn ' + (connected ? 'good' : 'bad');
                el.innerHTML = '<span class="dot"></span> ' + (connected ? 'YES' : 'NO');
            } catch (e) { el.className = 'pill conn bad'; el.textContent = 'NO'; }
        }
    })();

    function openEdit(d) {
        document.getElementById('edit-form').action = '{{ url('/devices') }}/' + d.id;
        document.getElementById('e-name').value = d.name;
        document.getElementById('e-ip').value = d.ip;
        document.getElementById('e-port').value = d.port;
        document.getElementById('e-mn').value = d.machine_number;
        document.querySelectorAll('details.more[open]').forEach((x) => (x.open = false));
        document.getElementById('edit-dlg').showModal();
    }
</script>
@endsection
