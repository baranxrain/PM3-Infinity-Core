<?php

declare(strict_types=1);

/**
 * U-3.3 preflight: weekendAjax dispatches the already-gated user function via
 * call_user_func() instead of eval().
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
    'workflow/engine/methods/setup/weekendAjax.php',
    'tests/fixtures/deprecation-budget.json',
    'tests/fixtures/eval-inventory.json',
    'tests/unit/Compatibility/WeekendAjaxEvalMigrationTest.php',
    'tests/tools/verify-u33.php',
    'tests/tools/run-u33-checks.cmd',
];
foreach ($required as $relative) {
    $pass(is_file($root . '/' . $relative), 'Required U-3.3 file: ' . $relative);
}

$pass(hash_file('sha256', $root . '/composer.json') === '708119e1eb1f15b263a35366ff18116dcd828329f2481aa588efc49d81a33ad2', 'Unchanged since U-1: composer.json');
$pass(hash_file('sha256', $root . '/composer.lock') === '913f83c278ba95912c1c0498a86033f01ce62d6a3501798e254cfda62c573033', 'Accepted T-4B PHP 8.3 Composer lock');

require_once $root . '/tests/bootstrap.php';

$target = 'workflow/engine/methods/setup/weekendAjax.php';
$pattern = '(?<![\w$>-])eval\s*\(';
$raw = PhpSourceScanner::read($root . '/' . $target);
$code = PhpSourceScanner::codeOnlySource($raw);
$budget = json_decode((string) file_get_contents($root . '/tests/fixtures/deprecation-budget.json'), true, 512, JSON_THROW_ON_ERROR);
$inventory = json_decode((string) file_get_contents($root . '/tests/fixtures/eval-inventory.json'), true, 512, JSON_THROW_ON_ERROR);
$counts = CompatibilityLedger::counts($budget['scope'], ['eval' => $pattern]);

token_get_all($raw, TOKEN_PARSE);
$pass(true, 'weekendAjax parses under PHP 8.1');
$pass(PhpSourceScanner::matchCount($code, $pattern) === 0, 'weekendAjax contains no executable eval() sites');
$pass(strpos($raw, "eval( \$funcion . '();' );") === false, 'The old dynamic function eval dispatch was removed');
$pass(strpos($raw, "\$funcion = strtolower( get_ajax_value( 'function' ) );") !== false, 'Function request lookup is unchanged');
$pass(strpos($raw, '$funcions = get_defined_functions();') !== false, 'Defined-functions lookup is unchanged');
$pass(strpos($raw, "if (in_array( \$funcion, \$funcions['user'] ))") !== false, 'Existing user-function gate is preserved');
$pass(strpos($raw, 'call_user_func( $funcion );') !== false, 'User-function dispatch uses call_user_func()');
$pass(PhpSourceScanner::matchCount($code, '(?<![\w$>-])call_user_func\s*\(') === 1, 'weekendAjax has exactly one call_user_func() replacement');
$pass(strpos($raw, 'function setDays ()') !== false, 'setDays() remains available');
$pass(strpos($raw, 'function setDay ($day, $dayValue)') !== false, 'setDay() remains available');

$pass(($budget['_noteU33'] ?? '') !== '', 'The U-3.3 eval ratchet movement is documented');
$pass($budget['budgets']['eval'] === 0, 'Eval textual budget lowered to 19');
$pass($budget['codeBudgets']['eval'] === 0, 'Eval executable budget lowered to 7');
$pass($counts['eval']['textual'] === 0, 'Live textual eval count is 19');
$pass($counts['eval']['code'] === 0, 'Live executable eval count is 7');

$pass($inventory['summary']['textual'] === 0, 'Inventory textual eval count is 19');
$pass($inventory['summary']['executable'] === 0, 'Inventory executable eval count is 7');
$pass($inventory['summary']['nonExecutable'] === 0, 'Inventory non-executable eval count remains 12');
$pass($inventory['summary']['executableFiles'] === 0, 'Inventory executable file count is 4');
$pass(($inventory['categories']['engine_method_dynamic_runtime']['count'] ?? null) === 0, 'Engine method dynamic runtime category lowered to 0');
$pass(!array_key_exists($target, $inventory['perFileExecutable']), 'weekendAjax is absent from per-file eval inventory');
$remainingFiles = array_values(array_unique(array_column($inventory['executableSites'], 'file')));
$pass(!in_array($target, $remainingFiles, true), 'weekendAjax is absent from executable eval sites');

$pass($budget['budgets']['strftime'] === 140 && $budget['codeBudgets']['strftime'] === 0, 'Strftime ratchet is unchanged');
$pass($budget['budgets']['utf8_encode_decode'] === 4 && $budget['codeBudgets']['utf8_encode_decode'] === 0, 'UTF-8 ratchet is unchanged');

echo '[SUMMARY] ' . $checks . ' U-3.3 preflight checks passed.' . PHP_EOL;
