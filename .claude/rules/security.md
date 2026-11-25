# Security rules

- **Never commit credentials.** No payroll usernames/passwords, API keys, `.env`, `app.key`, or
  `push-credentials.bin`. Tests use fake values (`sync-user` / `s3cret`). `scripts/validate.ps1` scans tracked
  files for secrets; fix anything it reports.
- **Never log or echo the password.** Not in `SyncLog` messages, `laravel.log`, command output, error messages,
  flash messages, or test failure output. `logs:push` has a test asserting the password is absent from output.
- **The password path is fixed:** page → `window.hierSync.setPassword` → Electron `safeStorage` →
  `HIER_PUSH_PASSWORD` env of the artisan child. Do not add a route, setting, or form field that carries it.
- **Credentials supplied for testing** (for example in a chat) are used only for the requested call, never written
  to files, and are treated as exposed afterwards (recommend rotating).
- **Outbound requests** keep `withoutRedirecting()`, a timeout, and HTTPS-only (plain HTTP only in
  `APP_ENV=local`). Reject URLs with embedded credentials.
- **IPC** handlers check `trusted(event)` and validate input shape (dates match `^\d{4}-\d{2}-\d{2}$`).
  Command-line values passed to `artisan` must come from validated values, never raw page input.
- **Destructive device actions** (writing a card number or name, setting device time, removing a device) stay
  behind an explicit user action and never run from a timer.
