<?php

declare(strict_types=1);

/**
 * U-2.3.3 preflight: the last executable native utf8_encode() call site,
 * Configurations.php:582, now delegates to ProcessMaker\Util\LegacyUtf8::encode(),
 * the UTF-8 ratchet is 5 textual / 0 executable, and the two locale-aware
 * strftime() calls on lines 582 and 585 are deliberately still native.
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
    'workflow/engine/src/ProcessMaker/Util/LegacyUtf8.php',
    'workflow/engine/src/ProcessMaker/Util/LegacyStrftime.php',
    'workflow/engine/classes/Configurations.php',
    'tests/unit/Compatibility/LegacyUtf8StrftimeCouplingTest.php',
    'tests/tools/verify-u233.php',
    'tests/tools/run-u233-checks.cmd',
];
foreach ($required as $relative) {
    $pass(is_file($root . '/' . $relative), 'Required U-2.3.3 file: ' . $relative);
}

$pass(hash_file('sha256', $root . '/composer.json') === '708119e1eb1f15b263a35366ff18116dcd828329f2481aa588efc49d81a33ad2', 'Unchanged since U-1: composer.json');
$pass(hash_file('sha256', $root . '/composer.lock') === 'c6d4c0da3da7483ad9499f8fdc5a137997cf57a55b1bbeee09f8210711a4c50f', 'Unchanged since U-1: composer.lock');
$pass(hash_file('sha256', $root . '/workflow/engine/src/ProcessMaker/Util/LegacyUtf8.php') === '1edb3051e45aa7096143251eb429681bc215f11ec5abf8b515270608f9601823', 'Unchanged since U-2.2.1: LegacyUtf8.php');
$pass(hash_file('sha256', $root . '/workflow/engine/src/ProcessMaker/Util/LegacyStrftime.php') === 'a992b694b354b3b86643e03525ce03864199214732d457934e15dab404fa560d', 'Unchanged since U-2.3.2a: LegacyStrftime.php');

require_once $root . '/tests/bootstrap.php';
require_once $root . '/workflow/engine/src/ProcessMaker/Util/LegacyUtf8.php';

$nativeEncode = '(?<![\w$>-])utf8_encode\s*\(';
$nativeUtf8 = '(?<![\w$>-])(?:utf8_encode|utf8_decode)\s*\(';
$nativePair = '(?<![\w$>-])utf8_encode\s*\(\s*strftime\s*\(';
$helperPair = '(?<![\w$>-])LegacyUtf8::encode\s*\(\s*strftime\s*\(';
$strftime = '(?<![\w$>-])(?:strftime|gmstrftime)\s*\(';
$strftimeHelper = '(?<![\w$>-])LegacyStrftime::';

$budget = json_decode((string) file_get_contents($root . '/tests/fixtures/deprecation-budget.json'), true, 512, JSON_THROW_ON_ERROR);
$raw = PhpSourceScanner::read($root . '/workflow/engine/classes/Configurations.php');
$code = PhpSourceScanner::codeOnlySource($raw);

$migratedLine = '$dateTime = \\ProcessMaker\\Util\\LegacyLocaleDate::format($newCreation, mktime($h, $i, $s, $m, $d, $y), $langLocate);';
$untouchedLine = '$dateTime = strftime($newCreation, mktime($h, $i, $s, $m, $d, $y));';

$pass(substr_count($raw, $migratedLine) === 2, 'Both branches call the locale-aware helper with their original arguments');
$pass(strpos($raw, $untouchedLine) === false, 'The native strftime() line is gone from both branches');
$pass(PhpSourceScanner::matchCount($code, $nativeEncode) === 0, 'No native utf8_encode() remains in Configurations.php');
$pass(PhpSourceScanner::matchCount($code, $nativePair) === 0, 'The native utf8_encode(strftime(...)) pair is gone');
$pass(PhpSourceScanner::matchCount($code, $helperPair) === 0, 'U-2.4.2 removed the UTF-8-wrapped strftime pair');

$pass(PhpSourceScanner::matchCount($code, $strftime) === 0, 'Configurations.php owns no native strftime() site');
$pass(PhpSourceScanner::matchLines($code, $strftime) === [], 'No native strftime() line remains');
$pass(PhpSourceScanner::matchCount($code, $strftimeHelper) === 0, 'U-2.3.3 did not migrate strftime(); LegacyStrftime is locale independent by design');
$pass(PhpSourceScanner::matchCount($code, '(?<![\w$>-])setlocale\s*\(\s*LC_TIME') === 2, 'Both setlocale(LC_TIME, ...) calls remain, they are what localizes %A/%B');
$pass(PhpSourceScanner::matchCount($code, 'function\s+getSystemDate\s*\(') === 1, 'getSystemDate() still exists exactly once');
$pass(PhpSourceScanner::matchCount($code, 'ucwords\s*\(\s*\$dateTime\s*\)') === 1, 'The ucwords() post-processing is untouched');
// String literals are blanked in the code-only projection, so the two
// literal-dependent guards below run against the raw source.
$pass(PhpSourceScanner::matchCount($raw, "str_replace\\(\\s*'\\[xx\\]'") === 1, 'The [xx] replacement is untouched');
$pass(PhpSourceScanner::matchCount($raw, "defined\\(\\s*'PARTNER_FLAG'\\s*\\)") === 1, 'The PARTNER_FLAG branch is untouched');

$counts = CompatibilityLedger::counts($budget['scope'], [
    'utf8' => $nativeUtf8,
    'strftime' => $strftime,
    'strftimeHelper' => $strftimeHelper,
]);

$pass($counts['utf8']['textual'] === 4, 'Textual UTF-8 ledger after U-6: 4 (found ' . $counts['utf8']['textual'] . ')');
$pass($counts['utf8']['code'] === 0, 'Executable UTF-8 ledger after U-2.3.3: 0 (found ' . $counts['utf8']['code'] . ')');
$pass($budget['budgets']['utf8_encode_decode'] === 4 && $budget['codeBudgets']['utf8_encode_decode'] === 0, 'utf8 ratchet lowered to 4 / 0 after U-6');
$pass(array_key_exists('_noteU233', $budget), 'The budget records why the utf8 ratchet moved');

$pass($counts['strftime']['textual'] === 140 && $counts['strftime']['code'] === 0, 'strftime ledger after U-2.4.2: 140 textual / 0 executable');
$pass($budget['budgets']['strftime'] === 140 && $budget['codeBudgets']['strftime'] === 0, 'strftime ratchet now 140 / 0');
$pass($counts['strftimeHelper']['code'] === 137, 'The 136 accepted U-2.3.2 consumers plus LegacyLocaleDate are present');

$perFile = [];
foreach (CompatibilityLedger::phpFiles($budget['scope']) as $file) {
    $hits = PhpSourceScanner::matchCount(PhpSourceScanner::read($file), $nativeUtf8);
    if ($hits > 0) {
        $perFile[CompatibilityLedger::relative($file)] = $hits;
    }
}
ksort($perFile);
// U-6 migrated the PMScript generated string, so only comment hits remain.
$expectedPerFile = [
    'gulliver/system/class.g.php' => 1,
    'workflow/engine/classes/model/Translation.php' => 3,
];
$pass($perFile === $expectedPerFile, 'Only the known comment hits remain: ' . json_encode($perFile));
$pass(CompatibilityLedger::locate($budget['scope'], $budget['patterns']['utf8_encode_decode'], 20) === [], 'The ledger finds no executable UTF-8 call site at all');

$divergent = 0;
foreach (range(0, 255) as $byte) {
    if (utf8_encode(chr($byte)) !== ProcessMaker\Util\LegacyUtf8::encode(chr($byte))) {
        ++$divergent;
    }
}
$pass($divergent === 0, 'Helper encode still matches the native encoder for all 256 bytes');
$pass(ProcessMaker\Util\LegacyUtf8::encode("mi\xE9rcoles, 02 de Febrero de 2013") === utf8_encode("mi\xE9rcoles, 02 de Febrero de 2013"), 'Localized date payload encodes identically');

echo '[SUMMARY] ' . $checks . ' U-2.3.3 preflight checks passed.' . PHP_EOL;
