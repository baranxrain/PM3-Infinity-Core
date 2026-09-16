@echo off
setlocal EnableExtensions
chcp 65001 >nul
for %%I in ("%~dp0\..\..") do set "ROOT=%%~fI"
set "LOG=%ROOT%\T1A-PHPUNIT10-DISCOVERY.log"
cd /d "%ROOT%" || exit /b 2
>"%LOG%" echo T-1A PHPUnit 10 discovery - PHP 8.1 - expected 604 tests
call :run "PHP version" "php --version" || goto :failed
call :run "T-1A preflight" "php tests\tools\verify-t1a-phpunit10.php" || goto :failed
call :run "PHPUnit 10 version" "php tests\tools\phpunit-10.phar --version" || goto :failed
call :run "PHPUnit 10 suite" "php tests\tools\phpunit-10.phar -c phpunit-10.xml --testsuite unit --colors=never --do-not-cache-result --display-errors --display-warnings --display-deprecations --display-phpunit-deprecations" || goto :failed
findstr /C:"OK (604 tests," "%LOG%" >nul || goto :failed
>>"%LOG%" echo T1A_PHPUNIT10_DISCOVERY=PASS
echo [PASS] T-1A focused discovery passed.
exit /b 0
:run
set "STEP=%~1"&set "COMMAND=%~2"
>>"%LOG%" echo ============================================================
>>"%LOG%" echo [RUN] %STEP%
cmd.exe /D /S /C "%COMMAND%" >>"%LOG%" 2>&1
set "RC=%ERRORLEVEL%"&>>"%LOG%" echo [EXIT] %RC%
exit /b %RC%
:failed
>>"%LOG%" echo T1A_PHPUNIT10_DISCOVERY=FAIL
echo [FAIL] Inspect "%LOG%".
exit /b 1
