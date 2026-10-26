@echo off
:: Builds zkbridge.exe (64-bit) with the .NET Framework compiler that ships with Windows.
cd /d %~dp0
"%windir%\Microsoft.NET\Framework64\v4.0.30319\csc.exe" /nologo /platform:x64 /target:exe /out:zkbridge.exe /r:System.Web.Extensions.dll /r:Microsoft.CSharp.dll /r:System.Core.dll ZkBridge.cs
