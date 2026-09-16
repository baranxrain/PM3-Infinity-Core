@echo off
setlocal EnableExtensions
for %%I in ("%~dp0\..\..") do set "ROOT=%%~fI"
set "LOG=%ROOT%\T3A-ACCEPTANCE.log"
cd /d "%ROOT%" || exit /b 2
>"%LOG%" echo T-3A cumulative PHP 8.2 plus PHPUnit 11 discovery
call tests\tools\run-t2a-checks.cmd
set "RC=%ERRORLEVEL%"
if exist T2A-ACCEPTANCE.log type T2A-ACCEPTANCE.log >>"%LOG%"
if not "%RC%"=="0" goto :fail
call tests\tools\run-t3a-phpunit11-checks.cmd
set "RC=%ERRORLEVEL%"
if exist T3A-PHPUNIT11-DISCOVERY.log type T3A-PHPUNIT11-DISCOVERY.log >>"%LOG%"
if not "%RC%"=="0" goto :fail
>>"%LOG%" echo T3A_ACCEPTANCE=PASS
echo [PASS] T-3A cumulative acceptance passed.&exit /b 0
:fail
>>"%LOG%" echo T3A_ACCEPTANCE=FAIL
echo [FAIL] Inspect "%LOG%".&exit /b 1
