[CmdletBinding()]
param([string]$Uri = 'https://phar.phpunit.de/phpunit-12.5.35.phar')
Set-StrictMode -Version 2.0
$ErrorActionPreference = 'Stop'
$expected = '2c076d3d30f3bca762b13d996ad665d23220bc29afdb98a40387f7896b324195'
$dir = Split-Path -Parent $MyInvocation.MyCommand.Path
$target = Join-Path $dir 'phpunit-12.phar'
$tmp = Join-Path $env:TEMP ('phpunit12-' + [Guid]::NewGuid().ToString('N') + '.phar')
try {
    $php = Get-Command php.exe, php -ErrorAction Stop | Select-Object -First 1
    $runtime = (& $php.Source -r "echo PHP_VERSION_ID;" 2>&1 | Out-String).Trim()
    if ($runtime -notmatch '^\d+$' -or [int]$runtime -lt 80300 -or [int]$runtime -ge 80400) {
        throw "PHPUnit 12.5.35 acquisition requires PHP 8.3.x; active PHP_VERSION_ID=$runtime"
    }
    [Net.ServicePointManager]::SecurityProtocol = [Net.SecurityProtocolType]::Tls12
    Invoke-WebRequest -UseBasicParsing -Uri $Uri -OutFile $tmp
    $hash = (Get-FileHash $tmp -Algorithm SHA256).Hash.ToLowerInvariant()
    if ($hash -ne $expected) { throw "SHA-256 mismatch: $hash" }
    $version = (& $php.Source $tmp --version 2>&1 | Out-String).Trim()
    if ($LASTEXITCODE -ne 0 -or $version -notmatch '^PHPUnit 12\.5\.35\b') { throw "Version mismatch: $version" }
    Move-Item $tmp $target -Force
    Write-Host "PHPUNIT12_VERSION=$version"
    Write-Host "PHPUNIT12_SHA256=$hash"
    Write-Host 'T4A_PINNED_ACQUISITION=PASS'
} finally {
    if (Test-Path $tmp) { Remove-Item $tmp -Force -ErrorAction SilentlyContinue }
}
