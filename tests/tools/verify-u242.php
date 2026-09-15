<?php

declare(strict_types=1);

/**
 * U-2.4.2 preflight: the last two executable strftime() call sites,
 * Configurations.php:582 and :585, now render through
 * ProcessMaker\Util\LegacyLocaleDate, so the executable strftime ratchet
 * reaches zero.
 *
 * The helper carries the five locale-dependent specifiers (%a %A %b %B %p)
 * from tables transcribed out of the U-2.4.1 oracle and delegates everything
 * else to LegacyStrftime. Line 582 also stops lifting its result through
 * LegacyUtf8::encode(), because the helper answers in UTF-8, so the LegacyUtf8
 * consumer list drops from nine production files to eight.
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
    'workflow/engine/src/ProcessMaker/Util/LegacyLocaleDate.php',
    'tests/tools/verify-u242.php',
    'tests/tools/run-u242-checks.cmd',
    'tests/unit/Compatibility/LegacyLocaleDateTest.php',
    'tests/fixtures/legacy-strftime-locale-oracle.json',
];
foreach ($required as $relative) {
    $pass(is_file($root . '/' . $relative), 'Required U-2.4.2 file: ' . $relative);
}

$pass(hash_file('sha256', $root . '/composer.json') === 'b3f0ff9a9882690f210fec2c8106175b47bed82f0aea1de035873a2d12295f0a', 'Unchanged since U-1: composer.json');
$pass(hash_file('sha256', $root . '/composer.lock') === '085ed0f8c619f302684dd8daba5dd65b5a47b4572aaf092731fefea30e15418c', 'Unchanged since U-1: composer.lock');
$pass(hash_file('sha256', $root . '/workflow/engine/src/ProcessMaker/Util/LegacyUtf8.php') === '1edb3051e45aa7096143251eb429681bc215f11ec5abf8b515270608f9601823', 'Unchanged since U-2.2.1: LegacyUtf8.php');
$pass(hash_file('sha256', $root . '/workflow/engine/src/ProcessMaker/Util/LegacyStrftime.php') === 'a992b694b354b3b86643e03525ce03864199214732d457934e15dab404fa560d', 'Unchanged since U-2.3.2a: LegacyStrftime.php');
$pass(hash_file('sha256', $root . '/workflow/engine/src/ProcessMaker/Util/LegacyLocaleDate.php') === 'a5a07377bef7ce64afee0bfcdfd3fcdb5061ad6643a6d4a4db7648eea264740f', 'The reviewed U-2.4.2 helper: LegacyLocaleDate.php');
$pass(hash_file('sha256', $root . '/workflow/engine/classes/Configurations.php') === '6f1145154fbc6e5ff12a64201c7e983844677c72c16f8913b5e91ae2368a27ac', 'The reviewed U-2.4.2 migration: Configurations.php');

require_once $root . '/tests/bootstrap.php';
require_once $root . '/workflow/engine/src/ProcessMaker/Util/LegacyStrftime.php';
require_once $root . '/workflow/engine/src/ProcessMaker/Util/LegacyLocaleDate.php';

$budget = json_decode((string) file_get_contents($root . '/tests/fixtures/deprecation-budget.json'), true, 512, JSON_THROW_ON_ERROR);
$inventory = json_decode((string) file_get_contents($root . '/tests/fixtures/strftime-inventory.json'), true, 512, JSON_THROW_ON_ERROR);

$strftime = $budget['patterns']['strftime'];
$counts = CompatibilityLedger::counts($budget['scope'], [
    'strftime' => $strftime,
    'utf8' => $budget['patterns']['utf8_encode_decode'],
    'helper' => '(?<![\w$>-])LegacyStrftime::',
    'locale' => '(?<![\w$>-])LegacyLocaleDate::',
]);

$pass($counts['strftime']['code'] === 0, 'No executable native strftime() call remains in scope (found ' . $counts['strftime']['code'] . ')');
$pass($counts['strftime']['textual'] === 140, 'The 140 textual strftime hits are untouched (found ' . $counts['strftime']['textual'] . ')');
$pass($budget['codeBudgets']['strftime'] === 0, 'The executable strftime ratchet is lowered to 0');
$pass($budget['budgets']['strftime'] === 140, 'The textual strftime ratchet stays at 140');
$pass(array_key_exists('_noteU242', $budget), 'The budget records why the ratchet moved and what changed behaviourally');
$pass($counts['helper']['code'] === 137, 'The 136 accepted U-2.3.2 consumers plus LegacyLocaleDate are present (found ' . $counts['helper']['code'] . ')');
$pass($counts['locale']['code'] === 2, 'Exactly the two migrated call sites consume the locale-aware helper (found ' . $counts['locale']['code'] . ')');
$pass($counts['utf8']['textual'] === 4 && $counts['utf8']['code'] === 0, 'The UTF-8 ledger U-6 lowered is untouched (4 / 0)');

$pass($inventory['summary']['executable'] === 0, 'The inventory reports zero executable sites');
$pass($inventory['summary']['textual'] === 140, 'The inventory textual total is unchanged');
$pass($inventory['summary']['nonExecutable'] === 140, 'Every remaining hit is non-executable');
$pass($inventory['executableSites'] === [], 'The inventory site list is empty');
$pass($inventory['categories']['handwritten_locale_date']['count'] === 0, 'The handwritten locale-date category is closed');
$pass($inventory['summary']['executable'] === $counts['strftime']['code'], 'The inventory matches the live tree');

$raw = PhpSourceScanner::read($root . '/workflow/engine/classes/Configurations.php');
$code = PhpSourceScanner::codeOnlySource($raw);
$helperLine = '$dateTime = \\ProcessMaker\\Util\\LegacyLocaleDate::format($newCreation, mktime($h, $i, $s, $m, $d, $y), $langLocate);';

$pass(substr_count($raw, $helperLine) === 2, 'Both branches call the helper with the original arguments and the active locale name');
$pass(PhpSourceScanner::matchCount($code, $strftime) === 0, 'Neither branch calls strftime() any more');
$pass(PhpSourceScanner::matchLines($code, $strftime) === [], 'No native strftime() line remains');
$pass(PhpSourceScanner::matchCount($code, '(?<![\w$>-])setlocale\s*\(\s*LC_TIME') === 2, 'Both setlocale(LC_TIME, ...) calls remain untouched');
$pass(PhpSourceScanner::matchCount($code, '(?<![\w$>-])LegacyUtf8::') === 0, 'Line 582 no longer needs a UTF-8 lift');
$pass(PhpSourceScanner::matchCount($code, 'function\s+getSystemDate\s*\(') === 1, 'getSystemDate() still exists exactly once');
$pass(PhpSourceScanner::matchCount($code, 'ucwords\s*\(\s*\$dateTime\s*\)') === 1, 'The ucwords() post-processing is untouched');
// String literals are blanked in the code-only projection, so the two literal
// guards below run against the raw source, including comments.
$pass(PhpSourceScanner::matchCount($raw, "str_replace\\(\\s*'\\[xx\\]'") === 1, 'The [xx] replacement is untouched');
$pass(PhpSourceScanner::matchCount($raw, "defined\\(\\s*'PARTNER_FLAG'\\s*\\)") === 1, 'The PARTNER_FLAG branch is untouched');

$helperReferences = [];
foreach (CompatibilityLedger::phpFiles($budget['scope']) as $file) {
    $source = file_get_contents($file);
    if ($source === false || strpos($source, 'LegacyUtf8') === false) {
        continue;
    }
    $relative = CompatibilityLedger::relative($file);
    if ($relative === 'workflow/engine/src/ProcessMaker/Util/LegacyUtf8.php') {
        continue;
    }
    $helperReferences[] = $relative;
}
sort($helperReferences);
// PHP's sort() is byte-ordered, so the lowercase 'class.' prefix sorts after
// the uppercase file names in the same directory.
$expectedConsumers = [
    'gulliver/system/class.g.php',
    'gulliver/system/class.inputfilter.php',
    'workflow/engine/classes/SpoolRun.php',
    'workflow/engine/classes/class.pmScript.php',
    'workflow/engine/controllers/pmTablesProxy.php',
    'workflow/engine/methods/events/eventsSetupGraph.php',
    'workflow/engine/methods/users/usersAjax.php',
    'workflow/engine/src/ProcessMaker/EmailOAuth/EmailBase.php',
];
$pass($helperReferences === $expectedConsumers, 'The LegacyUtf8 consumers are the expected eight files: ' . implode(', ', $helperReferences));

$helperSource = PhpSourceScanner::read($root . '/workflow/engine/src/ProcessMaker/Util/LegacyLocaleDate.php');
$helperCode = PhpSourceScanner::codeOnlySource($helperSource);
$pass(PhpSourceScanner::matchCount($helperCode, '(?<![\w$>-])(?:strftime|gmstrftime|setlocale|utf8_encode|utf8_decode)\s*\(') === 0, 'The helper reintroduces none of the deprecated functions');
$pass(PhpSourceScanner::matchCount($helperCode, '(?<![\w$>-])LegacyStrftime::format\s*\(') === 1, 'Locale-independent specifiers are delegated in exactly one place');
$pass(substr_count($helperSource, 'legacy-strftime-locale-oracle.json') === 1, 'The helper names the evidence its tables came from');

$oracle = json_decode((string) file_get_contents($root . '/tests/fixtures/legacy-strftime-locale-oracle.json'), true, 512, JSON_THROW_ON_ERROR);
$faithful = ['c_locale' => 'C', 'windows_es_utf8' => 'ESN.utf8', 'windows_pt_utf8' => 'PTB.utf8'];
$drift = [];
$compared = 0;
foreach ($faithful as $localeKey => $legacyName) {
    foreach ($oracle['translatedMasks'] as $formatId => $translation) {
        foreach ($oracle['timestamps'] as $label => $timestamp) {
            $expected = hex2bin($oracle['locales'][$localeKey]['masks'][$formatId][$label]['hex']);
            $actual = \ProcessMaker\Util\LegacyLocaleDate::format($translation['strftimeMask'], (int) $timestamp, $legacyName);
            ++$compared;
            if ($expected !== $actual) {
                $drift[] = $localeKey . '/' . $formatId . '/' . $label;
            }
        }
    }
}
$pass($compared === 153, 'Every accepted locale, mask and timestamp was replayed (' . $compared . ')');
$pass($drift === [], 'The helper reproduces the captured bytes exactly' . ($drift === [] ? '' : ': ' . implode(' ', array_slice($drift, 0, 5))));

// The English fix: the runtime resolved 'EST' to Spanish_United States, so the
// captured English rendering carries Spanish names. The helper must answer with
// the C-locale names instead, and that difference must be visible.
$divergences = 0;
foreach ($oracle['translatedMasks'] as $formatId => $translation) {
    foreach ($oracle['timestamps'] as $label => $timestamp) {
        $english = hex2bin($oracle['locales']['c_locale']['masks'][$formatId][$label]['hex']);
        $captured = hex2bin($oracle['locales']['windows_en_utf8']['masks'][$formatId][$label]['hex']);
        foreach (['EST', 'EST.utf8', 'en', 'en_US', 'unknown'] as $name) {
            if (\ProcessMaker\Util\LegacyLocaleDate::format($translation['strftimeMask'], (int) $timestamp, $name) !== $english) {
                $drift[] = 'english/' . $formatId . '/' . $label . '/' . $name;
            }
        }
        if ($english !== $captured) {
            ++$divergences;
        }
    }
}
$pass($drift === [], 'The English and default branch renders the English names' . ($drift === [] ? '' : ': ' . implode(' ', array_slice($drift, 0, 5))));
$pass($divergences > 0, 'The fixed defect is visible in the evidence: the runtime used to render Spanish names for the English locale name (' . $divergences . ' renderings)');

$pass(\ProcessMaker\Util\LegacyLocaleDate::format('%I:%M %p', 1104537845, 'ESN.utf8') === '12:04 ', 'The captured empty Spanish meridiem is preserved');
$pass(\ProcessMaker\Util\LegacyLocaleDate::format('%I:%M %p', 1104537845, 'EST') === '12:04 AM', 'The English meridiem still renders');
foreach (['ESN' => 'es', 'PTB' => 'pt', 'EST' => 'en', 'pt-BR' => 'pt', 'es_ES.utf8' => 'es', 'ja_JP' => 'en'] as $name => $language) {
    $pass(\ProcessMaker\Util\LegacyLocaleDate::language($name) === $language, 'Locale name resolves by language: ' . $name . ' -> ' . $language);
}
foreach (\ProcessMaker\Util\LegacyStrftime::refusedSpecifiers() as $specifier) {
    $pass(\ProcessMaker\Util\LegacyLocaleDate::format('x ' . $specifier, 1104537845, 'EST') === false, 'The helper still refuses ' . $specifier);
}

echo '[SUMMARY] ' . $checks . ' U-2.4.2 preflight checks passed.' . PHP_EOL;
