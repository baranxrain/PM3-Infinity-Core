@echo off
setlocal EnableExtensions
for %%I in ("%~dp0\..\..") do set "ROOT=%%~fI"
cd /d "%ROOT%" || exit /b 2
where git >nul 2>&1 || (echo [FAIL] Git was not found.& exit /b 1)
git rev-parse --is-inside-work-tree >nul 2>&1 || (echo [FAIL] Run inside the repository.& exit /b 1)
git rm --cached --ignore-unmatch -- tests/tools/phpunit-9.5.8.phar tests/tools/phpunit-10.phar || exit /b 1
powershell.exe -NoProfile -ExecutionPolicy Bypass -File ".\tests\tools\acquire-phpunit-all.ps1" || exit /b 1
echo [PASS] Tracked PHARs removed from the index; verified local copies retained as ignored runtime artifacts.
exit /b 0
