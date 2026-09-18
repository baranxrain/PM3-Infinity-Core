<?php

declare(strict_types=1);

/**
 * U-2.3.2 preflight: the 136 uniform Propel om getters call the accepted
 * LegacyStrftime helper, the ratchet was lowered to 142 / 2, the helper itself
 * is byte-identical to the accepted U-2.3.2a unit, and nothing else moved.
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
    'workflow/engine/src/ProcessMaker/Util/LegacyStrftime.php',
    'tests/fixtures/strftime-inventory.json',
    'tests/fixtures/legacy-strftime-contract.json',
    'tests/unit/Compatibility/StrftimeCallSiteMigrationTest.php',
    'tests/tools/verify-u232.php',
    'tests/tools/run-u232-checks.cmd',
];
foreach ($required as $relative) {
    $pass(is_file($root . '/' . $relative), 'Required U-2.3.2 file: ' . $relative);
}

$pass(hash_file('sha256', $root . '/composer.json') === '708119e1eb1f15b263a35366ff18116dcd828329f2481aa588efc49d81a33ad2', 'Unchanged since U-1: composer.json');
$pass(hash_file('sha256', $root . '/composer.lock') === '913f83c278ba95912c1c0498a86033f01ce62d6a3501798e254cfda62c573033', 'Accepted T-4B PHP 8.3 Composer lock');
$pass(hash_file('sha256', $root . '/workflow/engine/src/ProcessMaker/Util/LegacyUtf8.php') === '1edb3051e45aa7096143251eb429681bc215f11ec5abf8b515270608f9601823', 'Unchanged since U-2.2.1: LegacyUtf8.php');
$pass(hash_file('sha256', $root . '/workflow/engine/src/ProcessMaker/Util/LegacyStrftime.php') === 'a992b694b354b3b86643e03525ce03864199214732d457934e15dab404fa560d', 'Unchanged since U-2.3.2a: LegacyStrftime.php');

require_once $root . '/tests/bootstrap.php';
require_once $root . '/workflow/engine/src/ProcessMaker/Util/LegacyStrftime.php';

$oldLine = 'return strftime($format, $ts);';
$newLine = 'return \\ProcessMaker\\Util\\LegacyStrftime::format($format, $ts);';
$strftimePattern = '(?<![\w$>-])(?:strftime|gmstrftime)\s*\(';
$helperPattern = '(?<![\w$>-])LegacyStrftime::';

$budget = json_decode((string) file_get_contents($root . '/tests/fixtures/deprecation-budget.json'), true, 512, JSON_THROW_ON_ERROR);
$inventory = json_decode((string) file_get_contents($root . '/tests/fixtures/strftime-inventory.json'), true, 512, JSON_THROW_ON_ERROR);

$migrated = 0;
$migratedFiles = [];
$staleFiles = [];
$shapeViolations = [];
$generatedStillCalling = [];

foreach (CompatibilityLedger::phpFiles($budget['scope']) as $file) {
    $relative = CompatibilityLedger::relative($file);
    if (strpos($relative, '/model/om/') === false) {
        continue;
    }

    $source = PhpSourceScanner::read($file);
    $projection = PhpSourceScanner::codeOnlySource($source);

    $hits = substr_count($source, $newLine);
    if ($hits > 0) {
        $migrated += $hits;
        $migratedFiles[$relative] = $hits;
    }

    if (strpos($source, $oldLine) !== false) {
        $staleFiles[] = $relative;
    }

    if (PhpSourceScanner::matchCount($projection, $strftimePattern) !== 0) {
        $generatedStillCalling[] = $relative;
    }

    $lines = preg_split('/\r\n|\n|\r/', $source) ?: [];
    foreach (PhpSourceScanner::matchLines($projection, $helperPattern) as $line) {
        if (trim($lines[$line - 1] ?? '') !== $newLine) {
            $shapeViolations[] = $relative . ':' . $line;
        }
    }
}

$pass($staleFiles === [], 'No generated getter kept the pre-migration line' . ($staleFiles === [] ? '' : ': ' . implode(', ', array_slice($staleFiles, 0, 5))));
$pass($generatedStillCalling === [], 'No generated file calls the deprecated function' . ($generatedStillCalling === [] ? '' : ': ' . implode(', ', array_slice($generatedStillCalling, 0, 5))));
$pass($shapeViolations === [], 'Every helper call site is the uniform generated line' . ($shapeViolations === [] ? '' : ': ' . implode(', ', array_slice($shapeViolations, 0, 5))));
$pass($migrated === 136, 'Migrated getters: 136 (found ' . $migrated . ')');
$pass(count($migratedFiles) === 59, 'Migrated files: 59 (found ' . count($migratedFiles) . ')');

$counts = CompatibilityLedger::counts($budget['scope'], [
    'strftime' => $strftimePattern,
    'helper' => $helperPattern,
    'utf8' => $budget['patterns']['utf8_encode_decode'],
    'gm' => '(?<![\w$>-])gmstrftime\s*\(',
]);

$pass($counts['strftime']['textual'] === 140, 'Textual strftime ledger: 140 (found ' . $counts['strftime']['textual'] . ')');
$pass($counts['strftime']['code'] === 0, 'Executable strftime ledger after U-2.4.2: 0 (found ' . $counts['strftime']['code'] . ')');
$pass($counts['helper']['code'] === 137, 'Executable helper consumers: 137 = 136 getters + LegacyLocaleDate (found ' . $counts['helper']['code'] . ')');
$pass($counts['gm']['textual'] === 0, 'Still no gmstrftime occurrence in scope');
$pass($counts['utf8']['textual'] === 4 && $counts['utf8']['code'] === 0, 'UTF-8 ledger after U-6: 4 textual / 0 executable');

$pass($budget['budgets']['strftime'] === 140, 'Textual ratchet lowered to 140');
$pass($budget['codeBudgets']['strftime'] === 0, 'Executable ratchet lowered to 0 by U-2.4.2');
$pass(isset($budget['_noteU232']), 'The unit recorded why the ratchet moved');
$pass($budget['budgets']['utf8_encode_decode'] === 4 && $budget['codeBudgets']['utf8_encode_decode'] === 0, 'utf8 ratchet lowered by U-6 to 4 / 0');

$pass($inventory['summary']['textual'] === 140, 'Inventory textual total matches the tree');
$pass($inventory['summary']['executable'] === 0, 'Inventory reports no remaining executable site');
$pass($inventory['summary']['executableFiles'] === 0, 'No file still holds executable strftime hits');
$pass($inventory['categories']['generated_propel_getter']['count'] === 0, 'No generated getter remains');
$pass($inventory['categories']['handwritten_locale_date']['count'] === 0, 'U-2.4.2 migrated both handwritten sites');

$configurations = PhpSourceScanner::codeOnlySource(PhpSourceScanner::read($root . '/workflow/engine/classes/Configurations.php'));
$pass(PhpSourceScanner::matchCount($configurations, $strftimePattern) === 0, 'Configurations.php owns no native strftime site');
$pass(PhpSourceScanner::matchCount($configurations, $helperPattern) === 0, 'U-2.3.2 did not touch Configurations.php');
$pass(PhpSourceScanner::matchCount($configurations, '(?<![\w$>-])LegacyUtf8::encode\s*\(\s*strftime\s*\(') === 0, 'U-2.4.2 removed the UTF-8-wrapped strftime pair');

$maskOffenders = [];
foreach (CompatibilityLedger::phpFiles($budget['scope']) as $file) {
    $relative = CompatibilityLedger::relative($file);
    if ($relative === 'workflow/engine/src/ProcessMaker/Util/LegacyStrftime.php') {
        continue;
    }
    if (preg_match('~[\'"][^\'"]*%[zZ]~', PhpSourceScanner::read($file)) === 1) {
        $maskOffenders[] = $relative;
    }
}
$pass($maskOffenders === [], 'No literal mask in scope uses %z or %Z, which the helper refuses' . ($maskOffenders === [] ? '' : ': ' . implode(', ', array_slice($maskOffenders, 0, 5))));

$pass(ProcessMaker\Util\LegacyStrftime::format('%Y-%m-%d', 1119873600) === '2005-06-27', 'Helper still renders the accepted contract value');
$pass(ProcessMaker\Util\LegacyStrftime::format('%k', 0) === false, 'Helper still refuses %k, exactly as the native function did');

echo '[SUMMARY] ' . $checks . ' U-2.3.2 preflight checks passed.' . PHP_EOL;
