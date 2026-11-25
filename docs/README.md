# Hier Sync Local documentation

Hier Sync Local is a Windows desktop app (Laravel + Electron) that reads attendance and users from ZKTeco
terminals and pushes the attendance to Hier Payroll.

| Document | What is in it |
|---|---|
| [DEVELOPMENT.md](DEVELOPMENT.md) | Setup, running the app, building the installer, versioning and releases |
| [ARCHITECTURE.md](ARCHITECTURE.md) | How the pieces fit: Electron shell, Laravel, device bridge, data model, background jobs |
| [PUSH-API.md](PUSH-API.md) | The Hier Payroll HTTP contract and how pushes behave (auto, range, retries) |
| [TESTING.md](TESTING.md) | Running tests and the validation script, what is and is not covered |
| [../CHANGELOG.md](../CHANGELOG.md) | What changed in each version |
| [../README-DESKTOP.md](../README-DESKTOP.md) | One-time machine setup (SDK registration, PHP build) |

For AI-assisted work, project rules live in `.claude/rules/` and are linked from `CLAUDE.md`.

## User guide in one minute

1. **Devices:** add each terminal by IP and port, press **Test** (this reads the serial number), then **Pull logs**
   and **Pull users**.
2. **Settings:** enter the Hier Payroll website URL, username and password, set the push interval, turn push on,
   and press **Test connection**.
3. **Payroll Sync:** shows when the last push ran, how many logs are unsent, and lets you push a date range.
4. **Sync logs:** the history of pushes, device syncs, user pulls, tests and setting changes (kept for 30 days).
5. A device must be registered in Hier Payroll (by serial number) before its logs are accepted.
