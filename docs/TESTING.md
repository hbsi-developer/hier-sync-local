# Testing and validation

## Requirements
PHP **8.4.1 or newer**. On this machine the `php` on PATH is 7.3, so use the Laragon 8.4 binary:
`C:\laragon\bin\php\php-8.4.26-Win32-vs17-x64\php.exe`.

## Run everything (recommended)
```
powershell -NoProfile -ExecutionPolicy Bypass -File scripts\validate.ps1
```
Options: `-SkipTests`, `-Php <path>` (or set `PHP_BIN`). In Claude Code use `/validate`.

| Step | Checks |
|---|---|
| 1. PHP syntax | `php -l` on `app`, `config`, `routes`, `database`, `resources`, `tests` |
| 2. JavaScript syntax | `node --check` on `electron/` and `scripts/` |
| 3. package.json | Valid JSON and a `major.minor.patch` version |
| 4. Secrets scan | Tracked files hold no private keys, `APP_KEY`, `sl_…` credentials or push password; `.env` is not tracked |
| 5. Tests | `php artisan test` |
| 6. Code style | Pint, **advisory only** (the codebase has existing style differences) |

It ends with `VALIDATION PASSED` or `VALIDATION FAILED` and exits non-zero on failure.

## Run tests only
```
C:\laragon\bin\php\php-8.4.26-Win32-vs17-x64\php.exe artisan test
C:\laragon\bin\php\php-8.4.26-Win32-vs17-x64\php.exe artisan test --filter=LogPushTest
```
(In Claude Code: `/test` or `/test LogPushTest`.)

## What the suite covers
`tests/Feature/LogPushTest.php` (HTTP is faked; in-memory SQLite; fake password):
- push payload shape (`deviceSerial`, camelCase fields), ordering, batching, per-device sending
- one failing device does not block the others; failures back off; offline is a clean failure
- auto push (unsent + today) vs date-range push (resends sent logs, across batches); bad dates rejected
- interval gating for scheduled runs and the interval setting validation
- HTTPS-only URLs, credentials-in-URL refusal, redirects not followed, password absent from command output
- Test connection uses `GET /devices` and changes no state
- Sync logs: per-device push entries, connection tests, settings changes, skipped runs logged once, page filters,
  CSV export, 30-day pruning
- Settings and Payroll Sync pages render; version is shown and sent in the `User-Agent`
`tests/Feature/ExampleTest.php`: the home page redirects to Devices and Devices renders.

## What is not automated
- **Device access** (`ZkemClient`, `bridge/zkbridge.exe`, the SDK): check manually with **Test**, **Pull logs** and
  **Pull users** on a real terminal.
- **Electron features** (password storage, IPC, timers, installer): run `npm start` and use the Settings and Payroll
  Sync pages. A plain browser cannot exercise `window.hierSync`.
- **The real Hier Payroll server**: use **Test connection** and a **Push range** over a small date range, then
  check **Sync logs**.

## Writing tests
- Use `RefreshDatabase`, `Http::fake([...])`, and `config(['sync.password' => 's3cret'])` as in `LogPushTest`.
- Create data with the small helpers (`device()`, `log()`), and use `now()`-relative dates for anything that
  involves "today".
- No real credentials, network calls, or devices in tests.
