@echo off
setlocal
"%SystemRoot%\System32\WindowsPowerShell\v1.0\powershell.exe" -NoProfile -ExecutionPolicy Bypass -File "D:\TehranBridge\TehranSiteMappingPreview.ps1" 1>"D:\TehranBridge\last-mapping-preview.log" 2>&1
exit /b %ERRORLEVEL%
