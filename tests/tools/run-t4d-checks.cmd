@echo off
setlocal EnableExtensions
for %%I in ("%~dp0\..\..") do set "ROOT=%%~fI"
set "LOG=%ROOT%\T4D-ACCEPTANCE.log"
cd /d "%ROOT%" || exit /b 2
>"%LOG%" echo T-4D PHP 8.3 installer boundary cumulative acceptance
call "%ROOT%\tests\tools\run-t4c-checks.cmd"
set "RC=%ERRORLEVEL%"
if exist "%ROOT%\T4C-ACCEPTANCE.log" type "%ROOT%\T4C-ACCEPTANCE.log" >>"%LOG%"
if not "%RC%"=="0" goto :fail
call :run "T-4D PHP 8.3 installer boundary preflight" "powershell.exe -NoProfile -ExecutionPolicy Bypass -File .\tests\tools\verify-t4d-php83-installer.ps1" || goto :fail
findstr /C:"T4D_PREFLIGHT=PASS" "%LOG%" >nul || goto :fail
>>"%LOG%" echo T4D_ACCEPTANCE=PASS
echo [PASS] T-4D cumulative installer acceptance passed.
exit /b 0
:run
>>"%LOG%" echo ============================================================
>>"%LOG%" echo [RUN] %~1
cmd.exe /D /S /C "%~2" >>"%LOG%" 2>&1
set "RC=%ERRORLEVEL%"
>>"%LOG%" echo [EXIT] %RC%
exit /b %RC%
:fail
>>"%LOG%" echo T4D_ACCEPTANCE=FAIL
echo [FAIL] Inspect "%LOG%".
exit /b 1
