@echo off
setlocal
set "PATH=%SystemRoot%\system32;%SystemRoot%;%SystemRoot%\System32\Wbem;%SystemRoot%\System32\WindowsPowerShell\v1.0"
"%SystemRoot%\System32\WindowsPowerShell\v1.0\powershell.exe" -NoProfile -ExecutionPolicy Bypass -File "D:\TehranBridge\TehranPyramidReadOnly.ps1" 1>"D:\TehranBridge\last-task-run.log" 2>&1
set "EXITCODE=%ERRORLEVEL%"
echo SCHEDULED_TASK_EXIT=%EXITCODE%>>"D:\TehranBridge\last-task-run.log"
exit /b %EXITCODE%
