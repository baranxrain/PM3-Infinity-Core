<?php

declare(strict_types=1);

/**
 * U-2.3.1 preflight: the strftime family is inventoried and frozen, the
 * accepted U-2.2.x results are intact, and nothing was migrated.
 */

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
    'tests/fixtures/strftime-inventory.json',
    'tests/tools/generate-strftime-inventory.php',
    'tests/tools/generate-strftime-oracle.php',
    'tests/tools/verify-u231.php',
    'tests/tools/run-u231-checks.cmd',
    'tests/unit/Compatibility/StrftimeInventoryTest.php',
];
foreach ($required as $relativeFile) {
    $pass(is_file($root . '/' . $relativeFile), 'Required U-2.3.1 file: ' . $relativeFile);
}

$pass(hash_file('sha256', $root . '/composer.json') === '708119e1eb1f15b263a35366ff18116dcd828329f2481aa588efc49d81a33ad2', 'composer.json is unchanged from accepted U-2.1');
$pass(hash_file('sha256', $root . '/composer.lock') === '913f83c278ba95912c1c0498a86033f01ce62d6a3501798e254cfda62c573033', 'Accepted T-4B PHP 8.3 Composer lock');
$pass(hash_file('sha256', $root . '/workflow/engine/src/ProcessMaker/Util/LegacyUtf8.php') === '1edb3051e45aa7096143251eb429681bc215f11ec5abf8b515270608f9601823', 'Accepted U-2.2.1 helper is still byte-identical');

require_once $root . '/tests/bootstrap.php';

class_alias(Tests\Support\CompatibilityLedger::class, 'U231Ledger');
class_alias(Tests\Support\PhpSourceScanner::class, 'U231Scanner');

$inventory = json_decode((string) file_get_contents($root . '/tests/fixtures/strftime-inventory.json'), true, 512, JSON_THROW_ON_ERROR);
$budget = json_decode((string) file_get_contents($root . '/tests/fixtures/deprecation-budget.json'), true, 512, JSON_THROW_ON_ERROR);

$pass($inventory['scope'] === $budget['scope'], 'Inventory uses the accepted nine-directory ledger scope');

$counts = U231Ledger::counts($inventory['scope'], [
    'strftime' => $inventory['pattern'],
    'gm' => '(?<![\w$>-])gmstrftime\s*\(',
    'utf8' => $budget['patterns']['utf8_encode_decode'],
    'helper' => '(?<![\w$>-])LegacyStrftime::',
]);

$pass($counts['strftime']['textual'] === (int) $budget['budgets']['strftime'], 'Textual strftime hits match the current ratchet (' . $counts['strftime']['textual'] . ')');
$pass($counts['strftime']['code'] === (int) $budget['codeBudgets']['strftime'], 'Executable strftime hits match the current ratchet (' . $counts['strftime']['code'] . ')');
$pass($counts['gm']['textual'] === 0, 'No gmstrftime occurrence exists in scope');
$pass($counts['helper']['code'] === 137, 'The 136 getters plus LegacyLocaleDate now consume the helper (found ' . $counts['helper']['code'] . ')');
$pass($inventory['summary']['textual'] === $counts['strftime']['textual'], 'Inventory textual total matches the tree');
$pass($inventory['summary']['executable'] === $counts['strftime']['code'], 'Inventory executable total matches the tree');
$pass($budget['budgets']['strftime'] === $counts['strftime']['textual'], 'Textual strftime ratchet is unchanged');
$pass($budget['codeBudgets']['strftime'] === $counts['strftime']['code'], 'Executable strftime ratchet is unchanged');

$pass($counts['utf8']['textual'] === 4, 'Textual UTF-8 ledger after U-6 (4)');
$pass($counts['utf8']['code'] === 0, 'Executable UTF-8 ledger after U-2.3.3 (0)');

$live = [];
foreach (U231Ledger::phpFiles($inventory['scope']) as $file) {
    $source = U231Scanner::read($file);
    if (U231Scanner::matchCount($source, $inventory['pattern']) === 0) {
        continue;
    }
    $inFile = U231Scanner::matchCount(U231Scanner::codeOnlySource($source), $inventory['pattern']);
    if ($inFile > 0) {
        $live[U231Ledger::relative($file)] = $inFile;
    }
}
ksort($live);
$pass($live === $inventory['perFileExecutable'], 'Per-file executable inventory matches the tree exactly (' . count($live) . ' files)');

$generated = 0;
$handwritten = 0;
foreach ($inventory['executableSites'] as $site) {
    $pass_category = $site['category'];
    if ($pass_category === 'generated_propel_getter') {
        ++$generated;
    } elseif ($pass_category === 'handwritten_locale_date') {
        ++$handwritten;
    }
}
$pass($generated === 0, 'No generated Propel getter still calls the deprecated function (all 136 migrated by U-2.3.2)');
$pass($handwritten === 0, 'Handwritten locale-date sites classified: 0, U-2.4.2 migrated both');
$pass($generated + $handwritten === $inventory['summary']['executable'], 'Every executable site is classified');

$configurations = U231Scanner::codeOnlySource(U231Scanner::read($root . '/workflow/engine/classes/Configurations.php'));
$pass(U231Scanner::matchCount($configurations, '(?<![\w$>-])utf8_encode\s*\(\s*strftime\s*\(') === 0, 'The native utf8_encode(strftime(...)) pair is gone (U-2.3.3)');
$pass(U231Scanner::matchCount($configurations, '(?<![\w$>-])LegacyUtf8::encode\s*\(\s*strftime\s*\(') === 0, 'U-2.4.2 removed the UTF-8-wrapped strftime pair');
$pass(U231Scanner::matchLines($configurations, $inventory['pattern']) === [], 'No handwritten strftime line remains');

echo '[SUMMARY] ' . $checks . ' U-2.3.1 preflight checks passed.' . PHP_EOL;
