<?php
declare(strict_types=1);
$root = dirname(__DIR__, 2); $errors = []; $checks = 0;
$check = static function (bool $ok, string $message) use (&$errors, &$checks): void { echo ($ok ? '[PASS] ' : '[FAIL] ') . $message . PHP_EOL; if ($ok) { ++$checks; } else { $errors[] = $message; } };
$check(PHP_SAPI === 'cli', 'CLI runtime');
$check(PHP_VERSION_ID >= 80200 && PHP_VERSION_ID < 80300, 'PHP 8.2.x discovery runtime (' . PHP_VERSION . ')');
foreach (['dom','json','libxml','mbstring','phar','tokenizer','xml','xmlwriter'] as $extension) { $check(extension_loaded($extension), 'Required extension: ' . $extension); }
$required = ['phpunit.xml','phpunit-10.xml','tests/bootstrap.php','tests/tools/run-u319-checks.cmd','tests/tools/phpunit-9.5.8.phar','tests/tools/phpunit-10.phar','tests/tools/phpunit-10.phar.sha256','tests/Support/LegacyUtf8Oracle.php','tests/tools/verify-t2a-php82.php','tests/tools/run-t2a-php82-checks.cmd','tests/tools/run-t2a-checks.cmd'];
foreach ($required as $file) { $check(is_file($root . '/' . $file), 'Required file: ' . $file); }
$check(hash_file('sha256', $root . '/tests/tools/phpunit-9.5.8.phar') === '11f27cf3f9522241fe234e9bf5813667207a074ac92089aac26d502ffc5e9517', 'Pinned PHPUnit 9.5.8 SHA-256');
$check(hash_file('sha256', $root . '/tests/tools/phpunit-10.phar') === 'a823d916151f628dd9943ccc81a98bcfbba9c5babf53f27be6c7dccc89f8ee23', 'Pinned PHPUnit 10.5.64 SHA-256');
exec(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($root . '/tests/tools/phpunit-10.phar') . ' --version 2>&1', $versionOutput, $versionExit); $version = trim(implode("\n", $versionOutput)); echo '[INFO] ' . $version . PHP_EOL;
$check($versionExit === 0 && preg_match('/^PHPUnit 10\.5\.64\b/', $version) === 1, 'Pinned PHPUnit 10.5.64 runtime');
$composer = json_decode((string) file_get_contents($root . '/composer.json'), true);
$check(($composer['require']['php'] ?? null) === '>=7.4', 'Composer PHP constraint remains >=7.4');
$check(($composer['require-dev']['phpunit/phpunit'] ?? null) === '9.5', 'Composer development baseline remains PHPUnit 9.5');
$check(hash_file('sha256', $root . '/composer.lock') === '9f879af7b047666ee70708741d74521c91925e1b6addd80a9d465b6ea76e9cb3', 'Accepted T-2B rev E Composer lock SHA-256');
$lock = json_decode((string) file_get_contents($root . '/composer.lock'), true, 512, JSON_THROW_ON_ERROR);
$lockedVersions = [];
foreach (array_merge($lock['packages'] ?? [], $lock['packages-dev'] ?? []) as $package) { $lockedVersions[$package['name']] = $package['version']; }
$check(($lockedVersions['nette/schema'] ?? null) === 'v1.2.5', 'nette/schema is pinned to v1.2.5');
$check(($lockedVersions['nette/utils'] ?? null) === 'v3.2.8', 'nette/utils remains pinned to v3.2.8');
$check(($lockedVersions['phpspec/prophecy'] ?? null) === 'v1.16.0', 'phpspec/prophecy is pinned to v1.16.0');
$xml = (string) file_get_contents($root . '/phpunit-10.xml');
$check(strpos($xml, 'failOnDeprecation="true"') !== false, 'PHPUnit 10 fails on PHP deprecations');
$check(strpos($xml, 'failOnPhpunitDeprecation="true"') !== false, 'PHPUnit 10 fails on PHPUnit deprecations');
$iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root . '/tests', RecursiveDirectoryIterator::SKIP_DOTS)); $parsed = 0; $parseFailures = [];
foreach ($iterator as $file) { if (!$file->isFile() || strtolower($file->getExtension()) !== 'php') { continue; } try { token_get_all((string) file_get_contents($file->getPathname()), TOKEN_PARSE); ++$parsed; } catch (ParseError $error) { $parseFailures[] = str_replace('\\','/',$file->getPathname()) . ': ' . $error->getMessage(); } }
foreach ($parseFailures as $failure) { echo '[PARSE-FAIL] ' . $failure . PHP_EOL; }
$check($parseFailures === [] && $parsed === 95, 'All 95 test-harness PHP files parse on PHP 8.2');
$template = (string) file_get_contents($root . '/gulliver/system/class.templatePower.php');
$check(strpos($template, '$this->{$tplvar}') === false && strpos($template, 'public $tpl_rawContent = [];') !== false, 'TemplatePower dynamic storage replaced by declared array');
$testFiles = ['LegacyUtf8ContractTest.php','LegacyUtf8CallSiteMigrationTest.php','LegacyUtf8DecodeCallSiteMigrationTest.php','LegacyUtf8StrftimeCouplingTest.php','PmScriptGeneratedStringMigrationTest.php','StrftimeLocaleOracleTest.php'];
$nativeCalls = 0; foreach ($testFiles as $name) { $nativeCalls += preg_match_all('/(?<![A-Za-z0-9_])utf8_(?:encode|decode)\s*\(/', (string) file_get_contents($root . '/tests/unit/Compatibility/' . $name)); }
$check($nativeCalls === 0, 'No deprecated native UTF-8 oracle call remains in active compatibility tests');
echo '[SUMMARY] checks=' . $checks . ', failures=' . count($errors) . PHP_EOL; echo $errors ? "T2A_PREFLIGHT=FAIL\n" : "T2A_PREFLIGHT=PASS\n"; exit($errors ? 1 : 0);
