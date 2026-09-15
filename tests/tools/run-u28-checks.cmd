@echo off
setlocal EnableExtensions
chcp 65001 >nul

for %%I in ("%~dp0\..\..") do set "ROOT=%%~fI"
set "LOG=%ROOT%\U28-ACCEPTANCE.log"
set "PHPUNIT_PHAR=tests\tools\phpunit-9.5.8.phar"
set "COMPOSER_DISABLE_NETWORK=1"
set "COMPOSER_NO_INTERACTION=1"
cd /d "%ROOT%" || exit /b 2

> "%LOG%" echo ProcessMaker 3.8.3 Community - U-2.8 set_error_handler Migration Acceptance Offline
>>"%LOG%" echo Started: %DATE% %TIME%
>>"%LOG%" echo Root: %ROOT%
>>"%LOG%" echo Network policy: OFFLINE; bundled PHPUnit PHAR; package acquisition disabled.
>>"%LOG%" echo Expected PHPUnit PHAR SHA256: 11f27cf3f9522241fe234e9bf5813667207a074ac92089aac26d502ffc5e9517
>>"%LOG%" echo Expected PHPUnit result: OK (410 tests, assertions may vary)
>>"%LOG%" echo Scope: migrates the direct set_error_handler call in workflow\engine\classes\class.pmScript.php behind installTriggerErrorHandler().
>>"%LOG%" echo Out of scope: eval hardening, any schema or dependency change.
>>"%LOG%" echo.

echo U-2.8 offline checks started. Full output: "%LOG%"
call :run "Locate PHP" "where php"
if errorlevel 1 goto :failed
call :run "PHP version" "php --version"
if errorlevel 1 goto :failed
call :run "Capture the locale-dependent strftime oracle on this runtime" "php tests\tools\generate-strftime-locale-oracle.php"
if errorlevel 1 goto :failed
call :run "Combined U-1 through U-2.7 harness preflight" "php tests\tools\verify-u1.php"
if errorlevel 1 goto :failed
call :run "U-2.1 PHP 8 compatibility ledger preflight" "php tests\tools\verify-u2.php"
if errorlevel 1 goto :failed
call :run "U-2.2 legacy UTF-8 contract preflight" "php tests\tools\verify-u22.php"
if errorlevel 1 goto :failed
call :run "U-2.2.2 encode call-site migration preflight" "php tests\tools\verify-u222.php"
if errorlevel 1 goto :failed
call :run "U-2.2.3 decode call-site migration preflight" "php tests\tools\verify-u223.php"
if errorlevel 1 goto :failed
call :run "U-2.3.1 strftime inventory preflight" "php tests\tools\verify-u231.php"
if errorlevel 1 goto :failed
call :run "U-2.3.1 inventory drift check against the live tree" "php tests\tools\generate-strftime-inventory.php --check"
if errorlevel 1 goto :failed
call :run "U-2.3.2a LegacyStrftime helper contract preflight" "php tests\tools\verify-u232a.php"
if errorlevel 1 goto :failed
call :run "U-2.3.2 generated getter migration preflight" "php tests\tools\verify-u232.php"
if errorlevel 1 goto :failed
call :run "U-2.3.3 last executable utf8_encode migration preflight" "php tests\tools\verify-u233.php"
if errorlevel 1 goto :failed
call :run "U-6 PMScript generated string migration preflight" "php tests\tools\verify-u6.php"
if errorlevel 1 goto :failed
call :run "U-2.4.1 locale oracle capture preflight" "php tests\tools\verify-u241.php"
if errorlevel 1 goto :failed
call :run "U-2.4.2 locale-aware migration preflight" "php tests\tools\verify-u242.php"
if errorlevel 1 goto :failed
call :run "U-2.5 strptime-name migration preflight" "php tests\tools\verify-u25.php"
if errorlevel 1 goto :failed
call :run "U-2.6 mcrypt branch removal preflight" "php tests\tools\verify-u26.php"
if errorlevel 1 goto :failed
call :run "U-2.7 legacy string-sanitizer migration preflight" "php tests\tools\verify-u27.php"
if errorlevel 1 goto :failed
call :run "U-2.8 set_error_handler migration preflight" "php tests\tools\verify-u28.php"
if errorlevel 1 goto :failed
call :run "Locate Composer" "where composer"
if errorlevel 1 goto :failed
call :run "Composer version" "composer --version"
if errorlevel 1 goto :failed
call :run "Validate project Composer metadata offline" "composer validate --no-check-publish --no-interaction"
if errorlevel 1 goto :failed
call :run "Check production lock platform offline without installing packages" "composer check-platform-reqs --lock --no-dev"
if errorlevel 1 goto :failed
call :run "Record bundled PHPUnit version" "php %PHPUNIT_PHAR% --version"
if errorlevel 1 goto :failed
call :run "Recheck PHPUnit 9.5.8 PHAR integrity before suite" "php tests\tools\verify-u22.php --phar-only"
if errorlevel 1 goto :failed
call :run "Run DB-free combined U-1 through U-2.8 PHPUnit suite" "php %PHPUNIT_PHAR% --configuration phpunit.xml --testsuite unit --colors=never"
if errorlevel 1 goto :failed

>>"%LOG%" echo.
>>"%LOG%" echo U28_ACCEPTANCE=PASS
>>"%LOG%" echo Finished: %DATE% %TIME%
echo [PASS] U-2.8 offline acceptance completed.
echo Send back U28-ACCEPTANCE.log for evidence review.
exit /b 0

:run
set "STEP=%~1"
set "COMMAND=%~2"
echo [RUN] %STEP%
>>"%LOG%" echo ============================================================
>>"%LOG%" echo [RUN] %STEP%
>>"%LOG%" echo [CMD] %COMMAND%
cmd.exe /D /S /C "%COMMAND%" >>"%LOG%" 2>&1
set "RC=%ERRORLEVEL%"
>>"%LOG%" echo [EXIT] %RC%
if not "%RC%"=="0" (
    echo [FAIL] %STEP% ^(exit %RC%^)
    exit /b %RC%
)
echo [PASS] %STEP%
exit /b 0

:failed
set "FINAL_RC=%ERRORLEVEL%"
if "%FINAL_RC%"=="0" set "FINAL_RC=1"
>>"%LOG%" echo.
>>"%LOG%" echo U28_ACCEPTANCE=FAIL
>>"%LOG%" echo Failed: %DATE% %TIME%
echo U-2.8 offline acceptance failed. Inspect "%LOG%".
exit /b %FINAL_RC%
