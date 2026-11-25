# Architecture

```
┌──────────────── Electron shell (electron/main.js) ─────────────────┐
│  window ──► http://127.0.0.1:<random port>  ◄── php -S (Laravel)   │
│  preload.js: window.hierSync (password, test, push range)          │
│  timers (every 60 s):  artisan devices:auto-sync                   │
│                        artisan logs:push --scheduled               │
│  safeStorage: push-credentials.bin  ──► HIER_PUSH_PASSWORD (env)   │
└────────────────────────────────────────────────────────────────────┘
        Laravel ──► SQLite (WAL)         Laravel ──► bridge/zkbridge.exe ──► zkemkeeper.dll ──► ZKTeco device
        Laravel (artisan) ──► HTTPS ──► Hier Payroll /api/sync-local/v1/*
```

## Electron shell
- Finds PHP (`php\php.exe`, `%PHP_BINARY%`, then Laragon), runs `desktop/php.ini` (enables opcache, `com_dotnet`,
  `pdo_sqlite`, `curl`, …), migrates the database, and serves `public/` on a free loopback port.
- **Data folder:** `storage/desktop` in development; Electron `userData` when packaged. It holds `database.sqlite`,
  `app.key`, `push-credentials.bin` and Laravel's `storage/`.
- **Background jobs** run as separate `artisan` processes so a slow device never blocks the UI server:
  - `devices:auto-sync` decides which devices are due (setting `auto_sync_minutes`, min 5).
  - `logs:push --scheduled` pushes when the interval (`push_interval`, 1–60 min) has passed.
  - Both use cache locks so runs never overlap.
- **IPC** (`preload.js` → `main.js`): `hasPassword`, `setPassword`, `clearPassword`, `testConnection`, `pushNow`,
  `pushRange`. Each handler verifies the sender is the app's own page. The password never returns to the page.

## Laravel app
| Area | Files |
|---|---|
| Pages | Devices, Users, Attendance, Payroll Sync, Sync logs, Settings (`routes/web.php`, `resources/views/*`) |
| Controllers | `DeviceController`, `UserController`, `AttendanceController`, `SyncSettingsController`, `SyncLogController` |
| Services | `ZkemClient` (device bridge), `LogSyncer` (pull logs), `LogPusher` (push to payroll) |
| Commands | `devices:auto-sync`, `logs:push` (`--force --test --batch --scheduled --from --to --json`) |
| Models | `Device`, `DeviceUser`, `AttendanceLog`, `Setting`, `SyncLog` |

## Device bridge
PHP's COM layer can't pass the SDK's by-reference integer outputs, so `ZkemClient` runs `bridge/zkbridge.exe`
(`info`, `users`, `logs`, `settime`, `setuser`) and reads its JSON. Build it with `bridge/build.bat`. The SDK must be
registered once per PC (`sdk/register-sdk.bat`, as administrator). Devices accept one connection at a time, so a
manual action can fail while auto-sync is talking to the same device. Names come back truncated to about 8
characters from the SDK.

## Data model (SQLite)
| Table | Purpose |
|---|---|
| `devices` | Terminals: name, ip, port, machine number, **serial number**, auto-sync flags, last sync result |
| `device_users` | Users pulled from each device (`enroll_number` is unique **per device**), card number, enabled |
| `attendance_logs` | Punches: device, enroll number, `punched_at` (device local time), verify/in-out/work-code, `sent_at`, `push_error` |
| `settings` | Key/value configuration (`push_*`, `auto_sync_*`, `installation_id`, …) |
| `sync_logs` | Activity history (type, level, device, message, records, duration). Pruned after 30 days |

Punches are inserted with `insertOrIgnore` on `(device_id, enroll_number, punched_at)`, so re-pulling a device never
duplicates records.

## Push to Hier Payroll
See [PUSH-API.md](PUSH-API.md). In short: per-device requests to `POST /api/sync-local/v1/logs`; auto runs send
unsent logs plus today's logs; date-range runs resend everything in range; failures back off 2, 4, 8… up to 30 min.

## Performance notes
The PHP built-in server handles one request at a time. Opcache (in `desktop/php.ini`) and SQLite WAL
(`config/database.php`) keep pages fast while background jobs write; the Devices page probes each device with a 1 s
timeout.
