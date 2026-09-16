@echo off
setlocal EnableExtensions
for %%I in ("%~dp0\..\..") do set "ROOT=%%~fI"
set "LOG=%ROOT%\T1B-PHPUNIT10.log"
cd /d "%ROOT%" || exit /b 2
>"%LOG%" echo T-1B pinned PHPUnit 10.5.64 clean gate
call :run "Preflight" "php tests\tools\verify-t1b-phpunit10.php" || goto :fail
call :run "Suite" "php tests\tools\phpunit-10.phar -c phpunit-10.xml --testsuite unit --colors=never --do-not-cache-result --display-all-issues --fail-on-deprecation --fail-on-phpunit-deprecation" || goto :fail
findstr /C:"OK (604 tests, 9241 assertions)" "%LOG%" >nul || goto :fail
findstr /C:"Deprecations:" "%LOG%" >nul && goto :fail
>>"%LOG%" echo T1B_PHPUNIT10=PASS
echo [PASS] T-1B PHPUnit 10 passed.&exit /b 0
:run
>>"%LOG%" echo ============================================================
>>"%LOG%" echo [RUN] %~1
cmd.exe /D /S /C "%~2" >>"%LOG%" 2>&1
set "RC=%ERRORLEVEL%"
>>"%LOG%" echo [EXIT] %RC%
exit /b %RC%
:fail
>>"%LOG%" echo T1B_PHPUNIT10=FAIL
echo [FAIL] Inspect "%LOG%".&exit /b 1
