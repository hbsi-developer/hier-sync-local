# Hier Sync Local

Windows desktop app that reads attendance and users from **ZKTeco** terminals and pushes the attendance to
**Hier Payroll**. Version **1.1.0**. See [CHANGELOG.md](CHANGELOG.md) for what changed.

## Table of contents
- [Overview](#overview)
- [Features](#features)
- [Tech stack](#tech-stack)
- [Prerequisites](#prerequisites)
- [Installation](#installation)
- [Configuration](#configuration)
- [Usage](#usage)
- [Scripts](#scripts)
- [Testing](#testing)
- [Deployment](#deployment)
- [Contributing](#contributing)
- [License](#license)
- [Contact and support](#contact-and-support)

## Overview
Offices keep their punch data on ZKTeco attendance terminals. Hier Sync Local runs on a PC on the same network as the
terminals. It pulls punches and users from each device over the ZKTeco Standalone SDK, stores them in a local SQLite
database, and sends the attendance to Hier Payroll on a schedule.

```
ZKTeco device(s) ──► SDK bridge ──► Laravel + SQLite (inside Electron) ──► HTTPS ──► Hier Payroll
```

The app is a Laravel 13 web app served on a random loopback port inside an Electron window. Nothing is exposed to
the network. More detail: [docs/ARCHITECTURE.md](docs/ARCHITECTURE.md).

## Features
- **Devices:** add terminals by IP, port and machine number; live connected check; read the serial number; pull logs
  and users; set the device clock.
- **Attendance:** browse and filter punches by device, enroll number and date; export to CSV. Re-pulling never
  creates duplicates.
- **Users:** users pulled from each device, with search; edit a card number or name and write it back to the device.
- **Auto sync:** pulls new logs from the devices in the background on an interval (minimum 5 minutes).
- **Push to Hier Payroll:** sends attendance to Hier Payroll per device. Auto push sends today's logs plus any unsent
  older ones on an interval (1-60 minutes); **Push by date range** resends everything between two dates. Failed
  pushes retry with backoff.
- **Settings:** website URL, username, password (encrypted on this PC only), push interval, test connection.
- **Sync logs:** history of pushes, device syncs, user pulls, tests, settings changes and skipped runs, with
  filters and CSV export (kept 30 days).
- **Installer:** one Windows installer that also registers the ZKTeco SDK.

## Tech stack
| Layer | Technology |
|---|---|
| Desktop shell | Electron 33, electron-builder (NSIS installer) |
| Backend | Laravel 13, PHP 8.4+ (built-in server, opcache) |
| Database | SQLite (WAL mode) |
| Device access | ZKTeco Standalone SDK (`zkemkeeper.dll`) through a small .NET bridge (`bridge/zkbridge.exe`) |
| Tests | PHPUnit 12 |
| UI | Blade templates, plain CSS, Montserrat font |

## Prerequisites
- Windows 10 or 11 (x64). The SDK is Windows only.
- **PHP 8.4.1 or newer**, 64-bit, with the extensions `com_dotnet`, `pdo_sqlite`, `sqlite3`, `mbstring`, `openssl`,
  `fileinfo`, `curl`, `opcache`. Laragon ships a suitable build (`C:\laragon\bin\php\php-8.4.26-Win32-vs17-x64`).
  Note that the `php` on your PATH may be older; use the full path to the 8.4 binary.
- [Composer](https://getcomposer.org) and [Node.js](https://nodejs.org) 20+ with npm.
- Administrator rights once, to register the ZKTeco SDK.
- A Hier Payroll account with the website URL and an API username and password, and the devices registered there by
  serial number.

## Installation
**For users:** run `Hier-Sync-Local-Setup-<version>.exe`. It registers the ZKTeco SDK during install.

**For development:**
```powershell
composer install
npm install --ignore-scripts=false     # downloads the Electron binary
sdk\register-sdk.bat                   # once, as administrator (or use the Register SDK button in the app)
npm start
```
In development, data (SQLite database, app key, saved password, logs) is kept in `storage\desktop`. The shell finds
PHP in `php\php.exe`, then `%PHP_BINARY%`, then the newest Laragon PHP. If you change `bridge\ZkBridge.cs`, rebuild
the bridge with `bridge\build.bat`.

## Configuration
There is no `.env` to edit for the desktop app: the shell supplies the environment and generates the app key on
first run. Everything is set on the **Settings** page.

| Setting | Where | Notes |
|---|---|---|
| Website URL, username | Settings | HTTPS only (plain HTTP only when `APP_ENV=local`) |
| Password | Settings | Encrypted with Windows protected storage; never in the database or logs |
| Auto push interval | Settings | 1-60 minutes, default 2 |
| Device auto-sync | Settings | On/off and every 5-1440 minutes (default 30) |
| Devices | Devices page | Name, IP, port (usually 4370), machine number |

Advanced (rarely needed): `HIER_PUSH_BATCH` (logs per request, default 500) and the paths in `config/sync.php`.
`HIER_PUSH_PASSWORD` is set by the shell for background commands only; never set it by hand or commit it.

## Usage
1. **Devices:** press **Add device**, enter the terminal's IP and port, then press **Test** (reads the serial
   number) and **Pull logs** / **Pull users**.
2. **Settings:** enter the Hier Payroll URL, username and password, set the interval, turn **Push to Hier
   Payroll** on, press **Save settings**, then **Test connection**.
3. **Payroll Sync:** see the last push, unsent count and result. To resend a period, pick From and To and press
   **Push range**.
4. **Sync logs:** check what ran and why something failed (for example `404 Unknown device` means the device's serial
   isn't registered in Hier Payroll yet).

The push contract and behaviour are documented in [docs/PUSH-API.md](docs/PUSH-API.md).

## Scripts
| Command | What it does |
|---|---|
| `npm start` | Run the desktop app |
| `npm run stage` | Copy PHP and the Laravel app into `build-stage\` for packaging |
| `npm run dist` | Stage and build the Windows installer in `dist\` |
| `npm run release:patch \| minor \| major` | Bump the version, date the changelog, commit and tag (run `npm run dist` afterwards) |
| `php artisan logs:push` | Push logs now (`--test`, `--force`, `--from=`, `--to=`, `--json`) |
| `php artisan devices:auto-sync` | Pull logs from devices that are due (`--force` for all) |
| `scripts\validate.ps1` | Full validation: syntax, version, secrets scan, tests, style |

Use the PHP 8.4 binary for the `php artisan` commands.

## Testing
```powershell
powershell -NoProfile -ExecutionPolicy Bypass -File scripts\validate.ps1      # everything
C:\laragon\bin\php\php-8.4.26-Win32-vs17-x64\php.exe artisan test             # tests only
```
Tests use an in-memory database and fake HTTP; they need no devices or credentials. Device access and the Electron
features are checked by hand. Details: [docs/TESTING.md](docs/TESTING.md).

## Deployment
1. Make sure the tree is clean and `scripts\validate.ps1` passes.
2. `npm run release:minor` (or `patch` / `major`).
3. `npm run dist` builds `dist\Hier-Sync-Local-Setup-<version>.exe` (unsigned unless a code-signing certificate is
   configured).
4. Attach the installer to a GitHub Release for the new tag. It is too large for a normal git push and `dist\` is
   git-ignored.
5. On each PC: run the installer (it registers the SDK). Updates keep the local database and saved password, and new
   migrations run at the next launch.

More: [docs/DEVELOPMENT.md](docs/DEVELOPMENT.md).

## Contributing
- Work on a `feature/...` branch and open a pull request into `main`.
- Run `scripts\validate.ps1` before pushing; add or update tests for behaviour changes.
- Add notes under **Unreleased** in [CHANGELOG.md](CHANGELOG.md) for anything user-visible.
- Never commit `.env`, `app.key`, saved passwords, or any Hier Payroll credentials.
- Project rules for contributors and AI assistants are in `.claude/rules/` (linked from `CLAUDE.md`).

## License
Proprietary software of Hier Business Solutions Inc. All rights reserved. It is not licensed for redistribution or
use outside the company without written permission. The ZKTeco SDK files in `sdk\` belong to ZKTeco and are subject
to their own license.

## Contact and support
Hier Business Solutions Inc. For problems or questions, open an issue in the
[GitHub repository](https://github.com/hbsi-developer/hier-sync-local/issues) or contact your Hier Payroll support
representative. When reporting a push problem, include the matching entry from **Sync logs** (no passwords).
