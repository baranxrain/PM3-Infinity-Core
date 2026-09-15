<?php

declare(strict_types=1);

/**
 * U-2.2.3 preflight: both executable utf8_decode call sites are migrated to
 * ProcessMaker\Util\LegacyUtf8::decode(), the strftime-coupled encode call site
 * stays deferred, and no dependency, schema or public API moved.
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
$pass(PHP_VERSION_ID >= 80100 && PHP_VERSION_ID < 80200, 'PHP 8.1.x target (' . PHP_VERSION . ')');
$extensions = ['json', 'mbstring', 'tokenizer'];
$missing = array_values(array_filter($extensions, static fn (string $extension): bool => !extension_loaded($extension)));
$pass($missing === [], $missing === [] ? 'U-2.2.3 extensions: json, mbstring, tokenizer' : 'Missing U-2.2.3 extensions: ' . implode(', ', $missing));

$required = [
    'workflow/engine/src/ProcessMaker/Util/LegacyUtf8.php',
    'tests/unit/Compatibility/LegacyUtf8CallSiteMigrationTest.php',
    'tests/unit/Compatibility/LegacyUtf8DecodeCallSiteMigrationTest.php',
    'tests/tools/verify-u222.php',
    'tests/tools/verify-u223.php',
    'tests/tools/run-u223-checks.cmd',
];
foreach ($required as $relativeFile) {
    $pass(is_file($root . '/' . $relativeFile), 'Required U-2.2.3 file: ' . $relativeFile);
}

$pass(hash_file('sha256', $root . '/composer.json') === '708119e1eb1f15b263a35366ff18116dcd828329f2481aa588efc49d81a33ad2', 'composer.json is unchanged from accepted U-2.1');
$pass(hash_file('sha256', $root . '/composer.lock') === 'c6d4c0da3da7483ad9499f8fdc5a137997cf57a55b1bbeee09f8210711a4c50f', 'composer.lock is unchanged from accepted U-2.1');
$pass(hash_file('sha256', $root . '/workflow/engine/src/ProcessMaker/Util/LegacyUtf8.php') === '1edb3051e45aa7096143251eb429681bc215f11ec5abf8b515270608f9601823', 'Accepted U-2.2.1 helper is still byte-identical');

require_once $root . '/tests/bootstrap.php';
require_once $root . '/workflow/engine/src/ProcessMaker/Util/LegacyUtf8.php';

$encodePattern = '(?<![\w$>-])utf8_encode\s*\(';
$decodePattern = '(?<![\w$>-])utf8_decode\s*\(';
$helperDecodePattern = '(?<![\w$>-])LegacyUtf8::decode\s*\(';

$migrated = [
    'workflow/engine/classes/SpoolRun.php' => 1,
    'workflow/engine/methods/events/eventsSetupGraph.php' => 1,
];

$totalMigrated = 0;
foreach ($migrated as $relativeFile => $expected) {
    $source = Tests\Support\PhpSourceScanner::read($root . '/' . $relativeFile);
    token_get_all($source, TOKEN_PARSE);
    $projection = Tests\Support\PhpSourceScanner::codeOnlySource($source);
    $pass(Tests\Support\PhpSourceScanner::matchCount($projection, $helperDecodePattern) === $expected, 'Helper decode call count is ' . $expected . ' in ' . $relativeFile);
    $pass(Tests\Support\PhpSourceScanner::matchCount($projection, $decodePattern) === 0, 'No executable native decode remains in ' . $relativeFile);
    $totalMigrated += $expected;
}
$pass($totalMigrated === 2, 'Exactly two decode call sites were migrated');

// The code-only projection blanks string literals, so the array key can only be
// asserted on the raw source. The structural assertion stays on the projection.
$spoolRunRaw = Tests\Support\PhpSourceScanner::read($root . '/workflow/engine/classes/SpoolRun.php');
$spoolRun = Tests\Support\PhpSourceScanner::codeOnlySource($spoolRunRaw);
$pass(Tests\Support\PhpSourceScanner::matchCount($spoolRun, 'LegacyUtf8::decode\(\$this->fileData\[') === 1, 'SpoolRun decodes exactly one fileData argument');
$pass(Tests\Support\PhpSourceScanner::matchCount($spoolRunRaw, 'LegacyUtf8::decode\(\$this->fileData\[\s*.from_name.\s*\]\)') === 1, 'SpoolRun still decodes exactly the sender name argument');
$pass(Tests\Support\PhpSourceScanner::matchCount($spoolRun, 'LegacyUtf8::encode\s*\(') === 2, 'The two U-2.2.2 encode migrations in SpoolRun are intact');

$graph = Tests\Support\PhpSourceScanner::codeOnlySource(Tests\Support\PhpSourceScanner::read($root . '/workflow/engine/methods/events/eventsSetupGraph.php'));
$pass(Tests\Support\PhpSourceScanner::matchCount($graph, 'LegacyUtf8::decode\(\s*G::LoadTranslation\(') === 1, 'GD label still decodes the loaded translation');

$deferred = [
    'workflow/engine/classes/Configurations.php' => [$encodePattern, 0, 'strftime-coupled encode was migrated by U-2.3.3'],
    'workflow/engine/classes/class.pmScript.php' => [$encodePattern, 0, 'PMScript generated string was migrated by U-6'],
];
foreach ($deferred as $relativeFile => [$pattern, $expected, $reason]) {
    $projection = Tests\Support\PhpSourceScanner::codeOnlySource(Tests\Support\PhpSourceScanner::read($root . '/' . $relativeFile));
    $pass(Tests\Support\PhpSourceScanner::matchCount($projection, $pattern) === $expected, $reason);
}

$budget = json_decode((string) file_get_contents($root . '/tests/fixtures/deprecation-budget.json'), true, 512, JSON_THROW_ON_ERROR);
$pass($budget['budgets']['utf8_encode_decode'] === 4, 'Textual UTF-8 budget was ratcheted down to 4 by U-6');
$pass($budget['codeBudgets']['utf8_encode_decode'] === 0, 'Executable UTF-8 budget was ratcheted down to 0');
$hits = Tests\Support\CompatibilityLedger::locate($budget['scope'], $budget['patterns']['utf8_encode_decode'], 20);
$pass(count($hits) === 0, 'Ledger now finds no executable UTF-8 call site: ' . implode(', ', $hits));
$pass($hits === [], 'The last deferred Configurations.php site was migrated by U-2.3.3');

$divergentBytes = 0;
foreach (range(0, 255) as $byte) {
    if (utf8_decode(chr($byte)) !== ProcessMaker\Util\LegacyUtf8::decode(chr($byte))) {
        ++$divergentBytes;
    }
}
$pass($divergentBytes === 0, 'Helper decode matches the native decoder byte-for-byte for all 256 bytes');

$divergentPairs = 0;
for ($lead = 0xC0; $lead <= 0xDF; ++$lead) {
    for ($trail = 0x00; $trail <= 0xFF; ++$trail) {
        $sequence = chr($lead) . chr($trail);
        if (utf8_decode($sequence) !== ProcessMaker\Util\LegacyUtf8::decode($sequence)) {
            ++$divergentPairs;
        }
    }
}
$pass($divergentPairs === 0, 'Helper decode matches the native decoder for all 8192 two-byte sequences');

$allBytes = implode('', array_map('chr', range(0, 255)));
$pass(utf8_decode($allBytes) === ProcessMaker\Util\LegacyUtf8::decode($allBytes), 'Helper decode matches the native decoder for the full byte string');
$pass(ProcessMaker\Util\LegacyUtf8::decode(ProcessMaker\Util\LegacyUtf8::encode($allBytes)) === $allBytes, 'Latin-1 round trip is lossless for every byte');

echo '[SUMMARY] ' . $checks . ' U-2.2.3 preflight checks passed.' . PHP_EOL;
