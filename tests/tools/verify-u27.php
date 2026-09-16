<?php

declare(strict_types=1);

/**
 * U-2.7 preflight: the remaining FILTER_SANITIZE_STRING and
 * FILTER_FLAG_STRIP_LOW/HIGH executable hits in class.inputfilter.php are
 * migrated to a local compatibility helper.
 */

use Tests\Support\CompatibilityLedger;
use Tests\Support\PhpSourceScanner;

$root = dirname(__DIR__, 2);
$checks = 0;
$pass = static function (bool $condition, string $message) use (&$checks): void {
    if (!$condition) {
        fwrite(STDERR, '[FAIL] ' . $message . PHP_EOL);
        exit(1);
    }
    ++$checks;
    echo '[PASS] ' . $message . PHP_EOL;
};

$pass(PHP_SAPI === 'cli', 'CLI runtime');
$pass(PHP_VERSION_ID >= 80100 && PHP_VERSION_ID < 80300, 'PHP 8.1/8.2 target (' . PHP_VERSION . ')');

$required = [
    'gulliver/system/class.inputfilter.php',
    'tests/tools/verify-u27.php',
    'tests/tools/run-u27-checks.cmd',
    'tests/unit/Compatibility/FilterSanitizeStringMigrationTest.php',
    'tests/fixtures/php8-compatibility.json',
];
foreach ($required as $relative) {
    $pass(is_file($root . '/' . $relative), 'Required U-2.7 file: ' . $relative);
}

$pass(hash_file('sha256', $root . '/composer.json') === '708119e1eb1f15b263a35366ff18116dcd828329f2481aa588efc49d81a33ad2', 'Unchanged since U-1: composer.json');
$pass(hash_file('sha256', $root . '/composer.lock') === '9f879af7b047666ee70708741d74521c91925e1b6addd80a9d465b6ea76e9cb3', 'Accepted T-2B rev E Composer lock');

require_once $root . '/tests/bootstrap.php';

$fixture = json_decode((string) file_get_contents($root . '/tests/fixtures/php8-compatibility.json'), true, 512, JSON_THROW_ON_ERROR);
$pattern = $fixture['ratchet']['filter_sanitize_string']['pattern'];
$counts = CompatibilityLedger::counts($fixture['scope'], ['filter_sanitize_string' => $pattern]);

$pass(($fixture['ratchet']['filter_sanitize_string']['max'] ?? null) === 0, 'The legacy string-sanitizer executable ratchet is lowered to 0');
$pass(array_key_exists('_noteU27', $fixture), 'The compatibility fixture records the U-2.7 ratchet move');
$pass($counts['filter_sanitize_string']['code'] === 0, 'No executable legacy string-sanitizer hit remains in scope (found ' . $counts['filter_sanitize_string']['code'] . ')');

$raw = PhpSourceScanner::read($root . '/gulliver/system/class.inputfilter.php');
$code = PhpSourceScanner::codeOnlySource($raw);
$pass(PhpSourceScanner::matchCount($code, 'FILTER_SANITIZE_STRING|FILTER_FLAG_STRIP_(?:LOW|HIGH)') === 0, 'class.inputfilter.php no longer contains executable legacy constants');
$pass(PhpSourceScanner::matchCount($code, 'function\s+sanitizeLegacyString\s*\(') === 1, 'The local compatibility helper is declared exactly once');
$pass(PhpSourceScanner::matchCount($code, '\$this->sanitizeLegacyString\s*\(') === 6, 'All six call sites use the local compatibility helper');
$pass(strpos($raw, '$this->sanitizeLegacyString($value, true, true)') !== false, 'The nosql path keeps low/high stripping');
$pass(strpos($raw, '$this->sanitizeLegacyString($value, true)') !== false, 'The default sanitize path keeps low-byte stripping');
$pass(strpos($raw, 'strip_tags((string)$value)') !== false, 'The helper strips tags like the legacy sanitizer');
$pass(strpos($raw, "array('&#34;', '&#39;')") !== false, 'The helper preserves quote entity encoding');

$hits = CompatibilityLedger::locate($fixture['scope'], $pattern, 10);
$pass($hits === [], 'The legacy string-sanitizer locator returns no remaining executable hits');

echo '[SUMMARY] ' . $checks . ' U-2.7 preflight checks passed.' . PHP_EOL;
