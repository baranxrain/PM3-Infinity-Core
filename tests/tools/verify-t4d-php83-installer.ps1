[CmdletBinding()]
param()
Set-StrictMode -Version 2.0
$ErrorActionPreference = 'Stop'
$root = [IO.Path]::GetFullPath((Join-Path $PSScriptRoot '..\..'))
$failures = New-Object System.Collections.Generic.List[string]
$checks = 0
function Check([bool]$ok, [string]$message) {
    if ($ok) { $script:checks++; Write-Host "[PASS] $message" }
    else { $script:failures.Add($message); Write-Host "[FAIL] $message" }
}
function Read-ProjectFile([string]$relative) {
    $path = Join-Path $root $relative
    Check (Test-Path -LiteralPath $path -PathType Leaf) "Required T-4D file: $relative"
    if (Test-Path -LiteralPath $path -PathType Leaf) { return [IO.File]::ReadAllText($path) }
    return ''
}
function Check-Hash([string]$relative, [string]$expected) {
    $path = Join-Path $root $relative
    $actual = if (Test-Path -LiteralPath $path -PathType Leaf) { (Get-FileHash -LiteralPath $path -Algorithm SHA256).Hash.ToLowerInvariant() } else { '' }
    Check ($actual -eq $expected) "Exact T-4D SHA-256: $relative"
}
$php = Get-Command php.exe, php -ErrorAction Stop | Select-Object -First 1
$runtime = (& $php.Source -r 'echo PHP_VERSION_ID;' 2>&1 | Out-String).Trim()
Check ($runtime -match '^\d+$' -and [int]$runtime -ge 80300 -and [int]$runtime -lt 80400) "PHP 8.3.x installer verification runtime ($runtime)"
$installer = Read-ProjectFile 'workflow\engine\controllers\InstallerModule.php'
$po = Read-ProjectFile 'workflow\engine\content\translations\english\processmaker.en.po'
$sql = Read-ProjectFile 'workflow\engine\data\mysql\insert.sql'
$compiled = Read-ProjectFile 'workflow\engine\content\languages\translation.en'
$runner = Read-ProjectFile 'tests\tools\run-t4d-checks.cmd'
$historicalS1 = Read-ProjectFile 'tests\tools\verify-s1-php82-installer.php'
$historicalR2 = Read-ProjectFile 'tests\tools\verify-r2-php82-release.php'
Check-Hash 'workflow\engine\controllers\InstallerModule.php' 'db47583a63120428339be0b121642c84788f3123575ab0b04b207f86370207ec'
Check-Hash 'workflow\engine\content\translations\english\processmaker.en.po' '4b26d806a37d6481fa9a0b2d93094b3d420a2b47433c0715a106556ffdb62e06'
Check-Hash 'workflow\engine\data\mysql\insert.sql' '8fbb22015fcfeca75276f14fd0e1a3f17358a72866264c3818c326e01df99477'
Check-Hash 'workflow\engine\content\languages\translation.en' '83da9ec5be5a3c698b340172d096df84bf2b6718cec571367b96c684975490e5'
Check ($installer.Contains('const PHP_VERSION_MINIMUM_SUPPORTED = "7.4";')) 'Installer minimum PHP boundary remains 7.4'
Check ($installer.Contains('const PHP_VERSION_NOT_SUPPORTED = "8.4";')) 'Installer exclusive PHP ceiling is 8.4'
Check ($installer.Contains("version_compare(`$phpVer, self::PHP_VERSION_MINIMUM_SUPPORTED, '>=')")) 'Installer retains inclusive minimum comparison'
Check ($installer.Contains("version_compare(`$phpVer, self::PHP_VERSION_NOT_SUPPORTED, '<')")) 'Installer retains exclusive ceiling comparison'
Check (-not $installer.Contains('$phpVerNum = (float)')) 'Installer avoids lossy float version parsing'
Check ($installer.Contains("function_exists('curl_version')")) 'cURL requirement remains capability-based'
Check ($installer.Contains("class_exists('SoapClient')")) 'SOAP requirement remains capability-based'
Check ($installer.Contains("function_exists('ldap_connect')")) 'LDAP requirement remains capability-based'
$matrix = [ordered]@{'7.3.33'=$false;'7.4.0'=$true;'8.0.30'=$true;'8.1.31'=$true;'8.2.33'=$true;'8.3.0'=$true;'8.3.33'=$true;'8.4.0'=$false;'9.0.0'=$false}
foreach ($entry in $matrix.GetEnumerator()) {
    $version = $entry.Key
    $code = "echo (version_compare('$version', '7.4', '>=') && version_compare('$version', '8.4', '<')) ? '1' : '0';"
    $actual = (& $php.Source -r $code 2>&1 | Out-String).Trim()
    Check ($actual -eq $(if ($entry.Value) {'1'} else {'0'})) "PHP installer matrix: $version => $(if ($entry.Value) {'supported'} else {'rejected'})"
}
$oldLabel = 'PHP recommended version 8.2, we maintain compatibility starting with PHP 7.4'
$newLabel = 'PHP recommended version 8.3, we maintain compatibility starting with PHP 7.4'
Check (([regex]::Matches($po, [regex]::Escape($newLabel))).Count -eq 2 -and -not $po.Contains($oldLabel)) 'English PO label targets PHP 8.3'
Check (([regex]::Matches($sql, [regex]::Escape($newLabel))).Count -eq 1 -and -not $sql.Contains($oldLabel)) 'Fresh-install SQL label targets PHP 8.3'
Check (([regex]::Matches($compiled, [regex]::Escape($newLabel))).Count -eq 1 -and -not $compiled.Contains($oldLabel)) 'Compiled English installer label targets PHP 8.3'
Check ($runner.Contains('call "%ROOT%\tests\tools\run-t4c-checks.cmd"')) 'T-4D runner preserves the complete T-4C chain'
Check ($historicalS1.Contains("`$check(`$exclusiveMaximum === '8.3', 'Installer exclusive PHP ceiling is 8.3');")) 'Historical S-1 PHP 8.2 ceiling record remains unchanged'
Check ($historicalR2.Contains('9f879af7b047666ee70708741d74521c91925e1b6addd80a9d465b6ea76e9cb3')) 'Historical R-2 PHP 8.2 lock record remains unchanged'
$ignore = Read-ProjectFile '.gitignore'
Check ($ignore.Contains('/T4D-ACCEPTANCE.log')) 'T-4D acceptance log is ignored'
Write-Host "[SUMMARY] checks=$checks, failures=$($failures.Count)"
if ($failures.Count -eq 0) { Write-Host 'T4D_PREFLIGHT=PASS'; exit 0 }
Write-Host 'T4D_PREFLIGHT=FAIL'; exit 1
