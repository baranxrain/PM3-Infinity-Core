@echo off
setlocal EnableExtensions
for %%I in ("%~dp0\..\..") do set "ROOT=%%~fI"
set "LOG=%ROOT%\T3B-ACCEPTANCE.log"
cd /d "%ROOT%" || exit /b 2
>"%LOG%" echo T-3B acquisition-only PHPUnit 9/10/11 cumulative acceptance
call :run "Acquire and verify all pinned PHPUnit PHARs" "powershell.exe -NoProfile -ExecutionPolicy Bypass -File .\tests\tools\acquire-phpunit-all.ps1" || goto :fail
call :run "Acquisition-only repository preflight" "php tests\tools\verify-t3b-acquisition-only.php" || goto :fail
call :run "Complete T-3A cumulative historical, browser, Composer and PHPUnit 9/10/11 chain" "tests\tools\run-t3a-checks.cmd" || goto :fail
findstr /C:"T3A_ACCEPTANCE=PASS" "T3A-ACCEPTANCE.log" >nul || goto :fail
>>"%LOG%" echo T3B_ACCEPTANCE=PASS
echo [PASS] T-3B acquisition-only acceptance passed.
exit /b 0
:run
>>"%LOG%" echo ============================================================
>>"%LOG%" echo [RUN] %~1
cmd.exe /D /S /C "%~2" >>"%LOG%" 2>&1
set "RC=%ERRORLEVEL%"
>>"%LOG%" echo [EXIT] %RC%
exit /b %RC%
:fail
>>"%LOG%" echo T3B_ACCEPTANCE=FAIL
echo [FAIL] Inspect "%LOG%".
exit /b 1
