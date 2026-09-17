<?php
declare(strict_types=1);

$root = dirname(__DIR__, 2);
$errors = [];
$checks = 0;
$check = static function (bool $ok, string $message) use (&$errors, &$checks): void {
    echo ($ok ? '[PASS] ' : '[FAIL] ') . $message . PHP_EOL;
    if ($ok) { ++$checks; } else { $errors[] = $message; }
};

$check(PHP_SAPI === 'cli', 'CLI runtime');
$check(PHP_VERSION_ID >= 80200 && PHP_VERSION_ID < 80300, 'PHP 8.2.x runtime (' . PHP_VERSION . ')');
foreach (['json', 'tokenizer', 'zip'] as $extension) {
    $check(extension_loaded($extension), 'Required extension: ' . $extension);
}

$pointerPath = $root . '/build/releases/R2-LATEST.txt';
$pointer = [];
if (is_file($pointerPath)) {
    foreach (file($pointerPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
        $parts = explode('=', $line, 2);
        if (count($parts) === 2) { $pointer[$parts[0]] = $parts[1]; }
    }
}
$zipPath = $pointer['zip'] ?? '';
$shaPath = $pointer['sha256'] ?? '';
$expectedCommit = $pointer['commit'] ?? '';
$check(is_file($pointerPath), 'R-2 latest-artifact pointer');
$check($zipPath !== '' && is_file($zipPath), 'Release ZIP exists');
$check($shaPath !== '' && is_file($shaPath), 'Release SHA-256 manifest exists');
$check(preg_match('/^[0-9a-f]{40}$/', $expectedCommit) === 1, 'Pointer contains full source commit');

$currentCommit = trim((string) shell_exec('git -C ' . escapeshellarg($root) . ' rev-parse HEAD 2>NUL'));
$check($currentCommit !== '' && hash_equals($currentCommit, $expectedCommit), 'Release source commit matches HEAD');

$actualHash = is_file($zipPath) ? hash_file('sha256', $zipPath) : '';
$manifestLine = is_file($shaPath) ? trim((string) file_get_contents($shaPath)) : '';
$expectedLine = $actualHash . '  ' . basename($zipPath);
$check($actualHash !== '' && strlen($actualHash) === 64, 'Release ZIP SHA-256 computed');
$check($manifestLine !== '' && hash_equals($expectedLine, $manifestLine), 'External SHA-256 manifest matches ZIP');
$check(is_file($zipPath) && filesize($zipPath) > 1024 * 1024, 'Release ZIP has plausible size');

$zip = new ZipArchive();
$opened = $zipPath !== '' ? $zip->open($zipPath) : false;
$check($opened === true, 'Release ZIP opens successfully');
$names = [];
$lowerNames = [];
$top = null;
$unsafe = [];
$forbidden = [];
if ($opened === true) {
    for ($i = 0; $i < $zip->numFiles; ++$i) {
        $rawName = $zip->getNameIndex($i);
        if (!is_string($rawName)) { $unsafe[] = '<unreadable>'; continue; }
        $name = str_replace('\\', '/', $rawName);
        $names[$name] = true;
        $lower = strtolower($name);
        if (isset($lowerNames[$lower])) { $unsafe[] = 'case-collision:' . $name; }
        $lowerNames[$lower] = true;
        if (str_contains($name, "\0") || str_starts_with($name, '/') || preg_match('#^[A-Za-z]:/#', $name) || preg_match('#(^|/)\.\.(/|$)#', $name)) {
            $unsafe[] = $name;
        }
        $parts = explode('/', trim($name, '/'));
        if (($parts[0] ?? '') !== '') { $top ??= $parts[0]; if ($top !== $parts[0]) { $unsafe[] = 'multiple-roots:' . $name; } }
        $base = basename($name);
        if (preg_match('#(^|/)tests(/|$)#i', $name)
            || preg_match('/^phpunit(?:-[^.]*)?\.xml$/i', $base)
            || str_ends_with(strtolower($base), '.phar')
            || preg_match('/(?:-acceptance|-discovery)\.log$/i', $base)
            || preg_match('#(^|/)\.git(?:hub|ignore|attributes)?(/|$)#i', $name)
            || preg_match('#(^|/)\.circleci(/|$)#i', $name)
            || $base === '.editorconfig') {
            $forbidden[] = $name;
        }
    }
}
$check($opened === true && $zip->numFiles > 1000, 'Release ZIP contains a plausible production tree');
$check($top !== null && preg_match('/^PM3-Infinity-Core-3\.8\.3-PHP82-[0-9a-f]{7,12}$/', $top) === 1, 'Single versioned top-level release directory');
$check($unsafe === [], 'Archive paths are traversal-safe and collision-free');
if ($forbidden !== []) {
    foreach (array_slice($forbidden, 0, 25) as $forbiddenPath) {
        echo '[DETAIL] Forbidden archive entry: ' . $forbiddenPath . PHP_EOL;
    }
    if (count($forbidden) > 25) {
        echo '[DETAIL] Additional forbidden archive entries: ' . (count($forbidden) - 25) . PHP_EOL;
    }
}
$check($forbidden === [], 'Tests, PHPUnit configs, PHARs, logs and CI/VCS metadata are excluded');

$required = [
    'composer.json', 'composer.lock', 'processmaker', 'vendor/autoload.php',
    'workflow/engine/config/paths.php', 'PM3-INFINITY-RELEASE-MANIFEST.txt'
];
foreach ($required as $relative) {
    $check($top !== null && isset($names[$top . '/' . $relative]), 'Required release file: ' . $relative);
}
$releaseManifest = ($opened === true && $top !== null) ? $zip->getFromName($top . '/PM3-INFINITY-RELEASE-MANIFEST.txt') : false;
$check(is_string($releaseManifest) && str_contains($releaseManifest, 'Release-Target: PHP 8.2'), 'Embedded manifest targets PHP 8.2');
$check(is_string($releaseManifest) && str_contains($releaseManifest, 'Source-Commit: ' . $expectedCommit), 'Embedded manifest records source commit');
$check(is_string($releaseManifest) && str_contains($releaseManifest, 'Tests-Excluded: yes') && str_contains($releaseManifest, 'PHPUnit-Configs-Excluded: yes') && str_contains($releaseManifest, 'PHAR-Binaries-Excluded: yes'), 'Embedded manifest records release exclusions');
$lock = ($opened === true && $top !== null) ? $zip->getFromName($top . '/composer.lock') : false;
$check(is_string($lock) && hash('sha256', $lock) === '9f879af7b047666ee70708741d74521c91925e1b6addd80a9d465b6ea76e9cb3', 'Accepted PHP 8.2 composer.lock is packaged unchanged');
if ($opened === true) { $zip->close(); }

$iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root . '/tests', RecursiveDirectoryIterator::SKIP_DOTS));
$parsed = 0; $parseFailures = [];
foreach ($iterator as $file) {
    if (!$file->isFile() || strtolower($file->getExtension()) !== 'php') { continue; }
    try { token_get_all((string) file_get_contents($file->getPathname()), TOKEN_PARSE); ++$parsed; }
    catch (ParseError $error) { $parseFailures[] = str_replace('\\', '/', $file->getPathname()) . ': ' . $error->getMessage(); }
}
foreach ($parseFailures as $failure) { echo '[PARSE-FAIL] ' . $failure . PHP_EOL; }
$check($parseFailures === [] && $parsed === 95, 'All 95 test-harness PHP files parse on PHP 8.2');

echo '[SUMMARY] checks=' . $checks . ', failures=' . count($errors) . PHP_EOL;
echo $errors ? "R2_PREFLIGHT=FAIL\n" : "R2_PREFLIGHT=PASS\n";
exit($errors ? 1 : 0);
