<?php

declare(strict_types=1);

/**
 * U-2.6 preflight: Padl no longer contains executable mcrypt_* calls and always
 * uses the regular cipher path that PHP 8 already selected on the acceptance
 * runtime because ext/mcrypt is unavailable.
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
    'workflow/engine/classes/Padl.php',
    'tests/tools/verify-u26.php',
    'tests/tools/run-u26-checks.cmd',
    'tests/unit/Compatibility/McryptMigrationTest.php',
    'tests/fixtures/php8-compatibility.json',
];
foreach ($required as $relative) {
    $pass(is_file($root . '/' . $relative), 'Required U-2.6 file: ' . $relative);
}

$pass(hash_file('sha256', $root . '/composer.json') === '708119e1eb1f15b263a35366ff18116dcd828329f2481aa588efc49d81a33ad2', 'Unchanged since U-1: composer.json');
$pass(hash_file('sha256', $root . '/composer.lock') === '9f879af7b047666ee70708741d74521c91925e1b6addd80a9d465b6ea76e9cb3', 'Accepted T-2B rev E Composer lock');

require_once $root . '/tests/bootstrap.php';

$fixture = json_decode((string) file_get_contents($root . '/tests/fixtures/php8-compatibility.json'), true, 512, JSON_THROW_ON_ERROR);
$pattern = $fixture['ratchet']['mcrypt_ext']['pattern'];
$counts = CompatibilityLedger::counts($fixture['scope'], ['mcrypt_ext' => $pattern]);

$pass(($fixture['ratchet']['mcrypt_ext']['max'] ?? null) === 0, 'The mcrypt executable ratchet is lowered to 0');
$pass(array_key_exists('_noteU26', $fixture), 'The compatibility fixture records the U-2.6 ratchet move');
$pass($counts['mcrypt_ext']['code'] === 0, 'No executable mcrypt_* hit remains in scope (found ' . $counts['mcrypt_ext']['code'] . ')');

$raw = PhpSourceScanner::read($root . '/workflow/engine/classes/Padl.php');
$code = PhpSourceScanner::codeOnlySource($raw);
$pass(PhpSourceScanner::matchCount($code, '(?<![\w$>-])mcrypt_[a-z_]+\s*\(') === 0, 'Padl.php no longer contains executable mcrypt_* calls');
$pass(strpos($raw, '$this->USE_MCRYPT = false;') !== false, 'Padl init always selects the regular cipher path');
$pass(strpos($raw, "function_exists('mcrypt_generic')") === false, 'Padl no longer probes the removed mcrypt extension');
$pass(substr_count($raw, '$char = chr(ord($char) + ord($keychar));') === 1, 'The regular encryption loop is preserved');
$pass(substr_count($raw, '$char = chr(ord($char) - ord($keychar));') === 1, 'The regular decryption loop is preserved');
$pass(strpos($raw, '$query .= \'&MCRYPT=\' . $this->USE_MCRYPT;') !== false, 'The dial-home payload still reports the cipher path');

$hits = CompatibilityLedger::locate($fixture['scope'], $pattern, 10);
$pass($hits === [], 'The mcrypt locator returns no remaining executable hits');

echo '[SUMMARY] ' . $checks . ' U-2.6 preflight checks passed.' . PHP_EOL;
