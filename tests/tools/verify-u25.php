<?php

declare(strict_types=1);

/**
 * U-2.5 preflight: the two executable strptime-name hits in
 * gulliver/system/class.xmlform.php are migrated to a project-owned fallback
 * name. This closes the PHP 8.1 deprecated / PHP 8.2 removed strptime ratchet.
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
$pass(PHP_VERSION_ID >= 80100 && PHP_VERSION_ID < 80400, 'PHP 8.1/8.2/8.3 target (' . PHP_VERSION . ')');

$required = [
    'gulliver/system/class.xmlform.php',
    'tests/tools/verify-u25.php',
    'tests/tools/run-u25-checks.cmd',
    'tests/unit/Compatibility/StrptimeMigrationTest.php',
    'tests/fixtures/php8-compatibility.json',
];
foreach ($required as $relative) {
    $pass(is_file($root . '/' . $relative), 'Required U-2.5 file: ' . $relative);
}

$pass(hash_file('sha256', $root . '/composer.json') === '708119e1eb1f15b263a35366ff18116dcd828329f2481aa588efc49d81a33ad2', 'Unchanged since U-1: composer.json');
$pass(hash_file('sha256', $root . '/composer.lock') === '913f83c278ba95912c1c0498a86033f01ce62d6a3501798e254cfda62c573033', 'Accepted T-4B PHP 8.3 Composer lock');

require_once $root . '/tests/bootstrap.php';

$fixture = json_decode((string) file_get_contents($root . '/tests/fixtures/php8-compatibility.json'), true, 512, JSON_THROW_ON_ERROR);
$pattern = $fixture['ratchet']['strptime']['pattern'];
$counts = CompatibilityLedger::counts($fixture['scope'], ['strptime' => $pattern]);

$pass(($fixture['ratchet']['strptime']['max'] ?? null) === 0, 'The strptime executable ratchet is lowered to 0');
$pass(array_key_exists('_noteU25', $fixture), 'The compatibility fixture records the U-2.5 ratchet move');
$pass($counts['strptime']['code'] === 0, 'No executable strptime-name hit remains in scope (found ' . $counts['strptime']['code'] . ')');

$raw = PhpSourceScanner::read($root . '/gulliver/system/class.xmlform.php');
$code = PhpSourceScanner::codeOnlySource($raw);
$pass(PhpSourceScanner::matchCount($code, '(?<![\w$>-])strptime\s*\(') === 0, 'class.xmlform.php no longer calls or declares the native strptime name');
$pass(PhpSourceScanner::matchCount($code, '(?<![\w$>-])pmStrptimeCompat\s*\(') === 2, 'The project-owned fallback has one call and one declaration');
$pass(strpos($raw, '$ugly = pmStrptimeCompat($schedule, $schedule_format);') !== false, 'The date parser calls the project-owned fallback');
$pass(strpos($raw, "if (!function_exists('pmStrptimeCompat'))") !== false, 'The fallback guard uses the project-owned name');
$pass(strpos($raw, 'function pmStrptimeCompat($date, $format)') !== false, 'The fallback declaration uses the project-owned name');
$pass(strpos($raw, "'%Y' => '(?P<Y>[0-9]{4})'") !== false, 'The fallback year mapping is untouched');
$pass(strpos($raw, '"tm_year" => $out[\'Y\'] > 1900 ? $out[\'Y\'] - 1900 : 0') !== false, 'The fallback return shape is untouched');

$other = [];
foreach (CompatibilityLedger::locate($fixture['scope'], $pattern, 10) as $hit) {
    $other[] = $hit;
}
$pass($other === [], 'The strptime locator returns no remaining executable hits');

echo '[SUMMARY] ' . $checks . ' U-2.5 preflight checks passed.' . PHP_EOL;
