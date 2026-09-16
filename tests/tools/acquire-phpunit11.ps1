[CmdletBinding()]
param([string]$Uri = 'https://phar.phpunit.de/phpunit-11.5.49.phar')
Set-StrictMode -Version 2.0
$ErrorActionPreference = 'Stop'
$expected = 'b20ea78f38bc6abccc96ace605c471b1d11912ad6f0285c74415919050d234a6'
$dir = Split-Path -Parent $MyInvocation.MyCommand.Path
$target = Join-Path $dir 'phpunit-11.phar'
$tmp = Join-Path $env:TEMP ('phpunit11-' + [Guid]::NewGuid().ToString('N') + '.phar')
try {
    $php = Get-Command php.exe, php -ErrorAction Stop | Select-Object -First 1
    [Net.ServicePointManager]::SecurityProtocol = [Net.SecurityProtocolType]::Tls12
    Invoke-WebRequest -UseBasicParsing -Uri $Uri -OutFile $tmp
    $hash = (Get-FileHash $tmp -Algorithm SHA256).Hash.ToLowerInvariant()
    if ($hash -ne $expected) { throw "SHA-256 mismatch: $hash" }
    $version = (& $php.Source $tmp --version 2>&1 | Out-String).Trim()
    if ($version -notmatch '^PHPUnit 11\.5\.49\b') { throw "Version mismatch: $version" }
    Move-Item $tmp $target -Force
    Write-Host "PHPUNIT11_VERSION=$version"
    Write-Host "PHPUNIT11_SHA256=$hash"
    Write-Host 'T3A_PINNED_ACQUISITION=PASS'
} finally {
    if (Test-Path $tmp) { Remove-Item $tmp -Force -ErrorAction SilentlyContinue }
}
