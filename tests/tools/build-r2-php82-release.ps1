param(
    [string]$OutputDirectory = ""
)

$ErrorActionPreference = 'Stop'
Set-StrictMode -Version Latest

$Root = [System.IO.Path]::GetFullPath((Join-Path $PSScriptRoot '..\..'))
if ([string]::IsNullOrWhiteSpace($OutputDirectory)) {
    $OutputDirectory = Join-Path $Root 'build\releases'
} elseif (-not [System.IO.Path]::IsPathRooted($OutputDirectory)) {
    $OutputDirectory = Join-Path $Root $OutputDirectory
}
$OutputDirectory = [System.IO.Path]::GetFullPath($OutputDirectory)

function Invoke-Git {
    param([Parameter(ValueFromRemainingArguments = $true)][string[]]$Arguments)
    $output = & git -C $Root @Arguments 2>&1
    if ($LASTEXITCODE -ne 0) { throw "git $($Arguments -join ' ') failed: $($output -join [Environment]::NewLine)" }
    return $output
}

$null = Invoke-Git rev-parse --is-inside-work-tree
$Commit = ((Invoke-Git rev-parse HEAD) -join '').Trim()
$ShortCommit = ((Invoke-Git rev-parse --short=9 HEAD) -join '').Trim()
if ($Commit -notmatch '^[0-9a-f]{40}$' -or $ShortCommit -notmatch '^[0-9a-f]{7,12}$') {
    throw 'Could not resolve a valid source commit.'
}

# The release is always built from committed HEAD. Refuse dirty production files;
# uncommitted R-2 tooling under tests/tools and .gitignore are allowed during acceptance.
$status = @(Invoke-Git status --porcelain=v1 --untracked-files=all)
foreach ($line in $status) {
    if ([string]::IsNullOrWhiteSpace($line)) { continue }
    $path = $line.Substring(3).Trim('"') -replace '\\','/'
    if ($path -match ' -> ') { $path = ($path -split ' -> ')[-1].Trim('"') }
    $allowed = $path -eq '.gitignore' -or $path -like 'tests/*' -or $path -like 'build/*' -or $path -like '*-ACCEPTANCE.log' -or $path -like '*-DISCOVERY.log'
    if (-not $allowed) { throw "Dirty production path blocks release: $path" }
}

New-Item -ItemType Directory -Force -Path $OutputDirectory | Out-Null
$PackageName = "PM3-Infinity-Core-3.8.3-PHP82-$ShortCommit"
$FinalZip = Join-Path $OutputDirectory ($PackageName + '.zip')
$ShaFile = $FinalZip + '.sha256'
$PointerFile = Join-Path $OutputDirectory 'R2-LATEST.txt'
$TempRoot = Join-Path ([System.IO.Path]::GetTempPath()) ('pm3-r2-' + [Guid]::NewGuid().ToString('N'))
$RawArchive = Join-Path $TempRoot 'source.tar'
$StageParent = Join-Path $TempRoot 'stage'
$StageRoot = Join-Path $StageParent $PackageName
$TarExe = Join-Path $env:SystemRoot 'System32\tar.exe'
if (-not (Test-Path -LiteralPath $TarExe -PathType Leaf)) {
    throw "Windows inbox tar.exe was not found at $TarExe"
}

try {
    New-Item -ItemType Directory -Force -Path $StageRoot | Out-Null
    $null = Invoke-Git archive --format=tar --prefix="$PackageName/" --output=$RawArchive HEAD
    & $TarExe -xf $RawArchive -C $StageParent
    if ($LASTEXITCODE -ne 0) { throw "tar extraction failed with exit code $LASTEXITCODE" }

    $removePaths = @(
        (Join-Path $StageRoot 'tests'),
        (Join-Path $StageRoot '.github'),
        (Join-Path $StageRoot '.circleci'),
        (Join-Path $StageRoot '.gitignore'),
        (Join-Path $StageRoot '.gitattributes'),
        (Join-Path $StageRoot '.editorconfig')
    )
    foreach ($path in $removePaths) { if (Test-Path -LiteralPath $path) { Remove-Item -LiteralPath $path -Recurse -Force } }

    Get-ChildItem -LiteralPath $StageRoot -Recurse -File | Where-Object {
        $_.Name -match '^phpunit(?:-[^.]*)?\.xml$' -or
        $_.Extension -ieq '.phar' -or
        $_.Name -match '(?:-ACCEPTANCE|-DISCOVERY)\.log$'
    } | Remove-Item -Force

    $ManifestPath = Join-Path $StageRoot 'PM3-INFINITY-RELEASE-MANIFEST.txt'
    $Manifest = @(
        'Product: PM3 Infinity Core',
        'Upstream-Version: 3.8.3',
        'Release-Target: PHP 8.2',
        "Source-Commit: $Commit",
        'Source-Mode: committed HEAD via git archive',
        'Tests-Excluded: yes',
        'PHPUnit-Configs-Excluded: yes',
        'PHAR-Binaries-Excluded: yes',
        'VCS-Metadata-Excluded: yes'
    ) -join "`r`n"
    [System.IO.File]::WriteAllText($ManifestPath, $Manifest + "`r`n", [System.Text.UTF8Encoding]::new($false))

    if (Test-Path -LiteralPath $FinalZip) { Remove-Item -LiteralPath $FinalZip -Force }
    $ArchiveExclusions = @(
        '--exclude=*/tests',
        '--exclude=*/tests/*',
        '--exclude=*/Tests',
        '--exclude=*/Tests/*',
        '--exclude=*/phpunit.xml',
        '--exclude=*/phpunit-*.xml',
        '--exclude=*.phar',
        '--exclude=*-ACCEPTANCE.log',
        '--exclude=*-DISCOVERY.log',
        '--exclude=*/.git',
        '--exclude=*/.git/*',
        '--exclude=*/.github',
        '--exclude=*/.github/*',
        '--exclude=*/.circleci',
        '--exclude=*/.circleci/*',
        '--exclude=*/.gitignore',
        '--exclude=*/.gitattributes',
        '--exclude=*/.editorconfig'
    )
    & $TarExe -a -c -f $FinalZip @ArchiveExclusions -C $StageParent $PackageName
    if ($LASTEXITCODE -ne 0) { throw "ZIP creation with tar failed with exit code $LASTEXITCODE" }
    $Hash = (Get-FileHash -LiteralPath $FinalZip -Algorithm SHA256).Hash.ToLowerInvariant()
    [System.IO.File]::WriteAllText($ShaFile, "$Hash  $([System.IO.Path]::GetFileName($FinalZip))`r`n", [System.Text.UTF8Encoding]::new($false))
    $Pointer = @("zip=$FinalZip", "sha256=$ShaFile", "commit=$Commit") -join "`r`n"
    [System.IO.File]::WriteAllText($PointerFile, $Pointer + "`r`n", [System.Text.UTF8Encoding]::new($false))

    Write-Host "R2_RELEASE_ZIP=$FinalZip"
    Write-Host "R2_RELEASE_SHA256_FILE=$ShaFile"
    Write-Host "R2_RELEASE_SHA256=$Hash"
    Write-Host "R2_SOURCE_COMMIT=$Commit"
    Write-Host 'R2_BUILD=PASS'
} finally {
    if (Test-Path -LiteralPath $TempRoot) {
        try {
            $LongTempRoot = if ($TempRoot.StartsWith('\\?\')) { $TempRoot } else { '\\?\' + $TempRoot }
            [System.IO.Directory]::Delete($LongTempRoot, $true)
        } catch {
            Write-Warning "Temporary release workspace cleanup was incomplete: $TempRoot"
        }
    }
}
