@echo off
setlocal EnableExtensions
for %%I in ("%~dp0\..\..") do set "ROOT=%%~fI"
set "LOG=%ROOT%\T1A-DUAL-ACCEPTANCE.log"
set "ORACLE=%ROOT%\tests\fixtures\legacy-strftime-locale-oracle.json"
set "BACKUP=%TEMP%\t1a-oracle-%RANDOM%-%RANDOM%.json"
cd /d "%ROOT%" || exit /b 2
>"%LOG%" echo T-1A full dual discovery: historical U-3.19 plus PHPUnit 10
if exist "%ORACLE%" copy /y "%ORACLE%" "%BACKUP%" >nul
call tests\tools\run-u319-checks.cmd
set "RC9=%ERRORLEVEL%"
if exist "%BACKUP%" copy /y "%BACKUP%" "%ORACLE%" >nul
if exist "%BACKUP%" del /q "%BACKUP%"
if exist U319-ACCEPTANCE.log type U319-ACCEPTANCE.log >>"%LOG%"
if not "%RC9%"=="0" goto :failed
call tests\tools\run-t1a-phpunit10-checks.cmd
set "RC10=%ERRORLEVEL%"
if exist T1A-PHPUNIT10-DISCOVERY.log type T1A-PHPUNIT10-DISCOVERY.log >>"%LOG%"
if not "%RC10%"=="0" goto :failed
>>"%LOG%" echo T1A_DUAL_DISCOVERY=PASS
echo [PASS] T-1A full dual discovery passed.
exit /b 0
:failed
>>"%LOG%" echo T1A_DUAL_DISCOVERY=FAIL
echo [FAIL] Inspect "%LOG%".
exit /b 1
