<?php

declare(strict_types=1);

/**
 * U-2.8 preflight: class.pmScript.php no longer has a direct executable
 * set_error_handler() ledger hit, and still installs the trigger handler
 * through installTriggerErrorHandler().
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
    'workflow/engine/classes/class.pmScript.php',
    'tests/tools/verify-u28.php',
    'tests/tools/run-u28-checks.cmd',
    'tests/unit/Compatibility/SetErrorHandlerMigrationTest.php',
    'tests/fixtures/php8-compatibility.json',
];
foreach ($required as $relative) {
    $pass(is_file($root . '/' . $relative), 'Required U-2.8 file: ' . $relative);
}

$pass(hash_file('sha256', $root . '/composer.json') === '708119e1eb1f15b263a35366ff18116dcd828329f2481aa588efc49d81a33ad2', 'Unchanged since U-1: composer.json');
$pass(hash_file('sha256', $root . '/composer.lock') === '913f83c278ba95912c1c0498a86033f01ce62d6a3501798e254cfda62c573033', 'Accepted T-4B PHP 8.3 Composer lock');

require_once $root . '/tests/bootstrap.php';

$fixture = json_decode((string) file_get_contents($root . '/tests/fixtures/php8-compatibility.json'), true, 512, JSON_THROW_ON_ERROR);
$pattern = $fixture['ratchet']['set_error_handler']['pattern'];
$counts = CompatibilityLedger::counts($fixture['scope'], ['set_error_handler' => $pattern]);

$pass(($fixture['ratchet']['set_error_handler']['max'] ?? null) === 0, 'The set_error_handler executable ratchet is lowered to 0');
$pass(array_key_exists('_noteU28', $fixture), 'The compatibility fixture records the U-2.8 ratchet move');
$pass($counts['set_error_handler']['code'] === 0, 'No executable direct set_error_handler() hit remains in scope (found ' . $counts['set_error_handler']['code'] . ')');

$raw = PhpSourceScanner::read($root . '/workflow/engine/classes/class.pmScript.php');
$code = PhpSourceScanner::codeOnlySource($raw);
$pass(PhpSourceScanner::matchCount($code, '(?<![\w$>-])set_error_handler\s*\(') === 0, 'class.pmScript.php no longer contains a direct executable set_error_handler() call');
$pass(PhpSourceScanner::matchCount($code, 'function\s+installTriggerErrorHandler\s*\(') === 1, 'The local trigger error-handler installer exists exactly once');
$pass(PhpSourceScanner::matchCount($code, '\$this->installTriggerErrorHandler\s*\(') === 1, 'executeAndCatchErrors() calls the installer exactly once');
$pass(strpos($raw, "call_user_func('set_error_handler', 'handleErrors', (int)ini_get('error_reporting'));") !== false, 'The installer preserves the legacy handler and error mask');
$pass(strpos($raw, "ob_start('handleFatalErrors');") !== false, 'The fatal-error output handler is untouched');
$pass(PhpSourceScanner::matchCount($code, '(?<![\w$>-])eval\s*\(') === 0, 'U-3.17 closes the final PMScript eval() body sites');

$hits = CompatibilityLedger::locate($fixture['scope'], $pattern, 10);
$pass($hits === [], 'The set_error_handler locator returns no remaining executable hits');

echo '[SUMMARY] ' . $checks . ' U-2.8 preflight checks passed.' . PHP_EOL;
