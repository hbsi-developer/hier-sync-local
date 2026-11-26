# Changelog

All notable changes to Hier Sync Local, from the first commit to today. Versions follow
[Semantic Versioning](https://semver.org): `major.minor.patch`. The version lives in `package.json` and is shown at
the bottom of the sidebar.

To release: add notes under **Unreleased**, commit, then run one of

```
npm run release:patch   # bug fixes          1.0.0 -> 1.0.1
npm run release:minor   # new features       1.0.0 -> 1.1.0
npm run release:major   # breaking changes   1.0.0 -> 2.0.0
```

That bumps `package.json`, moves the Unreleased notes under the new version, commits and tags `vX.Y.Z`.
Then `npm run dist` builds the installer with the same version in its name.

## [Unreleased]

## [1.1.1] - 2026-11-26

### Fixed
- The installer and app executable now carry the Hier Sync icon. electron-builder skipped icon editing because
  `signAndEditExecutable` is off, so an `afterPack` hook (`build/after-pack.js`) applies `build/icon.ico` with the new
  `rcedit` dev dependency.

## [1.1.0] - 2026-11-25

Everything since 1.0.0: Settings and Sync logs pages, per-device push to the new Hier Payroll API, date range push,
versioning and project tooling.

### Added
- **Settings page** (sidebar): Hier Payroll connection (switch, website URL, username, password, auto-push interval,
  Test connection) and device auto-sync, as stacked full-width cards. One **Save settings** button saves the password
  (kept by Electron) and the rest together.
- **Sync logs page** (sidebar): history of pushes per device (success or error with reason, record count, duration),
  device syncs, user pulls, connection tests, settings changes and skipped runs. Filters (device, type, level, dates),
  pagination, CSV export, a rejected-records list with **Retry rejected**, 24-hour stats, and automatic deletion of
  entries older than 30 days. Devices page shows each device's last push result.
- **Push by date range** on Payroll Sync: resends every log between From and To, including ones already sent.
- **Configurable auto-push interval** (1–60 minutes, default 2). The shell ticks every minute and the command
  enforces the interval.
- **Auto push now sends today's logs** (resent each interval) **plus any unsent older logs**.
- New Hier Payroll Sync logo, favicon, apple-touch icon, window/installer icon and tray icon.
- **Versioning:** version shown in the sidebar and sent as `User-Agent: HierSyncLocal/<version>`; `npm run
  release:patch|minor|major` scripts; this changelog.
- **Project tooling:** `.claude/` folder (rules, `/test`, `/validate`, `/release` commands, permissions),
  `docs/` (architecture, push API, testing, development), `scripts/validate.ps1` (PHP/JS syntax, package.json,
  secrets scan, tests, style), and a project guide in `CLAUDE.md` linking them.
- Responsive sidebar and layout for narrow windows; global `[hidden]` rule.

### Changed
- **Push contract:** logs go to `POST /api/sync-local/v1/logs`, one request per device, as
  `{ deviceSerial, logs: [{ enrollNumber, punchedAt, inOutMode, verifyMode, workCode }] }`. A batch counts as accepted on
  a 2xx response unless `success` is `false`. A failing device no longer blocks the others and its error is named in the
  result.
- **Test connection** now calls `GET /api/sync-local/v1/devices` (read-only) instead of posting an empty batch.
- Payroll Sync page simplified to stats, alerts and Push by date range; connection settings moved to Settings. The
  **Push now** button was removed (auto push and range push remain).
- Device auto-sync interval minimum lowered from 30 to **5 minutes** (and its setting moved to Settings).
- Faster local server: opcache enabled in `desktop/php.ini`, SQLite WAL mode, device status probe timeout 2 s → 1 s.
- Manual **Pull logs** now records its result on the device and in Sync logs.
- The device serial number is fetched automatically during the first log sync, so pushes no longer wait for someone to
  press **Test**.
- `ExampleTest` now checks the real behaviour (home redirects to Devices); `phpunit.xml` sets a test `APP_KEY`.

### Fixed
- Attendance page and CSV export showed the wrong name when two devices used the same enroll number (names are now
  keyed by device and enroll number).
- Settings page showed hidden elements (password field) outside the desktop app.

## [1.0.0] - 2026-11-18

First release line: the desktop app (Laravel 13 + Electron) that reads ZKTeco terminals and pushes attendance to
Hier Payroll.

### 2026-11-18: Push to Hier Payroll
- Sender that posts unsent attendance logs over HTTPS with HTTP Basic Auth, every 2 minutes
  (`logs:push` command with `--test`, `--force`, `--batch`, `--json`; lock prevents overlapping runs).
- Migration adding `sent_at` (indexed) and `push_error` to `attendance_logs`.
- Oldest first, 500 logs per request; rows marked sent only when the server confirms them; server-rejected rows are
  parked so they don't block the queue, with **Retry rejected**.
- Failures keep logs queued in order and retry with backoff (2, 4, 8… up to 30 minutes).
- Stable per-install `installation_id`; each log carries its device serial.
- HTTPS only (plain HTTP only when `APP_ENV=local`), credentials-in-URL refused, redirects not followed.
- **Payroll Sync** page: URL, username, on/off, last push, result, unsent count, Test connection, Push now.
- Electron: password encrypted with `safeStorage` and handed to `artisan` only through `HIER_PUSH_PASSWORD`;
  `contextBridge` preload with origin-checked IPC, `nodeIntegration` off, sandbox on, navigation locked to the local
  origin; push timer with no overlap.
- Feature tests for the push flow and settings page.

### 2026-11-17: Rename and SDK setup
- Renamed "Hierpayroll AST" to **Hier Sync Local** (UI, package name, installer name, app ID); user data saved under
  the old name is carried over on first launch.
- **Register SDK** button on the "SDK not ready" banner (runs `sdk\register-sdk.ps1` elevated through a UAC prompt).
- PHP lookup picks the newest x64 PHP 8.x under Laragon instead of a hard-coded list (which fell back to PHP 8.1 and
  failed the migration).

### 2026-11-16: Users page and card numbers
- **Users** page listing users pulled from devices, with search and device filter.
- Edit a user's card number (and name) and write it back to the device.
- `card_number` column on `device_users`; the `zkbridge.exe` .NET bridge gained user editing.

### 2026-10-23 to 2026-10-26: Initial app
- Laravel 13 app wrapped in Electron (`electron/main.js`): starts a local PHP server on a free port, migrates the
  database, stores data in the user-data folder (or `storage\desktop` in development).
- **Devices** page: add/edit/remove ZKTeco terminals by IP, port and machine number; live "connected" check;
  **Test** (reads serial and time), **Pull logs**, **Pull users**, **Set device time**.
- Device access through `bridge\zkbridge.exe`, a .NET wrapper around the ZKTeco Standalone SDK (`zkemkeeper.dll`),
  because PHP COM can't pass the SDK's by-reference outputs. SDK files and registration scripts included.
- **Attendance** page with filters (device, enroll number, dates) and CSV export; duplicate punches are ignored.
- **Auto sync**: a background `devices:auto-sync` command (every minute tick, per-device interval) pulls new logs
  while the app is open, recording the last result per device.
- SQLite database with `devices`, `attendance_logs`, `device_users` and `settings` tables.
- NSIS installer (`npm run dist`) that registers the ZKTeco SDK during install; Montserrat font and the HBSI brand
  colours.
