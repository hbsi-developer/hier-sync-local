@echo off
:: Registers the ZKTeco SDK (zkemkeeper.dll) for COM. Right-click > Run as administrator.
:: Use x64 if your PHP is 64-bit (the default for this app), x86 for 32-bit PHP.
cd /d %~dp0
net session >nul 2>&1 || (echo Please run as Administrator & pause & exit /b 1)
copy /Y "%~dp0x64\*.dll" "%windir%\System32\"
regsvr32 "%windir%\System32\zkemkeeper.dll"
pause
