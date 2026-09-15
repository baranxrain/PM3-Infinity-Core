<?php

declare(strict_types=1);

/**
 * U-2.3.1 tool: regenerate tests/fixtures/strftime-inventory.json from the
 * live tree, using the same region-aware projection the test suite uses.
 *
 * The inventory is evidence, not a migration. Running this tool must not
 * change any production file. Use --check to compare without writing.
 */

use Tests\Support\CompatibilityLedger;
use Tests\Support\PhpSourceScanner;

$root = dirname(__DIR__, 2);
require_once $root . '/tests/bootstrap.php';

$checkOnly = in_array('--check', $argv, true);
$fixture = $root . '/tests/fixtures/strftime-inventory.json';
$current = json_decode((string) file_get_contents($fixture), true, 512, JSON_THROW_ON_ERROR);

$scope = $current['scope'];
$pattern = $current['pattern'];
$generatedLine = $current['categories']['generated_propel_getter']['exactLine'];

$textual = 0;
$executable = 0;
$gmTextual = 0;
$sites = [];
$perFile = [];

foreach (CompatibilityLedger::phpFiles($scope) as $file) {
    $source = PhpSourceScanner::read($file);
    $textual += PhpSourceScanner::matchCount($source, $pattern);
    $gmTextual += PhpSourceScanner::matchCount($source, '(?<![\w$>-])gmstrftime\s*\(');

    $projection = PhpSourceScanner::codeOnlySource($source);
    $inFile = PhpSourceScanner::matchCount($projection, $pattern);
    if ($inFile === 0) {
        continue;
    }

    $executable += $inFile;
    $relative = CompatibilityLedger::relative($file);
    $perFile[$relative] = $inFile;
    $lines = preg_split('/\r\n|\n|\r/', $source) ?: [];

    foreach (PhpSourceScanner::matchLines($projection, $pattern) as $line) {
        $text = trim($lines[$line - 1] ?? '');
        if (strpos($relative, '/model/om/') !== false && $text === $generatedLine) {
            $category = 'generated_propel_getter';
        } elseif ($relative === 'workflow/engine/classes/Configurations.php') {
            $category = 'handwritten_locale_date';
        } else {
            $category = 'unclassified';
        }

        $sites[] = [
            'file' => $relative,
            'line' => $line,
            'function' => strpos($text, 'gmstrftime') === false ? 'strftime' : 'gmstrftime',
            'category' => $category,
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
    'gmstrftimeTextual' => $gmTextual,
    'executableFiles' => count($perFile),
];
$next['categories']['generated_propel_getter']['count'] = $counts['generated_propel_getter'] ?? 0;
$next['categories']['generated_propel_getter']['files'] = count(array_unique(array_column(
    array_filter($sites, static fn (array $site): bool => $site['category'] === 'generated_propel_getter'),
    'file'
)));
$next['categories']['handwritten_locale_date']['count'] = $counts['handwritten_locale_date'] ?? 0;
$next['categories']['non_executable']['count'] = $textual - $executable;
$next['perFileExecutable'] = $perFile;
$next['executableSites'] = $sites;

$encoded = json_encode($next, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
if ($encoded === false) {
    fwrite(STDERR, 'Unable to encode inventory.' . PHP_EOL);
    exit(1);
}

if ($checkOnly) {
    $drift = $next['summary'] !== $current['summary']
        || $next['perFileExecutable'] !== $current['perFileExecutable']
        || $next['executableSites'] !== $current['executableSites'];
    echo $drift ? '[DRIFT] Inventory does not match the tree.' . PHP_EOL : '[OK] Inventory matches the tree.' . PHP_EOL;
    exit($drift ? 1 : 0);
}

file_put_contents($fixture, $encoded . PHP_EOL);
echo '[OK] Wrote ' . $textual . ' textual and ' . $executable . ' executable strftime sites.' . PHP_EOL;
