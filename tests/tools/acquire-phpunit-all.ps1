[CmdletBinding()]
param()
Set-StrictMode -Version 2.0
$ErrorActionPreference = 'Stop'
$dir = Split-Path -Parent $MyInvocation.MyCommand.Path
$scripts = @('acquire-phpunit9.ps1', 'acquire-phpunit10.ps1', 'acquire-phpunit11.ps1')
foreach ($script in $scripts) {
    $path = Join-Path $dir $script
    if (-not (Test-Path -LiteralPath $path -PathType Leaf)) { throw "Missing acquisition script: $script" }
    & $path
    if ($LASTEXITCODE -ne 0) { throw "Acquisition failed: $script (exit $LASTEXITCODE)" }
}
Write-Host 'T3B_ALL_PINNED_ACQUISITIONS=PASS'
