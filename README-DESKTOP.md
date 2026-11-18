# Hier Sync Local (Laravel + Electron desktop app)

Pulls users and attendance from ZKTeco devices using the official Standalone SDK (`zkemkeeper.dll`, COM) and stores them in a local SQLite database.

## One-time setup (Windows)
1. Right-click `sdk\register-sdk.bat` > **Run as administrator** (registers the 64-bit SDK; the PHP used must be 64-bit).
2. `npm install --ignore-scripts=false` (the Electron binary is downloaded by a postinstall script).
3. `composer install` (already done if you cloned from this folder).

## Run
```
npm start
```
The shell finds PHP in `php\php.exe`, then `%PHP_BINARY%`, then Laragon's PHP. It runs `desktop\php.ini` (enables `com_dotnet`, `pdo_sqlite`), migrates the database, and serves Laravel on a random localhost port inside the window. Data (SQLite DB, logs, app key) is stored in the Electron user-data folder when packaged, or `storage\desktop` in dev.

## Build installer
1. Copy a PHP 8.4 x64 NTS/ZTS build (with `ext\php_com_dotnet.dll`, `php_pdo_sqlite.dll`, `php_sqlite3.dll`, `php_mbstring.dll`, `php_openssl.dll`, `php_fileinfo.dll`, `php_curl.dll`) into `php\`.
2. `composer install --no-dev --optimize-autoloader`
3. `npm run dist` -> installer in `dist\`.
4. Target PCs still need `sdk\register-sdk.bat` run once as administrator.

## Notes
- If the Devices page shows "SDK not ready", the DLL isn't registered or PHP/DLL bitness differs.
- By-reference COM out-parameters (`ZkemClient::out()`) are untested against a real device; verify with **Test** on the Devices page first.

## Push to Hier Payroll
Every 2 minutes the shell runs `php artisan logs:push`, which sends unsent `attendance_logs` (oldest first, 500 per request) to `{website URL}/api/hier-sync/attendance-logs` with HTTP Basic Auth. Configure URL, username and password on the **Payroll Sync** page. The password is encrypted with Electron `safeStorage` (`push-credentials.bin` in user data) and only passed to the artisan process through the `HIER_PUSH_PASSWORD` environment variable. HTTPS is required (plain HTTP only when `APP_ENV=local`).
Request: `{installation_id, logs:[{ref, device_serial, enroll_number, punched_at, verify_mode, in_out_mode, work_code}]}`. Response: `{accepted:[ref...], rejected:[{ref, reason}]}`. Only accepted refs are marked `sent_at`.
