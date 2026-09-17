#Requires -Version 5.1
[CmdletBinding()]
param()

$ErrorActionPreference = 'Stop'
Set-StrictMode -Version Latest

function Resolve-Executable([string] $Name) {
    $command = Get-Command $Name -ErrorAction Stop | Select-Object -First 1
    if (-not $command.Source) { throw "Unable to resolve $Name from PATH." }
    return $command.Source
}

function Restore-S1State {
    param(
        [string] $TargetDll,
        [string] $BackupDll,
        [string] $IniPath,
        [string] $BackupIni
    )
    if (Test-Path -LiteralPath $BackupDll) {
        Copy-Item -LiteralPath $BackupDll -Destination $TargetDll -Force
    }
    if (Test-Path -LiteralPath $BackupIni) {
        Copy-Item -LiteralPath $BackupIni -Destination $IniPath -Force
    }
}

$phpExe = Resolve-Executable 'php.exe'
$httpdExe = Resolve-Executable 'httpd.exe'
$phpDir = Split-Path -Parent $phpExe
$apacheBin = Split-Path -Parent $httpdExe
$iniPath = & $phpExe -r 'echo php_ini_loaded_file();'
$phpVersion = & $phpExe -r 'echo PHP_VERSION;'

if ($LASTEXITCODE -ne 0 -or -not $iniPath) { throw 'Unable to resolve the active php.ini.' }
if (-not $phpVersion.StartsWith('8.2.')) { throw "Expected PHP 8.2.x; resolved $phpVersion at $phpExe." }
if (Get-Process -Name httpd -ErrorAction SilentlyContinue) {
    throw 'Apache is running. Use Laragon Stop All, then rerun this script.'
}

$sourceDll = Join-Path $phpDir 'nghttp2.dll'
$targetDll = Join-Path $apacheBin 'nghttp2.dll'
foreach ($path in @($sourceDll, $targetDll, $iniPath)) {
    if (-not (Test-Path -LiteralPath $path -PathType Leaf)) { throw "Required file not found: $path" }
}
foreach ($extension in @('curl', 'soap', 'ldap', 'zip')) {
    $extensionDll = Join-Path (Join-Path $phpDir 'ext') ("php_{0}.dll" -f $extension)
    if (-not (Test-Path -LiteralPath $extensionDll -PathType Leaf)) {
        throw "Required PHP extension DLL not found: $extensionDll"
    }
}

$stamp = Get-Date -Format 'yyyyMMdd-HHmmss'
$backupDll = "$targetDll.s1-backup-$stamp"
$backupIni = "$iniPath.s1-backup-$stamp"
Copy-Item -LiteralPath $targetDll -Destination $backupDll -Force
Copy-Item -LiteralPath $iniPath -Destination $backupIni -Force

$sourceHash = (Get-FileHash -Algorithm SHA256 -LiteralPath $sourceDll).Hash.ToLowerInvariant()
$oldTargetHash = (Get-FileHash -Algorithm SHA256 -LiteralPath $targetDll).Hash.ToLowerInvariant()
Write-Host "PHP=$phpExe"
Write-Host "APACHE=$httpdExe"
Write-Host "PHP_VERSION=$phpVersion"
Write-Host "NGHTTP2_SOURCE_SHA256=$sourceHash"
Write-Host "NGHTTP2_APACHE_OLD_SHA256=$oldTargetHash"
Write-Host "NGHTTP2_BACKUP=$backupDll"
Write-Host "PHP_INI_BACKUP=$backupIni"

try {
    if ($sourceHash -ne $oldTargetHash) {
        Copy-Item -LiteralPath $sourceDll -Destination $targetDll -Force
    }

    $rawIni = [IO.File]::ReadAllText($iniPath)
    $newline = if ($rawIni.Contains("`r`n")) { "`r`n" } else { "`n" }
    $lines = [Collections.Generic.List[string]]::new()
    foreach ($line in ($rawIni -split "`r?`n", 0, 'RegexMatch')) { [void] $lines.Add($line) }

    foreach ($extension in @('curl', 'soap', 'ldap', 'zip')) {
        $pattern = '^\s*;?\s*extension\s*=\s*(?:php_)?' + [regex]::Escape($extension) + '(?:\.dll)?\s*$'
        $indexes = [Collections.Generic.List[int]]::new()
        for ($i = 0; $i -lt $lines.Count; ++$i) {
            if ($lines[$i] -match $pattern) { [void] $indexes.Add($i) }
        }
        if ($indexes.Count -eq 0) {
            [void] $lines.Add("extension=$extension")
        } else {
            $lines[$indexes[0]] = "extension=$extension"
            for ($j = 1; $j -lt $indexes.Count; ++$j) {
                $lines[$indexes[$j]] = '; S1 disabled duplicate: ' + $lines[$indexes[$j]]
            }
        }
    }

    $utf8NoBom = [Text.UTF8Encoding]::new($false)
    [IO.File]::WriteAllText($iniPath, ($lines -join $newline), $utf8NoBom)

    & $httpdExe -t
    if ($LASTEXITCODE -ne 0) { throw "Apache configuration test failed with exit code $LASTEXITCODE." }

    & $phpExe -r "foreach(['curl','soap','ldap','zip'] as `$e){echo `$e,'=',extension_loaded(`$e)?'ON':'OFF',PHP_EOL;if(!extension_loaded(`$e)){exit(20);}}"
    if ($LASTEXITCODE -ne 0) { throw "CLI extension verification failed with exit code $LASTEXITCODE." }

    $newTargetHash = (Get-FileHash -Algorithm SHA256 -LiteralPath $targetDll).Hash.ToLowerInvariant()
    if ($newTargetHash -ne $sourceHash) { throw 'Apache nghttp2.dll does not match the selected PHP 8.2 runtime.' }
    Write-Host "NGHTTP2_APACHE_NEW_SHA256=$newTargetHash"
    Write-Host 'S1_LARAGON_REPAIR=PASS'
    Write-Host 'Start Laragon, then verify the installer page through Apache.'
} catch {
    Restore-S1State -TargetDll $targetDll -BackupDll $backupDll -IniPath $iniPath -BackupIni $backupIni
    Write-Error ("S1 repair failed and backups were restored: " + $_.Exception.Message)
    exit 1
}
