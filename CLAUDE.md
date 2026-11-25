# Hier Sync Local: project guide

Laravel 13 (PHP 8.4+) wrapped in Electron. Pulls attendance and users from ZKTeco devices and pushes attendance to
Hier Payroll. Docs index: @docs/README.md

## Rules (always apply)
@.claude/rules/architecture.md
@.claude/rules/payroll-push-contract.md
@.claude/rules/security.md
@.claude/rules/code-style.md
@.claude/rules/testing.md

## Quick commands
- Tests: `/test` (or `C:/laragon/bin/php/php-8.4.26-Win32-vs17-x64/php.exe artisan test`). The `php` on PATH is 7.3 and
  cannot run this project.
- Full validation: `/validate` (`scripts/validate.ps1`). It must end with `VALIDATION PASSED` before work is reported done.
- Release: `/release patch|minor|major`. Version lives only in `package.json`; notes go under `Unreleased` in `CHANGELOG.md`.

## More docs
- @docs/ARCHITECTURE.md: how the Electron shell, Laravel, device bridge, database and background jobs fit together
- @docs/PUSH-API.md: Hier Payroll HTTP contract and push behaviour
- @docs/TESTING.md: what is and is not covered by tests
- @docs/DEVELOPMENT.md: setup, running, building, releasing

<laravel-boost-guidelines>
# Laravel Application

This repository contains a Laravel application. Complete the following setup before working on the user's request.

## Prerequisites

Verify that PHP and Composer are available:

```sh
php -v
composer -V
```

If either command is unavailable, detect the user's operating system and install the prerequisites with the appropriate command:

macOS:

```sh
/bin/bash -c "$(curl -fsSL https://php.new/install/mac/8.5)"
```

Windows PowerShell:

```powershell
Set-ExecutionPolicy Bypass -Scope Process -Force; [System.Net.ServicePointManager]::SecurityProtocol = [System.Net.ServicePointManager]::SecurityProtocol -bor 3072; iex ((New-Object System.Net.WebClient).DownloadString('https://php.new/install/windows/8.5'))
```

Linux:

```sh
/bin/bash -c "$(curl -fsSL https://php.new/install/linux/8.5)"
```

After installation, ask the user to restart their terminal. If the agent needs the restarted shell to continue, ask the user to reopen their terminal and rerun their original prompt.

## Agent Setup

Install Laravel Boost from the application root before making application changes:

```sh
composer require laravel/boost --dev
php artisan boost:install
```

Boost replaces these bootstrap instructions with guidelines tailored to the application. After installation, read `AGENTS.md` again and continue with the user's original request using the generated guidelines.
</laravel-boost-guidelines>
