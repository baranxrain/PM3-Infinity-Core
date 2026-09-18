@echo off
setlocal EnableExtensions
for %%I in ("%~dp0\..\..") do set "ROOT=%%~fI"
set "LOG=%ROOT%\T4A-ACCEPTANCE.log"
cd /d "%ROOT%" || exit /b 2
>"%LOG%" echo T-4A cumulative PHP 8.3 and PHPUnit 12 discovery
call :run "Acquire pinned PHPUnit 12.5.35" "powershell.exe -NoProfile -ExecutionPolicy Bypass -File .\tests\tools\acquire-phpunit12.ps1" || goto :fail
call tests\tools\run-t3b-checks.cmd
set "RC=%ERRORLEVEL%"
if exist T3B-ACCEPTANCE.log type T3B-ACCEPTANCE.log >>"%LOG%"
if not "%RC%"=="0" goto :fail
call tests\tools\run-t4a-phpunit12-checks.cmd
set "RC=%ERRORLEVEL%"
if exist T4A-PHPUNIT12-DISCOVERY.log type T4A-PHPUNIT12-DISCOVERY.log >>"%LOG%"
if not "%RC%"=="0" goto :fail
>>"%LOG%" echo T4A_ACCEPTANCE=PASS
echo [PASS] T-4A cumulative acceptance passed.&exit /b 0
:run
>>"%LOG%" echo ============================================================
>>"%LOG%" echo [RUN] %~1
cmd.exe /D /S /C "%~2" >>"%LOG%" 2>&1
set "RC=%ERRORLEVEL%"
>>"%LOG%" echo [EXIT] %RC%
exit /b %RC%
:fail
>>"%LOG%" echo T4A_ACCEPTANCE=FAIL
echo [FAIL] Inspect "%LOG%".&exit /b 1
