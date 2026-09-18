@echo off
setlocal EnableExtensions
for %%I in ("%~dp0\..\..") do set "ROOT=%%~fI"
set "LOG=%ROOT%\T4C-ACCEPTANCE.log"
cd /d "%ROOT%" || exit /b 2
>"%LOG%" echo T-4C PHP 8.3 runtime baseline cumulative acceptance
call "%ROOT%\tests\tools\run-t4b-checks.cmd"
set "RC=%ERRORLEVEL%"
if exist "%ROOT%\T4B-ACCEPTANCE.log" type "%ROOT%\T4B-ACCEPTANCE.log" >>"%LOG%"
if not "%RC%"=="0" goto :fail
>>"%LOG%" echo ============================================================
>>"%LOG%" echo [RUN] T-4C PHP 8.3 runtime boundary preflight
php tests\tools\verify-t4c-php83-runtime.php >>"%LOG%" 2>&1
set "RC=%ERRORLEVEL%"
>>"%LOG%" echo [EXIT] %RC%
if not "%RC%"=="0" goto :fail
>>"%LOG%" echo T4C_ACCEPTANCE=PASS
echo [PASS] T-4C cumulative acceptance passed.
exit /b 0
:fail
>>"%LOG%" echo T4C_ACCEPTANCE=FAIL
echo [FAIL] Inspect "%LOG%".
exit /b 1
