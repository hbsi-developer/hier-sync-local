# Code and UI conventions

**PHP / Laravel**
- Run PHP **8.4+** only (`composer.lock` requires it). On this machine use
  `C:\laragon\bin\php\php-8.4.26-Win32-vs17-x64\php.exe`; the `php` on PATH is 7.3 and will fail.
- Follow the existing style: `declare` types on new methods, `! $x` with a space, short closures, services over
  fat controllers, `Setting::get/put` for configuration. Pint is advisory (`vendor/bin/pint`); do not reformat
  unrelated files in a feature change.
- Validate request input in the controller with `$request->validate(...)`; return `back()->with('ok'|'error', ...)`.
- Names shown to the user must be keyed by **device + enroll number** (the same enroll number is a different person
  on each device).

**Blade / UI**
- Pages extend `layout` and use the existing tokens in `layout.blade.php` (`--navy`, `--brand`, `.card`, `.pill`,
  `.banner`, `.row`, `label.f`). No new CSS framework. Montserrat is the font.
- Copy rules: sentence case, verb-first buttons, no terminal punctuation on labels, contractions are fine.
- Anything that needs Electron (password, test connection, push range) must degrade when `window.hierSync` is
  missing: hide it and show "Open this page in the Hier Sync Local desktop app".
- Respect the `[hidden]` attribute (global rule exists); don't override it with `display`.
- Check the page at desktop and phone width and in dark mode (`prefers-color-scheme`).

**JavaScript (Electron)**
- Plain CommonJS, no build step. Run `node --check electron/*.js` after edits.

**Versioning**
- When the version is bumped, follow this checklist in order:
  1. Pick the bump: fix, build or docs-only = `patch`; new feature = `minor`; breaking contract or data change =
     `major`. Confirm the new number with the user before bumping.
  2. Before the bump: `git status` is clean, `scripts/validate.ps1` ends `VALIDATION PASSED`, and `## [Unreleased]`
     lists everything since the last tag (`git log <last-tag>..HEAD`).
  3. Bump only with `npm run release:patch|minor|major`. Never edit `version` by hand.
  4. Right after the bump run `npm run dist`; confirm `dist\Hier-Sync-Local-Setup-<version>.exe` exists and report
     its path and size.
  5. Check the version matches in `package.json`, the root entry of `package-lock.json`, the top `CHANGELOG.md`
     heading and the installer file name.
  6. Never commit `dist\`; never push the commit or tag unless asked.
- `package.json` is the only place the version is set. Add notes under `## [Unreleased]` in `CHANGELOG.md`; use
  `npm run release:patch|minor|major` to release. **Every version bump is followed by `npm run dist`** to build
  `dist\Hier-Sync-Local-Setup-<version>.exe`; confirm the file exists and report it. Never commit `dist\`.
