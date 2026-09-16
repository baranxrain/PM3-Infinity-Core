@echo off
setlocal EnableExtensions
for %%I in ("%~dp0\..\..") do set "ROOT=%%~fI"
set "LOG=%ROOT%\T3A-PHPUNIT11-DISCOVERY.log"
cd /d "%ROOT%" || exit /b 2
>"%LOG%" echo T-3A pinned PHPUnit 11.5.49 discovery on PHP 8.2
call :run "PHPUnit 11 preflight" "php tests\tools\verify-t3a-phpunit11.php" || goto :fail
call :run "PHPUnit 11 suite" "php tests\tools\phpunit-11.phar -c phpunit-11.xml --testsuite unit --colors=never --do-not-cache-result --display-all-issues --fail-on-deprecation --fail-on-phpunit-deprecation" || goto :fail
findstr /C:"OK (604 tests, 9241 assertions)" "%LOG%" >nul || goto :fail
findstr /C:"Deprecations:" "%LOG%" >nul && goto :fail
>>"%LOG%" echo T3A_PHPUNIT11_DISCOVERY=PASS
echo [PASS] T-3A PHPUnit 11 discovery passed.&exit /b 0
:run
>>"%LOG%" echo ============================================================
>>"%LOG%" echo [RUN] %~1
cmd.exe /D /S /C "%~2" >>"%LOG%" 2>&1
set "RC=%ERRORLEVEL%"
>>"%LOG%" echo [EXIT] %RC%
exit /b %RC%
:fail
>>"%LOG%" echo T3A_PHPUNIT11_DISCOVERY=FAIL
echo [FAIL] Inspect "%LOG%".&exit /b 1
