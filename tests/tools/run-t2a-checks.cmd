@echo off
setlocal EnableExtensions
for %%I in ("%~dp0\..\..") do set "ROOT=%%~fI"
set "LOG=%ROOT%\T2A-ACCEPTANCE.log"
set "ORACLE=%ROOT%\tests\fixtures\legacy-strftime-locale-oracle.json"
set "BACKUP=%TEMP%\t2a-oracle-%RANDOM%-%RANDOM%.json"
cd /d "%ROOT%" || exit /b 2
>"%LOG%" echo T-2A cumulative PHP 8.2 discovery
if exist "%ORACLE%" copy /y "%ORACLE%" "%BACKUP%" >nul
call tests\tools\run-u319-checks.cmd
set "RC=%ERRORLEVEL%"
if exist "%BACKUP%" copy /y "%BACKUP%" "%ORACLE%" >nul
if exist "%BACKUP%" del /q "%BACKUP%"
if exist U319-ACCEPTANCE.log type U319-ACCEPTANCE.log >>"%LOG%"
if not "%RC%"=="0" goto :fail
call tests\tools\run-t2a-php82-checks.cmd
set "RC=%ERRORLEVEL%"
if exist T2A-PHP82-DISCOVERY.log type T2A-PHP82-DISCOVERY.log >>"%LOG%"
if not "%RC%"=="0" goto :fail
>>"%LOG%" echo T2A_DISCOVERY=PASS
echo [PASS] T-2A cumulative discovery passed.&exit /b 0
:fail
>>"%LOG%" echo T2A_DISCOVERY=FAIL
echo [FAIL] Inspect "%LOG%".&exit /b 1
