@echo off
setlocal EnableExtensions EnableDelayedExpansion
for %%I in ("%~dp0\..\..") do set "ROOT=%%~fI"
set "HARNESS=%ROOT%\tests\browser\u319-client-runtime.html"
set "OUT=%TEMP%\u319-browser-%RANDOM%-%RANDOM%.txt"
set "PROFILE=%TEMP%\u319-browser-profile-%RANDOM%-%RANDOM%"
set "BROWSER="
if exist "%HARNESS%" goto :find_browser
echo [FAIL] Missing browser harness: %HARNESS%
exit /b 1
:find_browser
for %%B in (msedge.exe chrome.exe chromium.exe chromium) do if not defined BROWSER for /f "delims=" %%P in ('where %%B 2^>nul') do if not defined BROWSER set "BROWSER=%%P"
if not defined BROWSER if exist "%ProgramFiles(x86)%\Microsoft\Edge\Application\msedge.exe" set "BROWSER=%ProgramFiles(x86)%\Microsoft\Edge\Application\msedge.exe"
if not defined BROWSER if exist "%ProgramFiles%\Microsoft\Edge\Application\msedge.exe" set "BROWSER=%ProgramFiles%\Microsoft\Edge\Application\msedge.exe"
if not defined BROWSER if exist "%ProgramFiles%\Google\Chrome\Application\chrome.exe" set "BROWSER=%ProgramFiles%\Google\Chrome\Application\chrome.exe"
if not defined BROWSER if exist "%LocalAppData%\Google\Chrome\Application\chrome.exe" set "BROWSER=%LocalAppData%\Google\Chrome\Application\chrome.exe"
if defined BROWSER goto :browser_found
echo [FAIL] Chrome, Edge or Chromium was not found.
exit /b 1
:browser_found
set "URL=file:///%HARNESS:\=/%"
echo [INFO] Browser: %BROWSER%
"%BROWSER%" --headless=new --disable-gpu --disable-extensions --no-first-run --no-default-browser-check --user-data-dir="%PROFILE%" --dump-dom "%URL%" >"%OUT%" 2>&1
if errorlevel 1 "%BROWSER%" --headless --disable-gpu --disable-extensions --no-first-run --no-default-browser-check --user-data-dir="%PROFILE%" --dump-dom "%URL%" >"%OUT%" 2>&1
type "%OUT%"
findstr /C:"Browser tests: 16, Passed: 16, Failures: 0" "%OUT%" >nul || goto :failed
findstr /C:"U319_BROWSER_ACCEPTANCE=PASS" "%OUT%" >nul || goto :failed
del /q "%OUT%" >nul 2>&1
rmdir /s /q "%PROFILE%" >nul 2>&1
echo [PASS] U-3.19 browser runtime checks passed.
exit /b 0
:failed
echo [FAIL] U-3.19 browser runtime checks failed.
del /q "%OUT%" >nul 2>&1
rmdir /s /q "%PROFILE%" >nul 2>&1
exit /b 1
