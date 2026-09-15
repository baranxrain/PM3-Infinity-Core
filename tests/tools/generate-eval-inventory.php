<?php

declare(strict_types=1);

/**
 * Regenerate tests/fixtures/eval-inventory.json from the live tree, using
 * the same code-only projection as the compatibility ledger. U-2.9 introduced
 * this fixture; later hardening units refresh it as the ratchet moves down.
 */

use Tests\Support\CompatibilityLedger;
use Tests\Support\PhpSourceScanner;

$root = dirname(__DIR__, 2);
require_once $root . '/tests/bootstrap.php';

$checkOnly = in_array('--check', $argv, true);
$fixture = $root . '/tests/fixtures/eval-inventory.json';
$current = json_decode((string) file_get_contents($fixture), true, 512, JSON_THROW_ON_ERROR);

$scope = $current['scope'];
$pattern = $current['pattern'];

$categoryOf = static function (string $relative): string {
    if ($relative === 'workflow/engine/classes/class.pmScript.php') {
        return 'trigger_script_eval';
    }
    if (strpos($relative, 'workflow/engine/classes/model/') === 0
        || strpos($relative, 'workflow/engine/src/ProcessMaker/BusinessModel/') === 0
        || in_array($relative, ['workflow/engine/classes/PropelTable.php', 'workflow/engine/classes/XMLConnection.php'], true)) {
        return 'dynamic_model_or_criteria';
    }
    if (strpos($relative, 'gulliver/system/') === 0) {
        return 'gulliver_dynamic_runtime';
    }
    if (strpos($relative, 'workflow/engine/methods/') === 0) {
        return 'engine_method_dynamic_runtime';
    }
    if (strpos($relative, 'workflow/engine/src/ProcessMaker/Core/') === 0
        || strpos($relative, 'workflow/engine/src/ProcessMaker/Util/') === 0) {
        return 'core_bootstrap_dynamic_runtime';
    }
    if (strpos($relative, 'workflow/engine/classes/') === 0) {
        return 'legacy_engine_dynamic_runtime';
    }

    return 'unclassified';
};

$textual = 0;
$executable = 0;
$perFile = [];
$sites = [];

foreach (CompatibilityLedger::phpFiles($scope) as $file) {
    $source = PhpSourceScanner::read($file);
    $textual += PhpSourceScanner::matchCount($source, $pattern);

    $projection = PhpSourceScanner::codeOnlySource($source);
    $inFile = PhpSourceScanner::matchCount($projection, $pattern);
    if ($inFile === 0) {
        continue;
    }

    $relative = CompatibilityLedger::relative($file);
    $perFile[$relative] = $inFile;
    $executable += $inFile;
    $lines = preg_split('/\r\n|\n|\r/', $source) ?: [];

    foreach (PhpSourceScanner::matchLines($projection, $pattern) as $line) {
        $sites[] = [
            'file' => $relative,
            'line' => $line,
            'category' => $categoryOf($relative),
            'text' => trim($lines[$line - 1] ?? ''),
        ];
    }
}

ksort($perFile);
usort($sites, static function (array $left, array $right): int {
    return [$left['file'], $left['line']] <=> [$right['file'], $right['line']];
});

$counts = array_count_values(array_column($sites, 'category'));
$next = $current;
$next['summary'] = [
    'textual' => $textual,
    'executable' => $executable,
    'nonExecutable' => $textual - $executable,
    'executableFiles' => count($perFile),
];
foreach ($next['categories'] as $name => &$definition) {
    if ($name === 'non_executable') {
        $definition['count'] = $textual - $executable;
    } else {
        $definition['count'] = $counts[$name] ?? 0;
    }
}
unset($definition);
$next['perFileExecutable'] = $perFile;
$next['executableSites'] = $sites;

$encoded = json_encode($next, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
if ($encoded === false) {
    fwrite(STDERR, 'Unable to encode eval inventory.' . PHP_EOL);
    exit(1);
}

if ($checkOnly) {
    $drift = $next['summary'] !== $current['summary']
        || $next['categories'] !== $current['categories']
        || $next['perFileExecutable'] !== $current['perFileExecutable']
        || $next['executableSites'] !== $current['executableSites'];
    echo $drift ? '[DRIFT] Eval inventory does not match the tree.' . PHP_EOL : '[OK] Eval inventory matches the tree.' . PHP_EOL;
    exit($drift ? 1 : 0);
}

file_put_contents($fixture, $encoded . PHP_EOL);
echo '[OK] Wrote ' . $textual . ' textual and ' . $executable . ' executable eval sites.' . PHP_EOL;
