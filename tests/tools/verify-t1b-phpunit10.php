<?php
declare(strict_types=1);
$root = dirname(__DIR__, 2); $errors = [];
$check = static function (bool $ok, string $message) use (&$errors): void { echo ($ok ? '[PASS] ' : '[FAIL] ') . $message . "\n"; if (!$ok) { $errors[] = $message; } };
$check(PHP_MAJOR_VERSION === 8 && PHP_MINOR_VERSION === 1, 'PHP 8.1 lane');
$phar = $root . '/tests/tools/phpunit-10.phar';
$check(is_file($phar), 'Pinned PHAR exists');
if (is_file($phar)) {
 $check(hash_file('sha256', $phar) === 'a823d916151f628dd9943ccc81a98bcfbba9c5babf53f27be6c7dccc89f8ee23', 'Pinned SHA-256');
 exec(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($phar) . ' --version 2>&1', $output, $exitCode); $version = trim(implode("\n", $output)); echo '[INFO] ' . $version . "\n";
 $check($exitCode === 0 && preg_match('/^PHPUnit 10\.5\.64\b/', $version) === 1, 'Pinned PHPUnit 10.5.64');
}
$check(hash_file('sha256', $root . '/tests/tools/phpunit-9.5.8.phar') === '11f27cf3f9522241fe234e9bf5813667207a074ac92089aac26d502ffc5e9517', 'PHPUnit 9 baseline unchanged');
$composer = json_decode((string) file_get_contents($root . '/composer.json'), true); $check(($composer['require-dev']['phpunit/phpunit'] ?? null) === '9.5', 'Composer baseline unchanged');
$xml = (string) file_get_contents($root . '/phpunit-10.xml');
$runner = (string) file_get_contents($root . '/tests/tools/run-t1b-phpunit10-checks.cmd');
$configStrict = strpos($xml, 'failOnDeprecation="true"') !== false && strpos($xml, 'failOnPhpunitDeprecation="true"') !== false;
$cliStrict = strpos($runner, '--fail-on-deprecation') !== false && strpos($runner, '--fail-on-phpunit-deprecation') !== false;
$check($configStrict, 'Strict deprecation attributes in phpunit-10.xml');
$check($cliStrict, 'Strict deprecation CLI gates in runner');
$check(strpos((string) file_get_contents($root . '/workflow/engine/classes/Padl.php'), 'mt_srand((int) $seed);') !== false, 'Explicit Padl seed cast');
echo $errors ? "T1B_PREFLIGHT=FAIL\n" : "T1B_PREFLIGHT=PASS\n"; exit($errors ? 1 : 0);
