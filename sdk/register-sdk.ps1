# Registers (or unregisters) the 64-bit ZKTeco SDK for COM. Must run elevated in 64-bit PowerShell.
param([switch]$Unregister)

$ErrorActionPreference = 'Stop'
$src = Join-Path $PSScriptRoot 'x64'
$sys = Join-Path $env:windir 'System32'
$dll = Join-Path $sys 'zkemkeeper.dll'

if ($Unregister) {
    if (Test-Path $dll) { & "$sys\regsvr32.exe" /u /s $dll }
    exit 0
}

Copy-Item "$src\*.dll" $sys -Force
& "$sys\regsvr32.exe" /s $dll
exit $LASTEXITCODE
