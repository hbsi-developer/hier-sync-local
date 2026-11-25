<#
  Validates the project before a commit or release:
    1. PHP syntax        (php -l on app, config, routes, database, resources, tests)
    2. JS syntax         (node --check on electron/ and scripts/)
    3. package.json      (valid JSON, semver version)
    4. Secrets scan      (tracked files must not hold keys, passwords or API credentials)
    5. Tests             (php artisan test)
    6. Code style        (Pint, advisory only: reports but does not fail)

  Usage:  powershell -NoProfile -ExecutionPolicy Bypass -File scripts\validate.ps1 [-SkipTests] [-Php <path-to-php.exe>]
  PHP 8.4+ is required (composer.lock). Resolution order: -Php, $env:PHP_BIN, newest Laragon 8.4+/8.5.
#>
param(
    [switch]$SkipTests,
    [string]$Php = $env:PHP_BIN
)

$ErrorActionPreference = 'Stop'
$root = Split-Path -Parent $PSScriptRoot
Set-Location $root

$failures = New-Object System.Collections.Generic.List[string]
function Step($name) { Write-Host "`n== $name" -ForegroundColor Cyan }
function Fail($msg) { Write-Host "  FAIL  $msg" -ForegroundColor Red; $failures.Add($msg) }
function Ok($msg) { Write-Host "  ok    $msg" -ForegroundColor Green }

# ---- locate PHP 8.4+ -------------------------------------------------------------------------
if (-not $Php -or -not (Test-Path $Php)) {
    $Php = Get-ChildItem 'C:\laragon\bin\php\php-8.[4-9]*\php.exe' -ErrorAction SilentlyContinue |
        Sort-Object { [version]($_.Directory.Name -replace '^php-(\d+\.\d+\.\d+).*', '$1') } -Descending |
        Select-Object -First 1 -ExpandProperty FullName
}
if (-not $Php) { Write-Host 'PHP 8.4+ not found. Pass -Php <path> or set PHP_BIN.' -ForegroundColor Red; exit 2 }
$phpVersion = & $Php -r 'echo PHP_VERSION;'
Write-Host "PHP $phpVersion  ($Php)"
if ([version]($phpVersion -replace '[^0-9.].*$', '') -lt [version]'8.4.1') { Write-Host 'PHP 8.4.1+ is required.' -ForegroundColor Red; exit 2 }

# ---- 1. PHP syntax ---------------------------------------------------------------------------
Step '1. PHP syntax'
$bad = 0
$files = Get-ChildItem -Path app, config, routes, database, resources, tests -Include *.php -Recurse -File
foreach ($f in $files) {
    $out = & $Php -l $f.FullName 2>&1
    if ($LASTEXITCODE -ne 0) { Fail "$($f.FullName): $out"; $bad++ }
}
if ($bad -eq 0) { Ok "$($files.Count) files" }

# ---- 2. JS syntax ----------------------------------------------------------------------------
Step '2. JavaScript syntax'
if (Get-Command node -ErrorAction SilentlyContinue) {
    $js = Get-ChildItem -Path electron, scripts -Include *.js -Recurse -File
    $bad = 0
    foreach ($f in $js) {
        node --check $f.FullName 2>&1 | Out-Null
        if ($LASTEXITCODE -ne 0) { Fail "$($f.Name) has a syntax error"; $bad++ }
    }
    if ($bad -eq 0) { Ok "$($js.Count) files" }
} else { Write-Host '  skip  node not found' -ForegroundColor Yellow }

# ---- 3. package.json -------------------------------------------------------------------------
Step '3. package.json'
try {
    $pkg = Get-Content package.json -Raw | ConvertFrom-Json
    if ($pkg.version -match '^\d+\.\d+\.\d+$') { Ok "version $($pkg.version)" } else { Fail "version '$($pkg.version)' is not semver (major.minor.patch)" }
} catch { Fail "package.json is not valid JSON: $($_.Exception.Message)" }

# ---- 4. Secrets scan -------------------------------------------------------------------------
Step '4. Secrets scan'
$patterns = @(
    'BEGIN (RSA |EC |OPENSSH )?PRIVATE KEY',
    'APP_KEY=base64:[A-Za-z0-9+/=]{20,}',
    'sl_[a-z0-9]{14,}',
    'HIER_PUSH_PASSWORD\s*=\s*[^\s$]+'
)
$tracked = git ls-files | Where-Object { $_ -notmatch '^(sdk/|vendor/|node_modules/|package-lock\.json|composer\.lock|phpunit\.xml|CHANGELOG\.md|\.claude/|docs/)' -and $_ -notmatch '\.(png|ico|dll|exe|ttf|pdf)$' }
$hits = 0
foreach ($p in $patterns) {
    $found = $tracked | ForEach-Object { Select-String -Path $_ -Pattern $p -ErrorAction SilentlyContinue } | Select-Object -First 5
    foreach ($h in $found) { Fail "possible secret in $($h.Path):$($h.LineNumber) (pattern $p)"; $hits++ }
}
if (git ls-files .env) { Fail '.env is tracked by git'; $hits++ }
if ($hits -eq 0) { Ok 'no secrets found in tracked files' }

# ---- 5. Tests --------------------------------------------------------------------------------
Step '5. Tests'
if ($SkipTests) { Write-Host '  skip  -SkipTests' -ForegroundColor Yellow }
else {
    & $Php artisan test 2>&1 | Select-Object -Last 3
    if ($LASTEXITCODE -ne 0) { Fail 'artisan test failed' } else { Ok 'all tests passed' }
}

# ---- 6. Code style (advisory) ----------------------------------------------------------------
Step '6. Code style (advisory)'
$pint = & $Php vendor/bin/pint --test 2>&1 | Out-String
if ($LASTEXITCODE -eq 0) { Ok 'Pint is clean' } else { Write-Host '  warn  Pint reports style differences (not blocking). Run: php vendor\bin\pint' -ForegroundColor Yellow }

# ---- summary ---------------------------------------------------------------------------------
Write-Host ''
if ($failures.Count -gt 0) {
    Write-Host "VALIDATION FAILED ($($failures.Count) problem(s))" -ForegroundColor Red
    exit 1
}
Write-Host 'VALIDATION PASSED' -ForegroundColor Green
