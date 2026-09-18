<?php

declare(strict_types=1);

/**
 * Eval inventory preflight. U-2.9 introduced the fixture; later hardening
 * units lower the ratchet and refresh this inventory.
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
    'tests/fixtures/eval-inventory.json',
    'tests/tools/generate-eval-inventory.php',
    'tests/tools/verify-u29.php',
    'tests/tools/run-u29-checks.cmd',
    'tests/unit/Compatibility/EvalInventoryTest.php',
    'tests/fixtures/deprecation-budget.json',
];
foreach ($required as $relative) {
    $pass(is_file($root . '/' . $relative), 'Required U-2.9 file: ' . $relative);
}

$pass(hash_file('sha256', $root . '/composer.json') === '708119e1eb1f15b263a35366ff18116dcd828329f2481aa588efc49d81a33ad2', 'Unchanged since U-1: composer.json');
$pass(hash_file('sha256', $root . '/composer.lock') === '913f83c278ba95912c1c0498a86033f01ce62d6a3501798e254cfda62c573033', 'Accepted T-4B PHP 8.3 Composer lock');

require_once $root . '/tests/bootstrap.php';

$budget = json_decode((string) file_get_contents($root . '/tests/fixtures/deprecation-budget.json'), true, 512, JSON_THROW_ON_ERROR);
$inventory = json_decode((string) file_get_contents($root . '/tests/fixtures/eval-inventory.json'), true, 512, JSON_THROW_ON_ERROR);
$counts = CompatibilityLedger::counts($inventory['scope'], ['eval' => $inventory['pattern']]);

$pass($inventory['scope'] === $budget['scope'], 'The eval inventory uses the accepted compatibility scope');
$pass($inventory['pattern'] === $budget['patterns']['eval'], 'The eval inventory uses the accepted eval pattern');
$pass($inventory['summary']['textual'] === 0, 'The eval textual inventory is pinned at 14');
$pass($inventory['summary']['executable'] === 0, 'The eval executable inventory is pinned at 2');
$pass($inventory['summary']['nonExecutable'] === 0, 'The eval non-executable inventory is pinned at 12');
$pass($inventory['summary']['executableFiles'] === 0, 'The eval executable file count is pinned at 1');
$pass($counts['eval']['textual'] === $inventory['summary']['textual'], 'The textual eval count matches the live tree');
$pass($counts['eval']['code'] === $inventory['summary']['executable'], 'The executable eval count matches the live tree');
$pass($budget['budgets']['eval'] === $inventory['summary']['textual'], 'The eval textual ratchet matches the current inventory');
$pass($budget['codeBudgets']['eval'] === $inventory['summary']['executable'], 'The eval executable ratchet matches the current inventory');

$expected = [
    'trigger_script_eval' => 0,
    'dynamic_model_or_criteria' => 0,
    'gulliver_dynamic_runtime' => 0,
    'engine_method_dynamic_runtime' => 0,
    'core_bootstrap_dynamic_runtime' => 0,
    'legacy_engine_dynamic_runtime' => 0,
    'non_executable' => 0,
];
foreach ($expected as $category => $count) {
    $pass(($inventory['categories'][$category]['count'] ?? null) === $count, 'Eval category pinned: ' . $category . ' = ' . $count);
}

$sites = $inventory['executableSites'];
$pass(count($sites) === 0, 'No executable eval site remains');
$unclassified = array_values(array_filter($sites, static fn (array $site): bool => ($site['category'] ?? '') === 'unclassified'));
$pass($unclassified === [], 'No executable eval site is unclassified');

$perFile = $inventory['perFileExecutable'];
$pass($perFile === [], 'No file remains in the executable eval inventory');
$pass(array_sum($perFile) === 0, 'Per-file eval counts sum to 0');

$projection = PhpSourceScanner::codeOnlySource(PhpSourceScanner::read($root . '/workflow/engine/methods/cases/ajaxListener.php'));
$pass(PhpSourceScanner::matchCount($projection, $inventory['pattern']) === 0, 'Inline JavaScript eval() in ajaxListener.php is not executable PHP');

$generator = PhpSourceScanner::read($root . '/tests/tools/generate-eval-inventory.php');
$pass(PhpSourceScanner::matchCount(PhpSourceScanner::codeOnlySource($generator), '(?<![\w$>-])file_put_contents\s*\(') === 1, 'The generator writes exactly one fixture');

echo '[SUMMARY] ' . $checks . ' U-2.9 preflight checks passed.' . PHP_EOL;
