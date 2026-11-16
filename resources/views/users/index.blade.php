@extends('layout')

@section('content')
<div class="head">
    <div>
        <h1>Users</h1>
        <div class="sub">{{ number_format($users->total()) }} user(s). Edit a card number to write it to the device.</div>
    </div>
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
        <label class="f">Search<input name="q" value="{{ request('q') }}" placeholder="Name, enroll no. or card no."></label>
        <button class="primary">Filter</button>
        @if (request()->hasAny(['device_id', 'q'])) <a class="btn" href="{{ route('users.index') }}">Reset</a> @endif
    </form>
</div>

<div class="card tbl">
    <table>
        <thead><tr><th>Employee</th><th>Card no.</th><th>Device</th><th>Status</th><th></th></tr></thead>
        <tbody>
        @forelse ($users as $u)
            <tr>
                <td>
                    <div class="who">
                        <div class="av">{{ strtoupper(mb_substr($u->name ?: $u->enroll_number, 0, 1)) }}</div>
                        <div><b>{{ $u->name ?: 'Unnamed' }}</b><div class="muted num" style="font-size:12px">#{{ $u->enroll_number }}</div></div>
                    </div>
                </td>
                <td class="num">{!! $u->card_number ? e($u->card_number) : '<span class="muted">—</span>' !!}</td>
                <td class="muted">{{ $u->device->name }}</td>
                <td><span class="pill {{ $u->enabled ? 'good' : 'bad' }}">{{ $u->enabled ? 'Enabled' : 'Disabled' }}</span></td>
                <td style="text-align:right">
                    <button class="sm" type="button"
                        data-action="{{ route('users.update', $u) }}" data-name="{{ $u->name }}" data-card="{{ $u->card_number }}"
                        data-title="#{{ $u->enroll_number }} on {{ $u->device->name }}" onclick="editUser(this)"><x-icon name="edit"/> Edit</button>
                </td>
            </tr>
        @empty
            <tr><td colspan="5"><div class="empty"><div class="ic"><x-icon name="users"/></div>No users. Use <b>Pull users</b> on the Devices page.</div></td></tr>
        @endforelse
        </tbody>
    </table>
    @if ($users->hasPages())
    <div class="pager">
        <span class="muted">Page {{ $users->currentPage() }} of {{ $users->lastPage() }}</span>
        <span style="display:flex; gap:8px">
            @if ($users->previousPageUrl()) <a class="btn sm" href="{{ $users->previousPageUrl() }}">&larr; Previous</a> @endif
            @if ($users->nextPageUrl()) <a class="btn sm" href="{{ $users->nextPageUrl() }}">Next &rarr;</a> @endif
        </span>
    </div>
    @endif
</div>

<dialog id="user-dlg">
    <form method="post" id="user-form">
        @csrf @method('PUT')
        <h2>Edit user</h2>
        <div class="muted" id="user-title"></div>
        <label class="f">Name<input name="name" id="u-name" maxlength="24"></label>
        <label class="f">Card number<input name="card_number" id="u-card" inputmode="numeric" pattern="[0-9]{1,10}" maxlength="10" placeholder="Leave blank to clear the card"></label>
        <div class="foot"><button type="button" id="u-cancel" onclick="this.closest('dialog').close()">Cancel</button><button class="primary" id="u-save">Save to device</button></div>
    </form>
</dialog>
<style>
    .spin { width:14px; height:14px; border-radius:50%; border:2px solid currentColor; border-right-color:transparent; animation:spin .7s linear infinite; }
    @keyframes spin { to { transform:rotate(360deg); } }
</style>
<script>
    // Writing to the device can take several seconds; show progress and block double submits.
    document.getElementById('user-form').addEventListener('submit', function () {
        const save = document.getElementById('u-save');
        // Defer disabling so the form still submits with its values.
        setTimeout(() => {
            save.disabled = true;
            save.innerHTML = '<span class="spin"></span> Saving to device…';
            document.getElementById('u-cancel').disabled = true;
            this.querySelectorAll('input').forEach((i) => (i.readOnly = true));
        }, 0);
    });
    // Closing with Esc mid-save would hide the progress, so block it.
    document.getElementById('user-dlg').addEventListener('cancel', (e) => {
        if (document.getElementById('u-save').disabled) e.preventDefault();
    });
</script>
<script>
    function editUser(b) {
        document.getElementById('user-form').action = b.dataset.action;
        document.getElementById('user-title').textContent = b.dataset.title;
        document.getElementById('u-name').value = b.dataset.name;
        document.getElementById('u-card').value = b.dataset.card;
        document.getElementById('user-dlg').showModal();
    }
</script>
@endsection
