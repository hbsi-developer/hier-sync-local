; Registers the ZKTeco SDK (COM) during install and removes the registration on uninstall.
; The NSIS stub is 32-bit, so call the 64-bit PowerShell through Sysnative.

!macro customInstall
  DetailPrint "Registering ZKTeco SDK..."
  nsExec::ExecToLog '"$WINDIR\Sysnative\WindowsPowerShell\v1.0\powershell.exe" -NoProfile -ExecutionPolicy Bypass -File "$INSTDIR\resources\laravel\sdk\register-sdk.ps1"'
  Pop $0
  ${If} $0 != 0
    MessageBox MB_OK|MB_ICONEXCLAMATION "The ZKTeco SDK could not be registered (code $0). Run sdk\register-sdk.bat as administrator after installing."
  ${EndIf}
!macroend

!macro customUnInstall
  nsExec::ExecToLog '"$WINDIR\Sysnative\WindowsPowerShell\v1.0\powershell.exe" -NoProfile -ExecutionPolicy Bypass -File "$INSTDIR\resources\laravel\sdk\register-sdk.ps1" -Unregister'
!macroend
