# Testing and validation rules

- Run the suite with PHP 8.4+: `php artisan test` (use the Laragon 8.4 binary, see `code-style.md`).
  Or run everything with `powershell -NoProfile -ExecutionPolicy Bypass -File scripts/validate.ps1`
  (or the `/validate` command). Details: `docs/TESTING.md`.
- **Every behaviour change gets a test.** Push logic lives in `tests/Feature/LogPushTest.php` (HTTP is faked with
  `Http::fake`); keep new push, settings, and logging tests there or in a new file under `tests/Feature`.
- Tests must be self-contained: in-memory SQLite (`RefreshDatabase`), fake HTTP, fake password via
  `config(['sync.password' => ...])`. **No real network calls, no real devices, no real credentials.**
- Anything that talks to hardware (`ZkemClient`, `bridge/zkbridge.exe`) is verified manually on a device and is not
  part of the automated suite. Say so in the PR instead of mocking it badly.
- Before reporting a task done: the full suite passes and `validate.ps1` ends with `VALIDATION PASSED`.
  A failing test is reported with its output, never skipped or deleted to get green.
- UI changes: load the page in the browser pane at desktop and phone width and check dark mode.
  Pages that depend on Electron cannot be fully exercised in a plain browser; say what was not checked.
- Date-dependent tests use `now()`-relative values (auto push resends "today"), never hard-coded current dates.
