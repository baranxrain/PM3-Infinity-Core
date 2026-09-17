[CmdletBinding()]
param([string]$Uri = 'https://phar.phpunit.de/phpunit-9.5.8.phar')
Set-StrictMode -Version 2.0
$ErrorActionPreference = 'Stop'
$expected = '11f27cf3f9522241fe234e9bf5813667207a074ac92089aac26d502ffc5e9517'
$dir = Split-Path -Parent $MyInvocation.MyCommand.Path
$target = Join-Path $dir 'phpunit-9.5.8.phar'
$tmp = Join-Path $env:TEMP ('phpunit9-' + [Guid]::NewGuid().ToString('N') + '.phar')
try {
    $php = Get-Command php.exe, php -ErrorAction Stop | Select-Object -First 1
    [Net.ServicePointManager]::SecurityProtocol = [Net.SecurityProtocolType]::Tls12
    Invoke-WebRequest -UseBasicParsing -Uri $Uri -OutFile $tmp
    $hash = (Get-FileHash $tmp -Algorithm SHA256).Hash.ToLowerInvariant()
    if ($hash -ne $expected) { throw "SHA-256 mismatch: $hash" }
    $version = (& $php.Source $tmp --version 2>&1 | Out-String).Trim()
    if ($version -notmatch '^PHPUnit 9\.5\.8\b') { throw "Version mismatch: $version" }
    Move-Item $tmp $target -Force
    Write-Host "PHPUNIT9_VERSION=$version"
    Write-Host "PHPUNIT9_SHA256=$hash"
    Write-Host 'T3B_PHPUNIT9_PINNED_ACQUISITION=PASS'
} finally {
    if (Test-Path $tmp) { Remove-Item $tmp -Force -ErrorAction SilentlyContinue }
}
