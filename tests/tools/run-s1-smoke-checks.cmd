@echo off
setlocal EnableExtensions
for %%I in ("%~dp0\..\..") do set "ROOT=%%~fI"
set "LOG=%ROOT%\S1-ACCEPTANCE.log"
cd /d "%ROOT%" || exit /b 2
>"%LOG%" echo S-1 PHP 8.2 installer smoke-fix acceptance
call :run "Complete T-3B historical, Composer, browser and PHPUnit 9/10/11 chain" "tests\tools\run-t3b-checks.cmd" || goto :fail
findstr /C:"T3B_ACCEPTANCE=PASS" "T3B-ACCEPTANCE.log" >nul || goto :fail
call :run "Verify PHP 8.2 installer requirement boundaries and labels" "php tests\tools\verify-s1-php82-installer.php" || goto :fail
findstr /C:"S1_PREFLIGHT=PASS" "%LOG%" >nul || goto :fail
>>"%LOG%" echo S1_ACCEPTANCE=PASS
echo [PASS] S-1 PHP 8.2 installer smoke-fix acceptance passed.
exit /b 0
:run
>>"%LOG%" echo ============================================================
>>"%LOG%" echo [RUN] %~1
cmd.exe /D /S /C "%~2" >>"%LOG%" 2>&1
set "RC=%ERRORLEVEL%"
>>"%LOG%" echo [EXIT] %RC%
exit /b %RC%
:fail
>>"%LOG%" echo S1_ACCEPTANCE=FAIL
echo [FAIL] Inspect "%LOG%".
exit /b 1
