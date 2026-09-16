@echo off
setlocal EnableExtensions
for %%I in ("%~dp0\..\..") do set "ROOT=%%~fI"
set "LOG=%ROOT%\T1B-ACCEPTANCE.log"
set "ORACLE=%ROOT%\tests\fixtures\legacy-strftime-locale-oracle.json"
set "BACKUP=%TEMP%\t1b-oracle-%RANDOM%-%RANDOM%.json"
cd /d "%ROOT%" || exit /b 2
>"%LOG%" echo T-1B cumulative acceptance
if exist "%ORACLE%" copy /y "%ORACLE%" "%BACKUP%" >nul
call tests\tools\run-u319-checks.cmd
set "RC=%ERRORLEVEL%"
if exist "%BACKUP%" copy /y "%BACKUP%" "%ORACLE%" >nul
if exist "%BACKUP%" del /q "%BACKUP%"
if exist U319-ACCEPTANCE.log type U319-ACCEPTANCE.log >>"%LOG%"
if not "%RC%"=="0" goto :fail
call tests\tools\run-t1b-phpunit10-checks.cmd
set "RC=%ERRORLEVEL%"
if exist T1B-PHPUNIT10.log type T1B-PHPUNIT10.log >>"%LOG%"
if not "%RC%"=="0" goto :fail
>>"%LOG%" echo T1B_ACCEPTANCE=PASS
echo [PASS] T-1B cumulative passed.&exit /b 0
:fail
>>"%LOG%" echo T1B_ACCEPTANCE=FAIL
echo [FAIL] Inspect "%LOG%".&exit /b 1
