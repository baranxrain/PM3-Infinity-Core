<?php

declare(strict_types=1);

/**
 * U-3.7 preflight: System::getPathsInstalled() no longer evaluates generated
 * require/return code for paths_installed.php.
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
    'workflow/engine/src/ProcessMaker/Core/System.php',
    'tests/fixtures/deprecation-budget.json',
    'tests/fixtures/eval-inventory.json',
    'tests/unit/Compatibility/SystemGetPathsInstalledEvalMigrationTest.php',
    'tests/tools/verify-u37.php',
    'tests/tools/run-u37-checks.cmd',
];
foreach ($required as $relative) {
    $pass(is_file($root . '/' . $relative), 'Required U-3.7 file: ' . $relative);
}

$pass(hash_file('sha256', $root . '/composer.json') === '708119e1eb1f15b263a35366ff18116dcd828329f2481aa588efc49d81a33ad2', 'Unchanged since U-1: composer.json');
$pass(hash_file('sha256', $root . '/composer.lock') === 'c6d4c0da3da7483ad9499f8fdc5a137997cf57a55b1bbeee09f8210711a4c50f', 'Unchanged since U-1: composer.lock');

require_once $root . '/tests/bootstrap.php';

$target = 'workflow/engine/src/ProcessMaker/Core/System.php';
$pattern = '(?<![\w$>-])eval\s*\(';
$raw = PhpSourceScanner::read($root . '/' . $target);
$code = PhpSourceScanner::codeOnlySource($raw);
$budget = json_decode((string) file_get_contents($root . '/tests/fixtures/deprecation-budget.json'), true, 512, JSON_THROW_ON_ERROR);
$inventory = json_decode((string) file_get_contents($root . '/tests/fixtures/eval-inventory.json'), true, 512, JSON_THROW_ON_ERROR);
$counts = CompatibilityLedger::counts($budget['scope'], ['eval' => $pattern]);

token_get_all($raw, TOKEN_PARSE);
$pass(true, 'System.php parses under PHP 8.1');
$pass(PhpSourceScanner::matchCount($code, $pattern) === 0, 'System.php remains eval-free after the follow-up credential migration');
$pass(strpos($raw, '$result = eval($script);') === false, 'The old getPathsInstalled eval result assignment was removed');
$pass(strpos($raw, '$script = "require_once') === false, 'The old generated getPathsInstalled script string was removed');
$pass(strpos($raw, '$pathsInstalled = getcwd() . "/workflow/engine/config/paths_installed.php";') !== false, 'paths_installed.php path is unchanged');
$pass(strpos($raw, 'if (file_exists($pathsInstalled))') !== false, 'paths_installed.php existence gate is unchanged');
$pass(strpos($raw, 'require_once $pathsInstalled;') !== false, 'paths_installed.php is loaded directly');
foreach (['pathData' => 'PATH_DATA', 'pathCompiled' => 'PATH_C', 'hashInstallation' => 'HASH_INSTALLATION', 'systemHash' => 'SYSTEM_HASH'] as $key => $constant) {
    $pass(strpos($raw, "'" . $key . "' => " . $constant) !== false, 'getPathsInstalled returns ' . $key . ' from ' . $constant);
}
$pass(strpos($raw, 'return (object) $result;') !== false, 'getPathsInstalled still returns an object');
$pass(strpos($raw, 'eval($this->getDatabaseCredentials') === false, 'The follow-up credential migration keeps System.php eval-free');

$pass(($budget['_noteU37'] ?? '') !== '', 'The U-3.7 eval ratchet movement is documented');
$pass($budget['budgets']['eval'] === 0, 'Eval textual budget lowered to 19');
$pass($budget['codeBudgets']['eval'] === 0, 'Eval executable budget lowered to 7');
$pass($counts['eval']['textual'] === 0, 'Live textual eval count is 19');
$pass($counts['eval']['code'] === 0, 'Live executable eval count is 7');

$pass($inventory['summary']['textual'] === 0, 'Inventory textual eval count is 19');
$pass($inventory['summary']['executable'] === 0, 'Inventory executable eval count is 7');
$pass($inventory['summary']['nonExecutable'] === 0, 'Inventory non-executable eval count remains 12');
$pass($inventory['summary']['executableFiles'] === 0, 'Inventory executable file count lowered to 4');
$pass(($inventory['categories']['core_bootstrap_dynamic_runtime']['count'] ?? null) === 0, 'Core bootstrap dynamic runtime category is closed');
$pass(!array_key_exists($target, $inventory['perFileExecutable']), 'System.php is absent from the per-file eval inventory');
$systemSites = array_values(array_filter($inventory['executableSites'], static fn (array $site): bool => $site['file'] === $target));
$pass(count($systemSites) === 0, 'No System.php eval site remains listed');

$pass($budget['budgets']['strftime'] === 140 && $budget['codeBudgets']['strftime'] === 0, 'Strftime ratchet is unchanged');
$pass($budget['budgets']['utf8_encode_decode'] === 4 && $budget['codeBudgets']['utf8_encode_decode'] === 0, 'UTF-8 ratchet is unchanged');

echo '[SUMMARY] ' . $checks . ' U-3.7 preflight checks passed.' . PHP_EOL;
