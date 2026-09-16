<?php

declare(strict_types=1);

/**
 * U-6 preflight: the utf8_encode() that PMScript builds into the generated
 * catch block (workflow/engine/classes/class.pmScript.php:479) and then runs
 * through eval() now emits \ProcessMaker\Util\LegacyUtf8::encode() instead.
 * The UTF-8 textual ratchet drops from 5 to 4; the executable ratchet stays 0
 * because the hit always lived inside a double-quoted string.
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
$pass(PHP_VERSION_ID >= 80100 && PHP_VERSION_ID < 80300, 'PHP 8.1/8.2 target (' . PHP_VERSION . ')');

$required = [
    'workflow/engine/src/ProcessMaker/Util/LegacyUtf8.php',
    'workflow/engine/classes/class.pmScript.php',
    'tests/unit/Compatibility/PmScriptGeneratedStringMigrationTest.php',
    'tests/tools/verify-u6.php',
    'tests/tools/run-u6-checks.cmd',
];
foreach ($required as $relative) {
    $pass(is_file($root . '/' . $relative), 'Required U-6 file: ' . $relative);
}

$pass(hash_file('sha256', $root . '/composer.json') === '708119e1eb1f15b263a35366ff18116dcd828329f2481aa588efc49d81a33ad2', 'Unchanged since U-1: composer.json');
$pass(hash_file('sha256', $root . '/composer.lock') === '9f879af7b047666ee70708741d74521c91925e1b6addd80a9d465b6ea76e9cb3', 'Accepted T-2B rev E Composer lock');
$pass(hash_file('sha256', $root . '/workflow/engine/src/ProcessMaker/Util/LegacyUtf8.php') === '1edb3051e45aa7096143251eb429681bc215f11ec5abf8b515270608f9601823', 'Unchanged since U-2.2.1: LegacyUtf8.php');
$pass(hash_file('sha256', $root . '/workflow/engine/src/ProcessMaker/Util/LegacyStrftime.php') === 'a992b694b354b3b86643e03525ce03864199214732d457934e15dab404fa560d', 'Unchanged since U-2.3.2a: LegacyStrftime.php');

require_once $root . '/tests/bootstrap.php';
require_once $root . '/workflow/engine/src/ProcessMaker/Util/LegacyUtf8.php';

$nativeEncode = '(?<![\w$>-])utf8_encode\s*\(';
$nativeUtf8 = '(?<![\w$>-])(?:utf8_encode|utf8_decode)\s*\(';
$helperEncode = '(?<![\w$>-])LegacyUtf8::encode\s*\(';

$budget = json_decode((string) file_get_contents($root . '/tests/fixtures/deprecation-budget.json'), true, 512, JSON_THROW_ON_ERROR);
$target = 'workflow/engine/classes/class.pmScript.php';
$raw = PhpSourceScanner::read($root . '/' . $target);
$code = PhpSourceScanner::codeOnlySource($raw);

token_get_all($raw, TOKEN_PARSE);
$pass(true, 'PMScript still parses under PHP 8.1');

// String literals are blanked in the code-only projection, so the generated
// snippet can only be checked against the raw source.
$pass(strpos($raw, 'utf8_encode(\$oException->getMessage())') === false, 'The generated catch block no longer emits native utf8_encode()');
$pass(strpos($raw, '\\\\ProcessMaker\\\\Util\\\\LegacyUtf8::encode(\$oException->getMessage())') !== false, 'The generated catch block emits the fully qualified helper call');
$pass(PhpSourceScanner::matchCount($raw, $nativeEncode) === 0, 'No native utf8_encode() remains in PMScript, not even inside a string');
$pass(PhpSourceScanner::matchCount($raw, $helperEncode) === 1, 'Exactly one helper encode call is emitted');

$composer = json_decode((string) file_get_contents($root . '/composer.json'), true, 512, JSON_THROW_ON_ERROR);
$pass(($composer['autoload']['psr-0']['ProcessMaker\\'] ?? null) === 'workflow/engine/src', 'The psr-0 mapping the generated code relies on is intact');

$pass(PhpSourceScanner::matchCount($code, '(?<![\w$>-])eval\s*\(') === 0, 'U-3.17 closes the final PMScript eval() body sites');
$pass(PhpSourceScanner::matchCount($code, 'function\s+executeAndCatchErrors\s*\(') === 1, 'executeAndCatchErrors() still exists exactly once');
$pass(PhpSourceScanner::matchCount($code, '(?<![\w$>-])set_error_handler\s*\(') === 0, 'U-2.8 removed the direct set_error_handler() ledger hit');
$pass(PhpSourceScanner::matchCount($code, '\$this->installTriggerErrorHandler\s*\(') === 1, 'The trigger error handler is still installed through the U-2.8 helper');
$pass(PhpSourceScanner::matchCount($raw, '__ERROR__') === 2, 'Both __ERROR__ usages are untouched');
$pass(strpos($raw, 'catch (Exception \$oException)') !== false, 'The generated catch signature is untouched');

$counts = CompatibilityLedger::counts($budget['scope'], [
    'utf8' => $budget['patterns']['utf8_encode_decode'],
    'strftime' => $budget['patterns']['strftime'],
]);
$pass($counts['utf8']['textual'] === 4, 'Textual UTF-8 ledger after U-6: 4 (found ' . $counts['utf8']['textual'] . ')');
$pass($counts['utf8']['code'] === 0, 'Executable UTF-8 ledger stays at 0');
$pass($budget['budgets']['utf8_encode_decode'] === 4 && $budget['codeBudgets']['utf8_encode_decode'] === 0, 'utf8 ratchet lowered to 4 / 0 by U-6');
$pass(isset($budget['_noteU6']), 'The budget records why the ratchet moved');
$pass($counts['strftime']['textual'] === 140 && $counts['strftime']['code'] === 0, 'strftime ledger after U-2.4.2 (140 / 0)');
$pass($budget['budgets']['strftime'] === 140 && $budget['codeBudgets']['strftime'] === 0, 'strftime ratchet after U-2.4.2 (140 / 0)');

$perFile = [];
foreach (CompatibilityLedger::phpFiles($budget['scope']) as $file) {
    $hits = PhpSourceScanner::matchCount(PhpSourceScanner::read($file), $nativeUtf8);
    if ($hits > 0) {
        $perFile[CompatibilityLedger::relative($file)] = $hits;
    }
}
ksort($perFile);
$expectedPerFile = [
    'gulliver/system/class.g.php' => 1,
    'workflow/engine/classes/model/Translation.php' => 3,
];
$pass($perFile === $expectedPerFile, 'Only comment hits remain: ' . json_encode($perFile));
$pass(CompatibilityLedger::locate($budget['scope'], $budget['patterns']['utf8_encode_decode'], 20) === [], 'The ledger finds no executable UTF-8 call site');

$divergent = 0;
foreach (range(0, 255) as $byte) {
    if (Tests\Support\LegacyUtf8Oracle::encode(chr($byte)) !== ProcessMaker\Util\LegacyUtf8::encode(chr($byte))) {
        ++$divergent;
    }
}
$pass($divergent === 0, 'Helper encode matches the frozen PHP 8.1 oracle for all 256 bytes');
$pass(ProcessMaker\Util\LegacyUtf8::encode("Error en la l\xEDnea 3") === Tests\Support\LegacyUtf8Oracle::encode("Error en la l\xEDnea 3"), 'A latin-1 trigger error message matches the frozen PHP 8.1 oracle');

echo '[SUMMARY] ' . $checks . ' U-6 preflight checks passed.' . PHP_EOL;
