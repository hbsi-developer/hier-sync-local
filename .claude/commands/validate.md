---
description: Run full validation (PHP + JS syntax, package.json, secrets scan, tests, style)
allowed-tools: Bash(powershell:*)
---

Run the project validation and report the result.

1. Run `powershell -NoProfile -ExecutionPolicy Bypass -File scripts/validate.ps1`.
2. If it ends with `VALIDATION PASSED`, say so in one line and mention any advisory warnings (Pint style).
3. If it fails, show each `FAIL` line, find the cause, and fix it (or explain why you can't). Re-run until it passes.
   Never skip a failing check or delete a failing test to get green.
