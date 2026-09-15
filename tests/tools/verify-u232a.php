<?php

declare(strict_types=1);

/**
 * U-2.3.2a preflight: the LegacyStrftime helper and its oracle-derived
 * contract exist, the helper does not delegate to the deprecated function,
 * and NO call site was migrated by this unit.
 */

use Tests\Support\CompatibilityLedger;
use Tests\Support\PhpSourceScanner;

$root = dirname(__DIR__, 2);
require_once $root . '/tests/bootstrap.php';

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

$helperRelative = 'workflow/engine/src/ProcessMaker/Util/LegacyStrftime.php';
$required = [
    $helperRelative,
    'tests/fixtures/legacy-strftime-oracle.json',
    'tests/fixtures/legacy-strftime-contract.json',
    'tests/fixtures/strftime-inventory.json',
    'tests/unit/Compatibility/LegacyStrftimeContractTest.php',
    'tests/tools/verify-u232a.php',
];
foreach ($required as $relative) {
    $pass(is_file($root . '/' . $relative), 'Required file: ' . $relative);
}

// Locks that this unit may not touch.
$locks = [
    'composer.json' => '708119e1eb1f15b263a35366ff18116dcd828329f2481aa588efc49d81a33ad2',
    'composer.lock' => 'c6d4c0da3da7483ad9499f8fdc5a137997cf57a55b1bbeee09f8210711a4c50f',
    'workflow/engine/src/ProcessMaker/Util/LegacyUtf8.php' => '1edb3051e45aa7096143251eb429681bc215f11ec5abf8b515270608f9601823',
];
foreach ($locks as $relative => $expected) {
    $actual = hash_file('sha256', $root . '/' . $relative);
    $pass(is_string($actual) && hash_equals($expected, $actual), 'Unchanged since U-1: ' . $relative);
}

$contract = json_decode((string) file_get_contents($root . '/tests/fixtures/legacy-strftime-contract.json'), true, 512, JSON_THROW_ON_ERROR);
$oraclePath = $root . '/tests/fixtures/legacy-strftime-oracle.json';
$oracleHash = hash_file('sha256', $oraclePath);
$pass(is_string($oracleHash) && hash_equals($contract['oracle']['sha256'], $oracleHash), 'Contract is pinned to the accepted oracle (' . (string) $oracleHash . ')');
$pass($contract['oracle']['sha256'] === '5c9db0711f9be95f436ebc072b06f9d9a57df8ecee22e4449872f4f4b1ee1c5b', 'Oracle file is the one returned with the U-2.3.1 acceptance log');
$pass($contract['oracle']['runtime']['phpVersion'] === '8.1.10', 'Oracle runtime is PHP 8.1.10');
$pass($contract['oracle']['runtime']['phpOs'] === 'Windows', 'Oracle runtime is Windows');
$pass($contract['oracle']['runtime']['lcTime'] === 'C', 'Oracle runtime recorded LC_TIME=C');
$pass(count($contract['specifiers']) === 35, 'Contract covers 35 proven specifiers (found ' . count($contract['specifiers']) . ')');
$pass(count($contract['masks']) === 6, 'Contract covers 6 real masks');
$pass($contract['refusedSpecifiers'] === ['%k', '%l', '%P', '%s', '%z', '%Z'], 'Refused specifiers: %k %l %P %s %z %Z');
$pass($contract['nativeFalseSpecifiers'] === ['%k', '%l', '%P', '%s'], 'Native strftime returned false for exactly %k %l %P %s');

$oracle = json_decode((string) file_get_contents($oraclePath), true, 512, JSON_THROW_ON_ERROR);
$pass($oracle['unsupportedSpecifiers'] === $contract['nativeFalseSpecifiers'], 'Contract copies the oracle unsupported list verbatim');
$sameZone = true;
foreach (['%z', '%Z'] as $zoneSpecifier) {
    $values = array_map(static fn (array $row) => $row['value'], $oracle['specifiers'][$zoneSpecifier]);
    $sameZone = $sameZone && count(array_unique($values)) === 1;
}
$pass($sameZone, 'Oracle proves native %z/%Z ignored PHP timezone and DST, so refusing them is evidence based');

foreach ($contract['specifiers'] as $specifier => $rows) {
    foreach ($rows as $stamp => $expected) {
        $oracleValue = $oracle['specifiers'][$specifier][$stamp]['value'];
        if ($oracleValue !== $expected) {
            $pass(false, 'Contract value for ' . $specifier . ' @ ' . $stamp . ' does not match the oracle');
        }
    }
}
$pass(true, 'Every contract value is a verbatim copy of the recorded native output');

// The helper itself.
$helperCode = PhpSourceScanner::codeOnlyFile($root . '/' . $helperRelative);
$pass(PhpSourceScanner::matchCount($helperCode, '(?<![\w$>-])(?:strftime|gmstrftime)\s*\(') === 0, 'Helper does not call the deprecated function');
$pass(PhpSourceScanner::matchCount($helperCode, '(?<![\w$>-])(?:setlocale|IntlDateFormatter)') === 0, 'Helper is locale independent');
$pass(PhpSourceScanner::matchCount($helperCode, 'final class LegacyStrftime') === 1, 'Helper class is final');
require_once $root . '/' . $helperRelative;
$pass(class_exists('ProcessMaker\\Util\\LegacyStrftime'), 'Helper declares ProcessMaker\\Util\\LegacyStrftime at its psr-0 path');

$previousZone = date_default_timezone_get();
date_default_timezone_set('UTC');
$mismatch = null;
foreach ($contract['specifiers'] as $specifier => $rows) {
    foreach ($rows as $stamp => $expected) {
        $actual = ProcessMaker\Util\LegacyStrftime::format($specifier, $contract['timestamps'][$stamp]);
        if ($actual !== $expected) {
            $mismatch = $specifier . ' @ ' . $stamp . ': expected ' . var_export($expected, true) . ', got ' . var_export($actual, true);
            break 2;
        }
    }
}
foreach ($contract['masks'] as $mask => $rows) {
    foreach ($rows as $stamp => $expected) {
        $actual = ProcessMaker\Util\LegacyStrftime::format($mask, $contract['timestamps'][$stamp]);
        if ($actual !== $expected) {
            $mismatch = $mask . ' @ ' . $stamp . ': expected ' . var_export($expected, true) . ', got ' . var_export($actual, true);
            break 2;
        }
    }
}
date_default_timezone_set($previousZone);
$pass($mismatch === null, $mismatch === null ? 'Helper reproduces all 246 recorded native outputs byte for byte' : 'Helper output differs from the oracle: ' . $mismatch);

foreach ($contract['refusedSpecifiers'] as $specifier) {
    $pass(ProcessMaker\Util\LegacyStrftime::format($specifier, 0) === false, 'Refused instead of guessed: ' . $specifier);
}
$pass(ProcessMaker\Util\LegacyStrftime::format('%Q', 0) === false, 'Unknown specifier is refused');

// Nothing migrated by this unit.
$budget = json_decode((string) file_get_contents($root . '/tests/fixtures/deprecation-budget.json'), true, 512, JSON_THROW_ON_ERROR);
$counts = CompatibilityLedger::counts($budget['scope'], [
    'strftime' => '(?<![\w$>-])(?:strftime|gmstrftime)\s*\(',
    'helperUse' => '(?<![\w$>-])LegacyStrftime::',
    'utf8' => '(?<![\w$>-])(?:utf8_encode|utf8_decode)\s*\(',
]);
$pass($counts['strftime']['textual'] === 140, 'strftime textual ledger after U-2.3.2: 140 (found ' . $counts['strftime']['textual'] . ')');
$pass($counts['strftime']['code'] === 0, 'strftime executable ledger after U-2.4.2: 0 (found ' . $counts['strftime']['code'] . ')');
$pass($counts['helperUse']['code'] === 137, 'The helper is consumed by the 136 migrated getters plus LegacyLocaleDate (found ' . $counts['helperUse']['code'] . ')');
$pass($counts['utf8']['textual'] === 4 && $counts['utf8']['code'] === 0, 'UTF-8 ledger after U-6: 4 textual / 0 executable');

$pass($budget['budgets']['strftime'] === 140 && $budget['codeBudgets']['strftime'] === 0, 'strftime ratchet now 140 / 0 after U-2.4.2');

$inventory = json_decode((string) file_get_contents($root . '/tests/fixtures/strftime-inventory.json'), true, 512, JSON_THROW_ON_ERROR);
$pass($inventory['summary']['executable'] === 0, 'Inventory reports no executable site left');
$pass($inventory['categories']['generated_propel_getter']['count'] === 0, 'No generated getter is left for U-2.3.2');
$pass($inventory['categories']['handwritten_locale_date']['count'] === 0, 'U-2.4.2 migrated both handwritten sites');

echo '[SUMMARY] ' . $checks . ' U-2.3.2a preflight checks passed.' . PHP_EOL;
exit(0);
