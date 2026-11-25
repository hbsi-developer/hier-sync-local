# Architecture rules

Hier Sync Local is a Laravel 13 app (PHP 8.4+) wrapped in an Electron shell. It pulls attendance and users from
ZKTeco devices and pushes attendance to Hier Payroll. Full detail: `docs/ARCHITECTURE.md`.

- **Electron owns the secrets and the timers.** `electron/main.js` starts `php -S` on a free loopback port, runs
  migrations, and spawns `artisan` for background work (`devices:auto-sync`, `logs:push --scheduled`, each ticked
  every 60 s). Laravel never stores the payroll password: Electron keeps it encrypted (`safeStorage`) and hands it
  to the artisan process only through the `HIER_PUSH_PASSWORD` environment variable.
- **The page can only call `window.hierSync`** (`electron/preload.js`). Every IPC handler in `main.js` must check
  `trusted(event)` first. Add a new IPC function in both files, validate its arguments in `main.js`, and never
  return the password to the page.
- **Device access goes through `ZkemClient` → `bridge/zkbridge.exe`** (a .NET wrapper around `zkemkeeper.dll`).
  PHP COM cannot marshal the SDK's by-ref out-parameters. Never call the DLL from PHP directly.
- **The local server is single-threaded.** Anything slow (device calls, large loops) must not run in a web request
  more than necessary. Long work belongs in an `artisan` command spawned by Electron. Keep the device probe
  timeout short.
- **One SQLite database** (`storage/desktop/database.sqlite` in dev, Electron `userData` when packaged), WAL mode.
  Migrations run at app start, so a new migration reaches users on the next launch.
- **Business logic lives in services** (`app/Services`): `LogPusher` (push to payroll), `LogSyncer` (pull from
  devices), `ZkemClient` (SDK bridge). Controllers stay thin; commands only parse options and call services.
- **Settings are key/value rows** (`Setting::get/put`). Prefix push settings with `push_`, device sync with
  `auto_sync_`.
- **Every operation that can fail or be skipped writes a `SyncLog`** (`SyncLog::record(...)`). Logging must never
  throw or break the operation (the model already swallows its own errors).
