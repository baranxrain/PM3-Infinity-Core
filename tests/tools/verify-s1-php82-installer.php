<?php
declare(strict_types=1);

$root = dirname(__DIR__, 2);
$errors = [];
$checks = 0;
$check = static function (bool $ok, string $message) use (&$errors, &$checks): void {
    echo ($ok ? '[PASS] ' : '[FAIL] ') . $message . PHP_EOL;
    if ($ok) { ++$checks; } else { $errors[] = $message; }
};
$read = static function (string $relative) use ($root, $check): string {
    $path = $root . '/' . $relative;
    $check(is_file($path), 'Required S-1 source: ' . $relative);
    return is_file($path) ? (string) file_get_contents($path) : '';
};

$check(PHP_SAPI === 'cli', 'CLI runtime');
$check(PHP_VERSION_ID >= 80200 && PHP_VERSION_ID < 80300, 'PHP 8.2.x runtime (' . PHP_VERSION . ')');
foreach (['json', 'tokenizer'] as $extension) {
    $check(extension_loaded($extension), 'Required extension: ' . $extension);
}

$installer = $read('workflow/engine/controllers/InstallerModule.php');
$po = $read('workflow/engine/content/translations/english/processmaker.en.po');
$sql = $read('workflow/engine/data/mysql/insert.sql');
$compiled = $read('workflow/engine/content/languages/translation.en');
$repair = $read('tests/tools/repair-laragon-php82-apache.ps1');

$minimum = null;
$exclusiveMaximum = null;
if (preg_match('/const PHP_VERSION_MINIMUM_SUPPORTED = "([^"]+)";/', $installer, $matches) === 1) {
    $minimum = $matches[1];
}
if (preg_match('/const PHP_VERSION_NOT_SUPPORTED = "([^"]+)";/', $installer, $matches) === 1) {
    $exclusiveMaximum = $matches[1];
}
$check($minimum === '7.4', 'Installer minimum PHP boundary is 7.4');
$check($exclusiveMaximum === '8.3', 'Installer exclusive PHP ceiling is 8.3');

$supports = static function (string $version) use ($minimum, $exclusiveMaximum): bool {
    return is_string($minimum) && is_string($exclusiveMaximum)
        && version_compare($version, $minimum, '>=')
        && version_compare($version, $exclusiveMaximum, '<');
};
$matrix = [
    '7.3.33' => false,
    '7.4.0' => true,
    '8.0.30' => true,
    '8.1.31' => true,
    '8.2.0' => true,
    '8.2.33' => true,
    '8.3.0' => false,
    '9.0.0' => false,
];
foreach ($matrix as $version => $expected) {
    $check($supports($version) === $expected, 'PHP support matrix: ' . $version . ' => ' . ($expected ? 'supported' : 'rejected'));
}

$check(str_contains($installer, "version_compare(\$phpVer, self::PHP_VERSION_MINIMUM_SUPPORTED, '>=')"), 'Runtime uses captured PHP version for minimum comparison');
$check(str_contains($installer, "version_compare(\$phpVer, self::PHP_VERSION_NOT_SUPPORTED, '<')"), 'Runtime uses captured PHP version for exclusive ceiling');
$check(!str_contains($installer, '$phpVerNum = (float)'), 'Lossy float PHP-version parsing removed');
$check(str_contains($installer, "function_exists('curl_version')"), 'cURL requirement remains runtime capability-based');
$check(str_contains($installer, "class_exists('SoapClient')"), 'SOAP requirement remains runtime capability-based');
$check(str_contains($installer, "function_exists('ldap_connect')"), 'LDAP requirement remains runtime capability-based');
$check(str_contains($repair, "Get-Process -Name httpd"), 'Laragon repair refuses to replace DLLs while Apache runs');
$check(str_contains($repair, "Get-FileHash -Algorithm SHA256"), 'Laragon repair records DLL SHA-256 values');
$check(str_contains($repair, "Copy-Item -LiteralPath \$targetDll -Destination \$backupDll"), 'Laragon repair backs up Apache nghttp2.dll');
$check(str_contains($repair, "Restore-S1State"), 'Laragon repair has rollback handling');
$check(str_contains($repair, "& \$httpdExe -t"), 'Laragon repair validates Apache configuration');

$oldLabel = 'PHP recommended version 8.1, we maintain compatibility starting with PHP 7.4';
$newLabel = 'PHP recommended version 8.2, we maintain compatibility starting with PHP 7.4';
$check(substr_count($po, $newLabel) === 2 && !str_contains($po, $oldLabel), 'English PO label targets PHP 8.2');
$check(substr_count($sql, $newLabel) === 1 && !str_contains($sql, $oldLabel), 'Fresh-install SQL label targets PHP 8.2');
$check(str_contains($compiled, $newLabel) && !str_contains($compiled, $oldLabel), 'Compiled English installer label targets PHP 8.2');

$iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root . '/tests', RecursiveDirectoryIterator::SKIP_DOTS));
$parsed = 0;
$parseFailures = [];
foreach ($iterator as $file) {
    if (!$file->isFile() || strtolower($file->getExtension()) !== 'php') { continue; }
    try {
        token_get_all((string) file_get_contents($file->getPathname()), TOKEN_PARSE);
        ++$parsed;
    } catch (ParseError $error) {
        $parseFailures[] = str_replace('\\', '/', $file->getPathname()) . ': ' . $error->getMessage();
    }
}
foreach ($parseFailures as $failure) { echo '[PARSE-FAIL] ' . $failure . PHP_EOL; }
$check($parseFailures === [] && $parsed === 99, 'All 99 test-harness PHP files parse on PHP 8.2');

echo '[SUMMARY] checks=' . $checks . ', failures=' . count($errors) . PHP_EOL;
echo $errors ? "S1_PREFLIGHT=FAIL\n" : "S1_PREFLIGHT=PASS\n";
exit($errors ? 1 : 0);
