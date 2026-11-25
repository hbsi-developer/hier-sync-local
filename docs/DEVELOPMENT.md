# Development

## Setup (Windows)
1. Install PHP 8.4+ (Laragon has 8.4.26 at `C:\laragon\bin\php\php-8.4.26-Win32-vs17-x64`). The `php` on PATH may be
   an older version; use the full path or put 8.4 first on PATH.
2. `composer install` and `npm install --ignore-scripts=false` (downloads the Electron binary).
3. Register the ZKTeco SDK once, as administrator: `sdk\register-sdk.bat` (or the **Register SDK** button in the
   app). The bridge is prebuilt at `bridge\zkbridge.exe`; rebuild with `bridge\build.bat` after editing
   `bridge\ZkBridge.cs`.

## Run
```
npm start
```
Data lives in `storage\desktop` (SQLite database, `app.key`, saved payroll password, logs). Delete that folder to
start from scratch.

To preview pages without Electron, serve with the same settings the shell uses (set `DB_DATABASE`, `APP_KEY`,
`SESSION_DRIVER=file`, `CACHE_STORE=file` and run `php -S 127.0.0.1:8000 -t public`). Pages that need
`window.hierSync` (password, test connection, push range) only work inside the Electron window.

## Validate before committing
```
powershell -NoProfile -ExecutionPolicy Bypass -File scripts\validate.ps1
```
See [TESTING.md](TESTING.md).

## Branching and commits
Work on a feature branch (`feature/...`), keep commits focused, and add notes under `## [Unreleased]` in
`CHANGELOG.md` for anything user-visible. Never commit `.env`, `app.key`, `push-credentials.bin`, or any payroll
credentials.

## Versioning and releases
Semantic Versioning, set only in `package.json` (shown in the sidebar footer and sent in the `User-Agent`).

```
npm run release:patch   # fixes        1.0.0 -> 1.0.1
npm run release:minor   # features     1.0.0 -> 1.1.0
npm run release:major   # breaking     1.0.0 -> 2.0.0
```
Each release script bumps `package.json`, moves the Unreleased notes under the new version in `CHANGELOG.md`,
commits, and tags `vX.Y.Z`. It requires a clean git tree. **After every version bump, build the installer with
`npm run dist`** (the `/release` command does this for you). Push the commit and tag yourself.

## Build the installer
1. Put a PHP 8.4 x64 build with the required extensions in `php\` (see `README-DESKTOP.md`), or let
   `scripts\stage.ps1` copy it from Laragon.
2. `npm run dist` → `dist\Hier-Sync-Local-Setup-<version>.exe`.
3. Each target PC still needs the SDK registered once (the installer script `build\installer.nsh` handles it).
   Updating the app keeps the user's database and saved password (they live in the user-data folder).
   New migrations run at the next launch.

## Working with Claude Code
- `CLAUDE.md` links the project rules in `.claude/rules/` (architecture, payroll push contract, security, code
  style, testing).
- Slash commands in `.claude/commands/`: `/test`, `/validate`, `/release`.
- `.claude/settings.json` allows the test and validation commands and denies reading `.env`, `app.key` and
  the saved password file.
