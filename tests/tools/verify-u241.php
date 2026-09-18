<?php

declare(strict_types=1);

/**
 * U-2.4.1 preflight: the locale-dependent evidence for the last two executable
 * strftime() sites (Configurations.php:582 and :585) is captured on the
 * acceptance runtime, and nothing is migrated by this unit.
 *
 * This unit is tooling only. Production code must be byte-identical to the
 * accepted U-6 tree, so the target file's SHA-256 is locked here.
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
    'tests/tools/generate-strftime-locale-oracle.php',
    'tests/tools/verify-u241.php',
    'tests/tools/run-u241-checks.cmd',
    'tests/unit/Compatibility/StrftimeLocaleOracleTest.php',
    'tests/fixtures/legacy-strftime-oracle.json',
    'tests/fixtures/legacy-strftime-locale-oracle.json',
];
foreach ($required as $relative) {
    $pass(is_file($root . '/' . $relative), 'Required U-2.4.1 file: ' . $relative);
}

$pass(hash_file('sha256', $root . '/composer.json') === '708119e1eb1f15b263a35366ff18116dcd828329f2481aa588efc49d81a33ad2', 'Unchanged since U-1: composer.json');
$pass(hash_file('sha256', $root . '/composer.lock') === '913f83c278ba95912c1c0498a86033f01ce62d6a3501798e254cfda62c573033', 'Accepted T-4B PHP 8.3 Composer lock');
$pass(hash_file('sha256', $root . '/workflow/engine/src/ProcessMaker/Util/LegacyUtf8.php') === '1edb3051e45aa7096143251eb429681bc215f11ec5abf8b515270608f9601823', 'Unchanged since U-2.2.1: LegacyUtf8.php');
$pass(hash_file('sha256', $root . '/workflow/engine/src/ProcessMaker/Util/LegacyStrftime.php') === 'a992b694b354b3b86643e03525ce03864199214732d457934e15dab404fa560d', 'Unchanged since U-2.3.2a: LegacyStrftime.php');
$pass(hash_file('sha256', $root . '/workflow/engine/classes/Configurations.php') === '6f1145154fbc6e5ff12a64201c7e983844677c72c16f8913b5e91ae2368a27ac', 'The target production file is the accepted U-2.4.2 migration: Configurations.php');

require_once $root . '/tests/bootstrap.php';

$budget = json_decode((string) file_get_contents($root . '/tests/fixtures/deprecation-budget.json'), true, 512, JSON_THROW_ON_ERROR);
$counts = CompatibilityLedger::counts($budget['scope'], [
    'strftime' => $budget['patterns']['strftime'],
    'utf8' => $budget['patterns']['utf8_encode_decode'],
    'helper' => '(?<![\w$>-])LegacyStrftime::',
]);

$pass($counts['strftime']['textual'] === 140 && $counts['strftime']['code'] === 0, 'The strftime ledger after U-2.4.2 (140 / 0)');
$pass($budget['budgets']['strftime'] === 140 && $budget['codeBudgets']['strftime'] === 0, 'The strftime ratchet after U-2.4.2 (140 / 0)');
$pass($counts['utf8']['textual'] === 4 && $counts['utf8']['code'] === 0, 'The UTF-8 ledger U-6 lowered is untouched (4 / 0)');
$pass($counts['helper']['code'] === 137, 'The 136 migrated Propel getters plus LegacyLocaleDate consume LegacyStrftime');

$configurations = PhpSourceScanner::read($root . '/workflow/engine/classes/Configurations.php');
$pass(PhpSourceScanner::matchLines($configurations, $budget['patterns']['strftime']) === [], 'U-2.4.2 migrated both locale-aware call sites');
$pass(strpos($configurations, "str_replace('[xx]', ' de ', \$dateTime)") !== false, 'The [xx] replacement is untouched');
$pass(strpos($configurations, "setlocale(LC_TIME, \$langLocate)") !== false, 'The PARTNER_FLAG setlocale branch is untouched');
$pass(strpos($configurations, 'setlocale(LC_TIME, $langLocate . ".utf8")') !== false, 'The default setlocale branch is untouched');

$specifierOracle = json_decode((string) file_get_contents($root . '/tests/fixtures/legacy-strftime-oracle.json'), true, 512, JSON_THROW_ON_ERROR);
$oracle = json_decode((string) file_get_contents($root . '/tests/fixtures/legacy-strftime-locale-oracle.json'), true, 512, JSON_THROW_ON_ERROR);

$pass(isset($oracle['_note']) && strpos($oracle['_note'], 'evidence only') !== false, 'The locale oracle declares itself evidence only');
$pass(($oracle['runtime']['phpOs'] ?? null) === 'Windows', 'The locale oracle was captured on the acceptance runtime (found ' . ($oracle['runtime']['phpOs'] ?? 'nothing') . ')');
$pass(($oracle['runtime']['phpVersion'] ?? null) === PHP_VERSION, 'The locale oracle was captured under the active PHP runtime (' . ($oracle['runtime']['phpVersion'] ?? '?') . ')');
$pass(($oracle['runtime']['timezoneUsed'] ?? null) === 'UTC', 'The capture pinned the timezone to UTC');
$pass(($specifierOracle['runtime']['phpVersion'] ?? null) === '8.1.10', 'The frozen specifier oracle remains pinned to PHP 8.1.10');
$pass(count($oracle['dateFormats']) === 17, 'All seventeen shipped date formats were captured (' . count($oracle['dateFormats']) . ')');
$pass(count($oracle['translatedMasks']) === 17, 'All seventeen derived strftime masks were recorded');
$pass(($oracle['translatedMasks']['ID_DATE_FORMAT_1']['strftimeMask'] ?? null) === '%Y-%m-%d %H:%M:%S', 'The default mask derives as %Y-%m-%d %H:%M:%S');
$pass(($oracle['translatedMasks']['ID_DATE_FORMAT_17']['strftimeMask'] ?? null) === '%d [xx] %B [xx] %Y', 'The Spanish long mask derives with the [xx] placeholder');

$expectedLocales = ['windows_en' => 'EST', 'windows_es' => 'ESN', 'windows_pt' => 'PTB', 'glibc_en' => 'en_US', 'glibc_es' => 'es_ES', 'glibc_pt' => 'pt_BR', 'c_locale' => 'C'];
foreach ($expectedLocales as $name => $requested) {
    $pass(($oracle['locales'][$name]['requested'] ?? null) === $requested, 'Locale probed: ' . $name . ' => ' . $requested);
}
$pass(($oracle['acceptedLocales'] ?? []) !== [], 'The runtime accepted at least one locale (' . implode(' ', $oracle['acceptedLocales'] ?? []) . ')');

$incomplete = [];
foreach ($oracle['acceptedLocales'] as $name) {
    $row = $oracle['locales'][$name];
    if (count($row['masks']) !== 17 || count($row['monthNames']) !== 12 || count($row['dayNames']) !== 7) {
        $incomplete[] = $name;
    }
}
$pass($incomplete === [], 'Every accepted locale carries 17 masks, 12 month names and 7 day names');

$badHex = [];
foreach ($oracle['acceptedLocales'] as $name) {
    foreach ($oracle['locales'][$name]['masks'] as $id => $stamps) {
        foreach ($stamps as $stamp => $capture) {
            $hex = $capture['hex'] ?? null;
            if ($hex === null) {
                continue;
            }
            // The raw single-byte rendering is never stored, so the invariant
            // is between the hexadecimal bytes and their ISO-8859-1 lift.
            $decoded = preg_match('~^([0-9a-f]{2})*$~', $hex) === 1 ? hex2bin($hex) : false;
            if ($decoded === false || utf8_encode($decoded) !== (string) ($capture['utf8'] ?? '')) {
                $badHex[] = $name . '/' . $id . '/' . $stamp;
            }
        }
    }
}
$pass($badHex === [], 'Every captured rendering carries portable, self-consistent byte evidence' . ($badHex === [] ? '' : ': ' . implode(' ', array_slice($badHex, 0, 5))));

$generator = PhpSourceScanner::read($root . '/tests/tools/generate-strftime-locale-oracle.php');
$pass(PhpSourceScanner::matchCount(PhpSourceScanner::codeOnlySource($generator), '(?<![\w$>-])file_put_contents\s*\(') === 1, 'The generator writes exactly one file');
$pass(substr_count($generator, 'legacy-strftime-locale-oracle.json') === 1, 'The generator only targets its own fixture');

echo '[SUMMARY] ' . $checks . ' U-2.4.1 preflight checks passed.' . PHP_EOL;
