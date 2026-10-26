# Prepares build-stage\ for electron-builder: a production copy of the Laravel app plus a PHP runtime.
param(
    [string]$PhpDir = 'C:\laragon\bin\php\php-8.4.26-Win32-vs17-x64'
)

$ErrorActionPreference = 'Stop'
$root = Split-Path $PSScriptRoot -Parent
$stage = Join-Path $root 'build-stage'
$app = Join-Path $stage 'laravel'
$php = Join-Path $stage 'php'

if (Test-Path $stage) { Remove-Item $stage -Recurse -Force }
New-Item -ItemType Directory -Force $app, $php | Out-Null

# Laravel app (no dev tooling, no local data, no secrets)
robocopy $root $app /E /NFL /NDL /NJH /NJS /NP `
    /XD node_modules php dist build-stage build .git tests desktop-data `
        (Join-Path $root 'storage\desktop') (Join-Path $root 'storage\logs') (Join-Path $root 'sdk\x86') `
        (Join-Path $root 'vendor') `
    /XF .env database.sqlite *.pdf README-DESKTOP.md package-lock.json | Out-Null
if ($LASTEXITCODE -ge 8) { throw "robocopy failed ($LASTEXITCODE)" }

# desktop\php.ini lives in the app (excluded 'desktop-data' only matches nothing); make sure it is present
New-Item -ItemType Directory -Force (Join-Path $app 'desktop') | Out-Null
Copy-Item (Join-Path $root 'desktop\php.ini') (Join-Path $app 'desktop\php.ini') -Force

# Empty storage skeleton and a writable-by-nobody-needed bootstrap/cache
foreach ($d in 'storage\logs', 'storage\framework\views', 'storage\framework\cache', 'storage\framework\sessions', 'bootstrap\cache') {
    New-Item -ItemType Directory -Force (Join-Path $app $d) | Out-Null
}

# Production dependencies only
Push-Location $app
# Manifests copied from the dev tree list dev-only providers (Pail, Collision...); rebuild them below.
Remove-Item (Join-Path $app 'bootstrap\cache\*.php') -Force -ErrorAction SilentlyContinue
& composer install --no-dev --optimize-autoloader --no-interaction --no-scripts
if ($LASTEXITCODE -ne 0) { throw 'composer install failed' }

# Pre-build Laravel's package/service manifests: the install folder is read-only for normal users.
$env:APP_KEY = 'base64:' + [Convert]::ToBase64String((1..32 | ForEach-Object { [byte](Get-Random -Max 256) }))
$env:DB_CONNECTION = 'sqlite'; $env:DB_DATABASE = ':memory:'; $env:SESSION_DRIVER = 'array'; $env:CACHE_STORE = 'array'
& (Join-Path $PhpDir 'php.exe') -c (Join-Path $app 'desktop\php.ini') -d "extension_dir=$PhpDir\ext" artisan package:discover
if ($LASTEXITCODE -ne 0) { throw 'package:discover failed' }
foreach ($f in 'packages.php', 'services.php') {
    if (-not (Test-Path (Join-Path $app "bootstrap\cache\$f"))) { throw "bootstrap\cache\$f was not generated" }
}
Remove-Item Env:APP_KEY, Env:DB_CONNECTION, Env:DB_DATABASE, Env:SESSION_DRIVER, Env:CACHE_STORE
Pop-Location

# PHP runtime (skip debug/dev bits)
robocopy $PhpDir $php /E /NFL /NDL /NJH /NJS /NP /XD devel extras /XF *.pdb php-cgi.exe phpdbg.exe php-win.exe | Out-Null
if ($LASTEXITCODE -ge 8) { throw "robocopy php failed ($LASTEXITCODE)" }

'{0:N0} MB staged' -f ((Get-ChildItem $stage -Recurse -File | Measure-Object Length -Sum).Sum / 1MB)
