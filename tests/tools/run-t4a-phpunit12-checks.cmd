@echo off
setlocal EnableExtensions
for %%I in ("%~dp0\..\..") do set "ROOT=%%~fI"
set "LOG=%ROOT%\T4A-PHPUNIT12-DISCOVERY.log"
cd /d "%ROOT%" || exit /b 2
>"%LOG%" echo T-4A pinned PHPUnit 12.5.35 discovery on PHP 8.3
call :run "PHP 8.3 and PHPUnit 12 preflight" "php tests\tools\verify-t4a-phpunit12.php" || goto :fail
call :run "PHPUnit 12 on PHP 8.3" "php tests\tools\phpunit-12.phar -c phpunit-12.xml --testsuite unit --colors=never --do-not-cache-result --display-all-issues --fail-on-deprecation --fail-on-phpunit-deprecation" || goto :fail
findstr /C:"OK (604 tests, 9241 assertions)" "%LOG%" >nul || goto :fail
findstr /C:"Deprecations:" "%LOG%" >nul && goto :fail
>>"%LOG%" echo T4A_PHPUNIT12_DISCOVERY=PASS
echo [PASS] T-4A PHPUnit 12 discovery passed.&exit /b 0
:run
>>"%LOG%" echo ============================================================
>>"%LOG%" echo [RUN] %~1
cmd.exe /D /S /C "%~2" >>"%LOG%" 2>&1
set "RC=%ERRORLEVEL%"
>>"%LOG%" echo [EXIT] %RC%
exit /b %RC%
:fail
>>"%LOG%" echo T4A_PHPUNIT12_DISCOVERY=FAIL
echo [FAIL] Inspect "%LOG%".&exit /b 1
