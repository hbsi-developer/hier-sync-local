<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Hier Sync Local</title>
    <style>
        /* Brand: HBSI Brand Guideline. Primary greens #15DD58 / #02BC4D / #2AF56D, accents #5583ED + #FFCC00, neutrals #212735 + #FFFFFF. Typeface: Montserrat. */
        @font-face { font-family:"Montserrat"; src:url("{{ asset('fonts/Montserrat.ttf') }}") format("truetype"); font-weight:100 900; font-style:normal; font-display:swap; }
        @font-face { font-family:"Montserrat"; src:url("{{ asset('fonts/Montserrat-Italic.ttf') }}") format("truetype"); font-weight:100 900; font-style:italic; font-display:swap; }
        :root {
            --navy:#212735; --brand:#15DD58; --brand-mid:#02BC4D; --brand-bright:#2AF56D; --info:#5583ED; --yellow:#FFCC00;
            --bg:#f3f5f9; --surface:#ffffff; --surface-2:#f6f8fb; --text:#212735; --muted:#6b7385; --line:#e3e7ee;
            --accent:#02BC4D; --accent-ink:#04793a; --accent-soft:#e3f9ec; --accent-text:#212735;
            --good:#04793a; --good-soft:#e3f9ec; --bad:#b42318; --bad-soft:#fdecea; --warn:#8a6500; --warn-soft:#fff6cc;
            --shadow:0 1px 2px rgba(33,39,53,.06), 0 4px 16px rgba(33,39,53,.05); --radius:14px;
        }
        @media (prefers-color-scheme: dark) {
            :root {
                --bg:#171c27; --surface:#212735; --surface-2:#2a3142; --text:#f1f4f9; --muted:#9aa4b8; --line:#353d51;
                --accent:#15DD58; --accent-ink:#2AF56D; --accent-soft:rgba(21,221,88,.14); --accent-text:#212735;
                --good:#2AF56D; --good-soft:rgba(21,221,88,.14); --bad:#f97066; --bad-soft:rgba(249,112,102,.15); --warn:#FFCC00; --warn-soft:rgba(255,204,0,.14);
                --shadow:none;
            }
        }
        * { box-sizing:border-box; }
        body { margin:0; font:14px/1.55 "Montserrat", "Segoe UI", system-ui, sans-serif; font-weight:400; background:var(--bg); color:var(--text); display:flex; min-height:100vh; }
        a { color:inherit; text-decoration:none; }

        /* sidebar */
        .side { width:230px; flex-shrink:0; background:var(--navy); color:#fff; border-right:1px solid var(--navy); padding:20px 14px; display:flex; flex-direction:column; gap:4px; position:sticky; top:0; height:100vh; }
        .brand { display:flex; align-items:center; gap:10px; padding:4px 10px 20px; font-weight:700; font-size:16px; }
        .brand i { width:32px; height:32px; border-radius:50%; background:var(--brand); color:var(--accent-text); display:grid; place-items:center; }
        .nav a { display:flex; align-items:center; gap:10px; padding:9px 12px; border-radius:10px; color:#c3cadb; font-weight:500; }
        .nav a:hover { background:rgba(255,255,255,.07); color:#fff; }
        .nav a.on { background:rgba(21,221,88,.16); color:var(--brand-bright); }
        svg.i { width:18px; height:18px; fill:none; stroke:currentColor; stroke-width:1.8; stroke-linecap:round; stroke-linejoin:round; flex-shrink:0; }
        .side small { margin-top:auto; color:#8791a7; padding:0 10px; }

        /* content */
        .content { flex:1; min-width:0; padding:28px 36px 48px; max-width:1240px; }
        .head { display:flex; align-items:center; justify-content:space-between; gap:16px; margin-bottom:22px; flex-wrap:wrap; }
        h1 { margin:0; font-size:24px; font-weight:900; letter-spacing:-.02em; } h2, h3 { font-weight:800; } .sub { font-weight:500; }
        .sub { color:var(--muted); margin-top:2px; }
        .card { background:var(--surface); border:1px solid var(--line); border-radius:var(--radius); box-shadow:var(--shadow); padding:18px; }
        .grid { display:grid; gap:16px; }
        .stats { grid-template-columns:repeat(auto-fit,minmax(190px,1fr)); margin-bottom:18px; }
        .stat { display:flex; align-items:center; gap:14px; }
        .stat .ic { width:42px; height:42px; border-radius:12px; background:var(--accent-soft); color:var(--accent-ink); display:grid; place-items:center; }
        .stat b { font-size:22px; font-weight:800; letter-spacing:-.02em; display:block; line-height:1.1; }
        .stat span { color:var(--muted); font-size:13px; }

        /* controls */
        button, .btn { display:inline-flex; align-items:center; gap:7px; padding:8px 14px; border:1px solid var(--line); border-radius:10px; background:var(--surface); color:var(--text); font:inherit; font-weight:500; cursor:pointer; transition:.12s; }
        button:hover, .btn:hover { background:var(--surface-2); border-color:color-mix(in srgb, var(--line) 60%, var(--muted)); }
        button.primary { background:var(--accent); border-color:var(--accent-ink); color:var(--accent-text); }
        button.primary:hover { background:var(--brand-bright); border-color:var(--brand-bright); }
        button.danger { color:var(--bad); }
        button.sm { padding:6px 10px; font-size:13px; }
        button:disabled { opacity:.55; cursor:progress; }
        input, select { font:inherit; padding:9px 12px; border:1px solid var(--line); border-radius:10px; background:var(--surface-2); color:var(--text); width:100%; }
        input:focus, select:focus { outline:2px solid var(--accent); outline-offset:-1px; background:var(--surface); }
        label.f { display:flex; flex-direction:column; gap:5px; font-size:12px; font-weight:600; color:var(--muted); }
        .row { display:flex; gap:12px; flex-wrap:wrap; align-items:end; }
        .row > label.f { flex:1; min-width:130px; }
        .muted { color:var(--muted); }

        /* pills */
        .pill { display:inline-flex; align-items:center; gap:6px; padding:3px 10px; border-radius:999px; font-size:12px; font-weight:600; background:var(--surface-2); color:var(--muted); }
        .pill.good { background:var(--good-soft); color:var(--good); } .pill.bad { background:var(--bad-soft); color:var(--bad); }
        .pill.warn { background:var(--warn-soft); color:var(--warn); } .pill.info { background:rgba(85,131,237,.15); color:var(--info); } .pill.acc { background:var(--accent-soft); color:var(--accent-ink); }
        .dot { width:7px; height:7px; border-radius:50%; background:currentColor; }

        /* switch */
        .switch { position:relative; width:38px; height:22px; flex-shrink:0; }
        .switch input { position:absolute; inset:0; opacity:0; cursor:pointer; z-index:1; width:100%; height:100%; padding:0; }
        .switch span { position:absolute; inset:0; background:var(--line); border-radius:999px; transition:.15s; }
        .switch span::after { content:""; position:absolute; top:3px; left:3px; width:16px; height:16px; border-radius:50%; background:#fff; transition:.15s; box-shadow:0 1px 2px rgba(0,0,0,.25); }
        .switch input:checked + span { background:var(--accent); } .switch input:checked + span::after { transform:translateX(16px); }

        /* devices */
        .devices { grid-template-columns:repeat(auto-fill,minmax(380px,1fr)); }
        .dev h3 { margin:0; font-size:16px; } .dev .addr { color:var(--muted); font-size:13px; font-variant-numeric:tabular-nums; }
        .dev-top { display:flex; justify-content:space-between; gap:10px; align-items:flex-start; }
        .kv { display:grid; grid-template-columns:repeat(3,1fr); gap:10px; margin:16px 0; padding:12px; background:var(--surface-2); border-radius:10px; }
        .kv b { display:block; font-size:18px; } .kv span { font-size:12px; color:var(--muted); }
        .dev-actions { display:flex; gap:8px; flex-wrap:wrap; }
        .dev-actions form { margin:0; }
        .auto { display:flex; align-items:center; justify-content:space-between; gap:10px; padding-top:14px; margin-top:14px; border-top:1px solid var(--line); font-size:13px; }
        .auto form { margin:0; display:flex; align-items:center; gap:10px; }
        details.more { position:relative; } details.more summary { list-style:none; } details.more summary::-webkit-details-marker { display:none; }
        details.more .menu { position:absolute; right:0; top:calc(100% + 6px); z-index:5; min-width:200px; background:var(--surface); border:1px solid var(--line); border-radius:12px; box-shadow:0 10px 30px rgba(0,0,0,.18); padding:6px; display:flex; flex-direction:column; }
        details.more .menu form { margin:0; } details.more .menu button { width:100%; border:0; justify-content:flex-start; background:transparent; }
        details.more .menu button:hover { background:var(--surface-2); }
        .empty { text-align:center; padding:48px 20px; color:var(--muted); }
        .empty .ic { width:56px; height:56px; border-radius:16px; background:var(--accent-soft); color:var(--accent-ink); display:grid; place-items:center; margin:0 auto 14px; }

        /* table */
        .tbl { overflow:auto; }
        table { width:100%; border-collapse:collapse; }
        th { text-align:left; font-size:12px; font-weight:700; text-transform:uppercase; letter-spacing:.04em; color:var(--muted); padding:10px 14px; border-bottom:1px solid var(--line); position:sticky; top:0; background:var(--surface); }
        td { padding:11px 14px; border-bottom:1px solid var(--line); }
        tbody tr:hover { background:var(--surface-2); } tbody tr:last-child td { border-bottom:0; }
        .who { display:flex; align-items:center; gap:10px; }
        .av { width:30px; height:30px; border-radius:50%; background:var(--accent-soft); color:var(--accent-ink); display:grid; place-items:center; font-weight:700; font-size:12px; flex-shrink:0; }
        .num { font-variant-numeric:tabular-nums; }
        .pager { display:flex; gap:10px; align-items:center; justify-content:space-between; padding-top:14px; }

        /* toast + dialog */
        .toasts { position:fixed; right:22px; bottom:22px; display:flex; flex-direction:column; gap:10px; z-index:50; max-width:420px; }
        .toast { padding:12px 16px; border-radius:12px; background:var(--surface); border:1px solid var(--line); box-shadow:0 10px 30px rgba(0,0,0,.18); display:flex; gap:10px; align-items:flex-start; animation:in .2s ease-out; }
        .toast.ok { border-left:4px solid var(--good); } .toast.err { border-left:4px solid var(--bad); }
        @keyframes in { from { transform:translateY(10px); opacity:0; } }
        .banner { padding:12px 16px; border-radius:12px; background:var(--warn-soft); color:var(--warn); margin-bottom:18px; font-weight:500; display:flex; align-items:center; justify-content:space-between; gap:14px; }
        .banner button { flex-shrink:0; white-space:nowrap; }
        dialog { border:1px solid var(--line); border-radius:var(--radius); background:var(--surface); color:var(--text); padding:0; width:min(480px,92vw); box-shadow:0 24px 60px rgba(0,0,0,.35); }
        dialog::backdrop { background:rgba(10,14,25,.55); backdrop-filter:blur(2px); }
        dialog form { padding:22px; display:grid; gap:14px; } dialog h2 { margin:0 0 2px; font-size:18px; }
        dialog .two { display:grid; grid-template-columns:1fr 1fr; gap:12px; } dialog .foot { display:flex; justify-content:flex-end; gap:10px; margin-top:6px; }
        @media (max-width:860px) { body { flex-direction:column; } .side { width:100%; height:auto; position:static; flex-direction:row; align-items:center; } .brand { padding:0 10px; } .side small { display:none; } .content { padding:20px 16px; } .devices { grid-template-columns:1fr; } }
    </style>
</head>
<body>

<aside class="side">
    <div class="brand"><i><x-icon name="clock"/></i> Hier Sync Local</div>
    <nav class="nav">
        <a href="{{ route('devices.index') }}" class="{{ request()->routeIs('devices.*') ? 'on' : '' }}"><x-icon name="cpu"/> Devices</a>
        <a href="{{ route('users.index') }}" class="{{ request()->routeIs('users.*') ? 'on' : '' }}"><x-icon name="users"/> Users</a>
        <a href="{{ route('attendance.index') }}" class="{{ request()->routeIs('attendance.*') ? 'on' : '' }}"><x-icon name="list"/> Attendance</a>
        <a href="{{ route('sync.index') }}" class="{{ request()->routeIs('sync.*') ? 'on' : '' }}"><x-icon name="sync"/> Payroll Sync</a>
    </nav>
    <small>ZKTeco SDK 6.3.1.37</small>
</aside>
<main class="content">
    @if (isset($problem) && $problem)
        <div class="banner">
            <span><strong>SDK not ready:</strong> {{ $problem }}</span>
            @if (! \App\Services\ZkemClient::sdkRegistered())
                <form method="post" action="{{ route('sdk.register') }}" onsubmit="const b=this.querySelector('button');b.disabled=true;b.textContent='Waiting for permission…';">
                    @csrf
                    <button class="primary sm">Register SDK</button>
                </form>
            @endif
        </div>
    @endif
    @yield('content')
</main>
<div class="toasts" id="toasts">
    @if (session('ok')) <div class="toast ok">{{ session('ok') }}</div> @endif
    @if (session('error')) <div class="toast err">{{ session('error') }}</div> @endif
    @if ($errors->any()) <div class="toast err">{{ $errors->first() }}</div> @endif
</div>
<script>
    // Device actions can take a few seconds; show progress so the click doesn't look dead.
    document.addEventListener('submit', (e) => {
        if (e.defaultPrevented) return;
        const f = e.target;
        if (!f.action.includes('/devices/') || f.method.toLowerCase() !== 'post') return;
        setTimeout(() => {
            document.querySelectorAll('.dev-actions button, .auto button').forEach((b) => (b.disabled = true));
            const b = f.querySelector('button'); if (b) b.lastChild.textContent = ' Working…';
        }, 0);
    });
    document.querySelectorAll('.toast').forEach((t) => setTimeout(() => t.remove(), t.classList.contains('err') ? 12000 : 5000));
    document.addEventListener('click', (e) => document.querySelectorAll('details.more[open]').forEach((d) => { if (!d.contains(e.target)) d.open = false; }));
</script>
</body>
</html>
