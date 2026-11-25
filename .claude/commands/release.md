---
description: Prepare a release (validate, changelog, version bump) after confirming the bump type
argument-hint: "patch | minor | major"
allowed-tools: Bash(powershell:*), Bash(npm run:*), Bash(git status:*), Bash(git log:*), Bash(git diff:*)
---

Prepare release `$ARGUMENTS` (ask which of patch, minor or major if it is missing; features are minor, fixes are patch,
breaking changes are major).

1. `git status` must be clean and the branch correct. If not, stop and tell the user.
2. Run `scripts/validate.ps1`; stop on any failure.
3. Make sure `CHANGELOG.md` has accurate notes under `## [Unreleased]` for everything since the last tag
   (`git log <last-tag>..HEAD`). Add missing entries and commit them.
4. Tell the user the new version number and ask for a go-ahead, then run `npm run release:$ARGUMENTS`
   (bumps `package.json`, dates the changelog, commits and tags `vX.Y.Z`).
5. **Build the installer right after the version bump:** run `npm run dist` and confirm
   `dist\Hier-Sync-Local-Setup-<new version>.exe` exists (report its path and size). If the build fails, show the error and
   stop; the release commit and tag stay as they are.
6. Do **not** push the commit or tag unless the user asks. `dist\` is git-ignored, so the installer is never committed.
