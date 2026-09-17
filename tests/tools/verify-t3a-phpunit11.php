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
$check(PHP_VERSION_ID >= 80200 && PHP_VERSION_ID < 80300, 'PHP 8.2.x discovery runtime (' . PHP_VERSION . ')');
foreach (['dom','json','libxml','mbstring','phar','tokenizer','xml','xmlwriter'] as $extension) {
    $check(extension_loaded($extension), 'Required extension: ' . $extension);
}
$required = [
    'phpunit.xml', 'phpunit-10.xml', 'phpunit-11.xml', 'tests/bootstrap.php',
    'tests/tools/phpunit-9.5.8.phar', 'tests/tools/phpunit-10.phar',
    'tests/tools/phpunit-11.phar', 'tests/tools/phpunit-11.phar.sha256',
    'tests/tools/acquire-phpunit11.ps1', 'tests/tools/run-t2a-checks.cmd',
    'tests/tools/run-t3a-phpunit11-checks.cmd', 'tests/tools/run-t3a-checks.cmd',
    'tests/tools/verify-t3a-phpunit11.php'
];
foreach ($required as $file) { $check(is_file($root . '/' . $file), 'Required file: ' . $file); }
$expectedHash = 'b20ea78f38bc6abccc96ace605c471b1d11912ad6f0285c74415919050d234a6';
$phar = $root . '/tests/tools/phpunit-11.phar';
$check(is_file($phar) && hash_file('sha256', $phar) === $expectedHash, 'Pinned PHPUnit 11.5.49 SHA-256');
$manifest = is_file($root . '/tests/tools/phpunit-11.phar.sha256') ? trim((string) file_get_contents($root . '/tests/tools/phpunit-11.phar.sha256')) : '';
$check($manifest === $expectedHash . '  phpunit-11.phar', 'PHPUnit 11 checksum manifest');
if (is_file($phar)) {
    exec(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($phar) . ' --version 2>&1', $output, $exitCode);
    $version = trim(implode("\n", $output));
    echo '[INFO] ' . $version . PHP_EOL;
    $check($exitCode === 0 && preg_match('/^PHPUnit 11\.5\.49\b/', $version) === 1, 'Pinned PHPUnit 11.5.49 runtime');
} else {
    $check(false, 'Pinned PHPUnit 11.5.49 runtime');
}
$composer = json_decode((string) file_get_contents($root . '/composer.json'), true, 512, JSON_THROW_ON_ERROR);
$check(($composer['require']['php'] ?? null) === '>=7.4', 'Production PHP constraint remains >=7.4');
$check(($composer['require-dev']['phpunit/phpunit'] ?? null) === '9.5', 'Composer baseline remains PHPUnit 9.5');
$check(hash_file('sha256', $root . '/composer.lock') === '9f879af7b047666ee70708741d74521c91925e1b6addd80a9d465b6ea76e9cb3', 'Accepted PHP 8.2 composer.lock unchanged');
$xml = (string) file_get_contents($root . '/phpunit-11.xml');
$check(strpos($xml, 'https://schema.phpunit.de/11.5/phpunit.xsd') !== false, 'PHPUnit 11.5 schema');
$check(strpos($xml, 'bootstrap="tests/bootstrap.php"') !== false, 'Shared bootstrap retained');
$check(strpos($xml, 'failOnDeprecation="true"') !== false, 'PHP deprecations fail the PHPUnit 11 lane');
$check(strpos($xml, 'failOnPhpunitDeprecation="true"') !== false, 'PHPUnit deprecations fail the PHPUnit 11 lane');
$check(strpos($xml, '<directory suffix="Test.php">tests/unit</directory>') !== false, 'Unit suite scope retained');
$iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root . '/tests', RecursiveDirectoryIterator::SKIP_DOTS));
$parsed = 0; $parseFailures = [];
foreach ($iterator as $file) {
    if (!$file->isFile() || strtolower($file->getExtension()) !== 'php') { continue; }
    try { token_get_all((string) file_get_contents($file->getPathname()), TOKEN_PARSE); ++$parsed; }
    catch (ParseError $error) { $parseFailures[] = str_replace('\\', '/', $file->getPathname()) . ': ' . $error->getMessage(); }
}
foreach ($parseFailures as $failure) { echo '[PARSE-FAIL] ' . $failure . PHP_EOL; }
$check($parseFailures === [] && $parsed === 96, 'All 96 test-harness PHP files parse on PHP 8.2');
echo '[SUMMARY] checks=' . $checks . ', failures=' . count($errors) . PHP_EOL;
echo $errors ? "T3A_PREFLIGHT=FAIL\n" : "T3A_PREFLIGHT=PASS\n";
exit($errors ? 1 : 0);
