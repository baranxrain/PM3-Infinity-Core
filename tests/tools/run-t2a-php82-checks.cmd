@echo off
setlocal EnableExtensions
for %%I in ("%~dp0\..\..") do set "ROOT=%%~fI"
set "LOG=%ROOT%\T2A-PHP82-DISCOVERY.log"
cd /d "%ROOT%" || exit /b 2
>"%LOG%" echo T-2A PHP 8.2 with pinned PHPUnit 10.5.64 discovery
call :run "PHP 8.2 preflight" "php tests\tools\verify-t2a-php82.php" || goto :fail
call :run "PHPUnit 10 on PHP 8.2" "php tests\tools\phpunit-10.phar -c phpunit-10.xml --testsuite unit --colors=never --do-not-cache-result --display-all-issues --fail-on-deprecation --fail-on-phpunit-deprecation" || goto :fail
findstr /C:"OK (604 tests, 9241 assertions)" "%LOG%" >nul || goto :fail
findstr /C:"Deprecations:" "%LOG%" >nul && goto :fail
>>"%LOG%" echo T2A_PHP82_DISCOVERY=PASS
echo [PASS] T-2A PHP 8.2 discovery passed.&exit /b 0
:run
>>"%LOG%" echo ============================================================
>>"%LOG%" echo [RUN] %~1
cmd.exe /D /S /C "%~2" >>"%LOG%" 2>&1
set "RC=%ERRORLEVEL%"
>>"%LOG%" echo [EXIT] %RC%
exit /b %RC%
:fail
>>"%LOG%" echo T2A_PHP82_DISCOVERY=FAIL
echo [FAIL] Inspect "%LOG%".&exit /b 1
