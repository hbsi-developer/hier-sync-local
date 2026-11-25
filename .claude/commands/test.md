---
description: Run the PHPUnit suite with PHP 8.4 (optionally filtered, e.g. /test LogPushTest)
argument-hint: "[test class or method filter]"
allowed-tools: Bash(C:/laragon/bin/php/*:*)
---

Run the tests with PHP 8.4. The `php` on PATH is 7.3 and cannot run this project.

- No argument: `C:/laragon/bin/php/php-8.4.26-Win32-vs17-x64/php.exe artisan test`
- With an argument: add `--filter=$ARGUMENTS`

Report passed/failed counts. For failures show the test name and message, find the cause, and fix the code (or the
test if the test is wrong, saying why). See `.claude/rules/testing.md`.
