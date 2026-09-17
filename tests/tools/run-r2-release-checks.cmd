@echo off
setlocal EnableExtensions
for %%I in ("%~dp0\..\..") do set "ROOT=%%~fI"
set "LOG=%ROOT%\R2-ACCEPTANCE.log"
cd /d "%ROOT%" || exit /b 2
>"%LOG%" echo R-2 PHP 8.2 production release acceptance
call :run "Complete T-3B historical, Composer, browser and PHPUnit 9/10/11 chain" "tests\tools\run-t3b-checks.cmd" || goto :fail
findstr /C:"T3B_ACCEPTANCE=PASS" "T3B-ACCEPTANCE.log" >nul || goto :fail
call :run "Build production archive from committed HEAD" "powershell.exe -NoProfile -ExecutionPolicy Bypass -File .\tests\tools\build-r2-php82-release.ps1" || goto :fail
call :run "Verify production archive contents and SHA-256" "php tests\tools\verify-r2-php82-release.php" || goto :fail
findstr /C:"R2_PREFLIGHT=PASS" "%LOG%" >nul || goto :fail
>>"%LOG%" echo R2_ACCEPTANCE=PASS
echo [PASS] R-2 PHP 8.2 release acceptance passed.
exit /b 0
:run
>>"%LOG%" echo ============================================================
>>"%LOG%" echo [RUN] %~1
cmd.exe /D /S /C "%~2" >>"%LOG%" 2>&1
set "RC=%ERRORLEVEL%"
>>"%LOG%" echo [EXIT] %RC%
exit /b %RC%
:fail
>>"%LOG%" echo R2_ACCEPTANCE=FAIL
echo [FAIL] Inspect "%LOG%".
exit /b 1
