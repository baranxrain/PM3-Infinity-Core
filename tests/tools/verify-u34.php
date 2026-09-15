<?php

declare(strict_types=1);

/**
 * U-3.4 preflight: upgrade_SystemAjax loads workspace db.php credentials with
 * a direct define parser and setter instead of eval().
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
$pass(PHP_VERSION_ID >= 80100 && PHP_VERSION_ID < 80200, 'PHP 8.1.x target (' . PHP_VERSION . ')');

$required = [
    'workflow/engine/methods/setup/upgrade_SystemAjax.php',
    'tests/fixtures/deprecation-budget.json',
    'tests/fixtures/eval-inventory.json',
    'tests/unit/Compatibility/UpgradeSystemAjaxEvalMigrationTest.php',
    'tests/tools/verify-u34.php',
    'tests/tools/run-u34-checks.cmd',
];
foreach ($required as $relative) {
    $pass(is_file($root . '/' . $relative), 'Required U-3.4 file: ' . $relative);
}

$pass(hash_file('sha256', $root . '/composer.json') === 'b3f0ff9a9882690f210fec2c8106175b47bed82f0aea1de035873a2d12295f0a', 'Unchanged since U-1: composer.json');
$pass(hash_file('sha256', $root . '/composer.lock') === '085ed0f8c619f302684dd8daba5dd65b5a47b4572aaf092731fefea30e15418c', 'Unchanged since U-1: composer.lock');

require_once $root . '/tests/bootstrap.php';

$target = 'workflow/engine/methods/setup/upgrade_SystemAjax.php';
$pattern = '(?<![\w$>-])eval\s*\(';
$raw = PhpSourceScanner::read($root . '/' . $target);
$code = PhpSourceScanner::codeOnlySource($raw);
$budget = json_decode((string) file_get_contents($root . '/tests/fixtures/deprecation-budget.json'), true, 512, JSON_THROW_ON_ERROR);
$inventory = json_decode((string) file_get_contents($root . '/tests/fixtures/eval-inventory.json'), true, 512, JSON_THROW_ON_ERROR);
$counts = CompatibilityLedger::counts($budget['scope'], ['eval' => $pattern]);

token_get_all($raw, TOKEN_PARSE);
$pass(true, 'upgrade_SystemAjax parses under PHP 8.1');
$pass(PhpSourceScanner::matchCount($code, $pattern) === 0, 'upgrade_SystemAjax contains no executable eval() sites');
$pass(strpos($raw, 'eval(getDatabaseCredentials') === false, 'The old credential eval loader was removed');
$pass(strpos($raw, "setDatabaseCredentials(getDatabaseCredentials(PATH_DB . \$workspace . PATH_SEP . 'db.php'));") !== false, 'Workspace credential load uses the direct setter');
$pass(strpos($raw, '$database = new database($DB_ADAPTER, $DB_HOST, $DB_USER, $DB_PASS, $DB_NAME);') !== false, 'Database construction still uses the five legacy globals');
$pass(PhpSourceScanner::matchCount($code, 'function\s+getDatabaseCredentials\s*\(') === 1, 'getDatabaseCredentials() exists once');
$pass(PhpSourceScanner::matchCount($code, 'function\s+setDatabaseCredentials\s*\(') === 1, 'setDatabaseCredentials() exists once');
$pass(strpos($raw, 'preg_match_all("/define\s*\(') !== false, 'Credential parser targets define() statements');
$pass(strpos($raw, 'PREG_SET_ORDER') !== false, 'Credential parser uses PREG_SET_ORDER');
$pass(strpos($raw, '$credentials[$match[1]] = stripcslashes($match[2]);') !== false, 'Credential parser decodes quoted values');
$pass(PhpSourceScanner::matchCount($code, '(?<![\w$>-])preg_match_all\s*\(') === 1, 'upgrade_SystemAjax has exactly one preg_match_all() parser');
$pass(PhpSourceScanner::matchCount($code, '(?<![\w$>-])stripcslashes\s*\(') === 1, 'upgrade_SystemAjax has exactly one stripcslashes() decoder');

foreach (['DB_ADAPTER', 'DB_HOST', 'DB_USER', 'DB_PASS', 'DB_NAME'] as $name) {
    $pass(strpos($raw, 'global $' . $name . ';') !== false, 'Credential setter declares global $' . $name);
    $pass(strpos($raw, '$' . $name . " = isset(\$credentials['" . $name . "']) ? \$credentials['" . $name . "'] : null;") !== false, 'Credential setter assigns $' . $name);
}

$pass(($budget['_noteU34'] ?? '') !== '', 'The U-3.4 eval ratchet movement is documented');
$pass($budget['budgets']['eval'] === 0, 'Eval textual budget lowered to 19');
$pass($budget['codeBudgets']['eval'] === 0, 'Eval executable budget lowered to 7');
$pass($counts['eval']['textual'] === 0, 'Live textual eval count is 19');
$pass($counts['eval']['code'] === 0, 'Live executable eval count is 7');

$pass($inventory['summary']['textual'] === 0, 'Inventory textual eval count is 19');
$pass($inventory['summary']['executable'] === 0, 'Inventory executable eval count is 7');
$pass($inventory['summary']['nonExecutable'] === 0, 'Inventory non-executable eval count remains 12');
$pass($inventory['summary']['executableFiles'] === 0, 'Inventory executable file count is 4');
$pass(($inventory['categories']['engine_method_dynamic_runtime']['count'] ?? null) === 0, 'Engine method dynamic runtime category lowered to 0');
$pass(!array_key_exists($target, $inventory['perFileExecutable']), 'upgrade_SystemAjax is absent from per-file eval inventory');
$remainingFiles = array_values(array_unique(array_column($inventory['executableSites'], 'file')));
$pass(!in_array($target, $remainingFiles, true), 'upgrade_SystemAjax is absent from executable eval sites');

$pass($budget['budgets']['strftime'] === 140 && $budget['codeBudgets']['strftime'] === 0, 'Strftime ratchet is unchanged');
$pass($budget['budgets']['utf8_encode_decode'] === 4 && $budget['codeBudgets']['utf8_encode_decode'] === 0, 'UTF-8 ratchet is unchanged');

echo '[SUMMARY] ' . $checks . ' U-3.4 preflight checks passed.' . PHP_EOL;
