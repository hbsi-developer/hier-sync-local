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
- `package.json` is the only place the version is set. Add notes under `## [Unreleased]` in `CHANGELOG.md`; use
  `npm run release:patch|minor|major` to release.
