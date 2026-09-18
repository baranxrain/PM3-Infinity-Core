@echo off
setlocal EnableExtensions
for %%I in ("%~dp0\..\..") do set "ROOT=%%~fI"
set "LOG=%ROOT%\T4B-ACCEPTANCE.log"
cd /d "%ROOT%" || exit /b 2
>"%LOG%" echo T-4B PHP 8.3 Composer closure and PHPUnit 12 cumulative acceptance

call "%ROOT%\tests\tools\run-t4a-checks.cmd"
set "RC=%ERRORLEVEL%"
if exist "%ROOT%\T4A-ACCEPTANCE.log" type "%ROOT%\T4A-ACCEPTANCE.log" >>"%LOG%"
if not "%RC%"=="0" goto :fail

>>"%LOG%" echo ============================================================
>>"%LOG%" echo [RUN] T-4B Composer closure preflight
php tests\tools\verify-t4b-composer-closure.php >>"%LOG%" 2>&1
set "RC=%ERRORLEVEL%"
>>"%LOG%" echo [EXIT] %RC%
if not "%RC%"=="0" goto :fail

>>"%LOG%" echo ============================================================
>>"%LOG%" echo [RUN] Validate Composer metadata
call composer validate --no-check-publish --no-interaction >>"%LOG%" 2>&1
set "RC=%ERRORLEVEL%"
>>"%LOG%" echo [EXIT] %RC%
if not "%RC%"=="0" goto :fail

>>"%LOG%" echo ============================================================
>>"%LOG%" echo [RUN] Check production lock platform
call composer check-platform-reqs --lock --no-dev >>"%LOG%" 2>&1
set "RC=%ERRORLEVEL%"
>>"%LOG%" echo [EXIT] %RC%
if not "%RC%"=="0" goto :fail

>>"%LOG%" echo ============================================================
>>"%LOG%" echo [RUN] Check complete lock platform
call composer check-platform-reqs --lock >>"%LOG%" 2>&1
set "RC=%ERRORLEVEL%"
>>"%LOG%" echo [EXIT] %RC%
if not "%RC%"=="0" goto :fail

>>"%LOG%" echo T4B_ACCEPTANCE=PASS
echo [PASS] T-4B cumulative acceptance passed.
exit /b 0

:fail
>>"%LOG%" echo T4B_ACCEPTANCE=FAIL
echo [FAIL] Inspect "%LOG%".
exit /b 1
