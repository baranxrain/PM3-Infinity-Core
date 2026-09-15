<?php

declare(strict_types=1);

/**
 * U-3.2 preflight: DynaformEditor temporary-data loading no longer evaluates
 * its generated cache file as PHP.
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
    'workflow/engine/classes/DynaformEditor.php',
    'tests/fixtures/deprecation-budget.json',
    'tests/fixtures/eval-inventory.json',
    'tests/unit/Compatibility/DynaformEditorEvalMigrationTest.php',
    'tests/tools/verify-u32.php',
    'tests/tools/run-u32-checks.cmd',
];
foreach ($required as $relative) {
    $pass(is_file($root . '/' . $relative), 'Required U-3.2 file: ' . $relative);
}

$pass(hash_file('sha256', $root . '/composer.json') === 'b3f0ff9a9882690f210fec2c8106175b47bed82f0aea1de035873a2d12295f0a', 'Unchanged since U-1: composer.json');
$pass(hash_file('sha256', $root . '/composer.lock') === '085ed0f8c619f302684dd8daba5dd65b5a47b4572aaf092731fefea30e15418c', 'Unchanged since U-1: composer.lock');

require_once $root . '/tests/bootstrap.php';

$target = 'workflow/engine/classes/DynaformEditor.php';
$pattern = '(?<![\w$>-])eval\s*\(';
$raw = PhpSourceScanner::read($root . '/' . $target);
$code = PhpSourceScanner::codeOnlySource($raw);
$budget = json_decode((string) file_get_contents($root . '/tests/fixtures/deprecation-budget.json'), true, 512, JSON_THROW_ON_ERROR);
$inventory = json_decode((string) file_get_contents($root . '/tests/fixtures/eval-inventory.json'), true, 512, JSON_THROW_ON_ERROR);
$counts = CompatibilityLedger::counts($budget['scope'], ['eval' => $pattern]);

token_get_all($raw, TOKEN_PARSE);
$pass(true, 'DynaformEditor parses under PHP 8.1');
$pass(PhpSourceScanner::matchCount($code, $pattern) === 0, 'DynaformEditor contains no executable eval() sites');
$pass(strpos($raw, "eval(implode('', file(\$file)))") === false, 'The old temporary-data eval loader was removed');
$pass(strpos($raw, 'public static function _setTmpData($data)') !== false, '_setTmpData API is still present');
$pass(strpos($raw, 'public static function _getTmpData()') !== false, '_getTmpData API is still present');
$pass(strpos($raw, "PATH_C . 'dynEditor/' . session_id() . '.php'") !== false, 'Temporary-data file path is unchanged');
$pass(strpos($raw, "fwrite(\$fp, '\$tmpData=unserialize(\\'' . addcslashes(serialize(\$data), '\\\\\\'') . '\\');');") !== false, 'Temporary-data writer format is unchanged');
$pass(strpos($raw, 'preg_match("/^\\$tmpData=unserialize') !== false, 'Temporary-data reader parses the expected assignment shape');
$pass(strpos($raw, 'unserialize(strtr($match[1]') !== false, 'Temporary-data reader decodes and unserializes the payload directly');
$pass(PhpSourceScanner::matchCount($code, '(?<![\w$>-])preg_match\s*\(') === 1, 'DynaformEditor has exactly one preg_match() parser');
$pass(PhpSourceScanner::matchCount($code, '(?<![\w$>-])strtr\s*\(') === 1, 'DynaformEditor has exactly one strtr() decoder');

$pass(($budget['_noteU32'] ?? '') !== '', 'The U-3.2 eval ratchet movement is documented');
$pass($budget['budgets']['eval'] === 0, 'Eval textual budget lowered to 19');
$pass($budget['codeBudgets']['eval'] === 0, 'Eval executable budget lowered to 7');
$pass($counts['eval']['textual'] === 0, 'Live textual eval count is 19');
$pass($counts['eval']['code'] === 0, 'Live executable eval count is 7');

$pass($inventory['summary']['textual'] === 0, 'Inventory textual eval count is 19');
$pass($inventory['summary']['executable'] === 0, 'Inventory executable eval count is 7');
$pass($inventory['summary']['nonExecutable'] === 0, 'Inventory non-executable eval count remains 12');
$pass($inventory['summary']['executableFiles'] === 0, 'Inventory executable file count is 4');
$pass(($inventory['categories']['legacy_engine_dynamic_runtime']['count'] ?? null) === 0, 'Legacy engine dynamic runtime category is closed');
$pass(!array_key_exists($target, $inventory['perFileExecutable']), 'DynaformEditor is absent from per-file eval inventory');
$remainingFiles = array_values(array_unique(array_column($inventory['executableSites'], 'file')));
$pass(!in_array($target, $remainingFiles, true), 'DynaformEditor is absent from executable eval sites');

$pass($budget['budgets']['strftime'] === 140 && $budget['codeBudgets']['strftime'] === 0, 'Strftime ratchet is unchanged');
$pass($budget['budgets']['utf8_encode_decode'] === 4 && $budget['codeBudgets']['utf8_encode_decode'] === 0, 'UTF-8 ratchet is unchanged');

echo '[SUMMARY] ' . $checks . ' U-3.2 preflight checks passed.' . PHP_EOL;
