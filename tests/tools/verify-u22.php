<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
$phpunitPhar = $root . '/tests/tools/phpunit-9.5.8.phar';
$phpunitPharSha256 = '11f27cf3f9522241fe234e9bf5813667207a074ac92089aac26d502ffc5e9517';
$checks = 0;
$pass = static function (bool $condition, string $message) use (&$checks): void {
    if (!$condition) {
        fwrite(STDERR, '[FAIL] ' . $message . PHP_EOL);
        exit(1);
    }
    ++$checks;
    echo '[PASS] ' . $message . PHP_EOL;
};

if (in_array('--phar-only', $argv, true)) {
    $pass(extension_loaded('phar'), 'Required extension: phar');
    $pass(is_file($phpunitPhar), 'Required file: tests/tools/phpunit-9.5.8.phar');
    $actual = hash_file('sha256', $phpunitPhar);
    $pass(is_string($actual) && hash_equals($phpunitPharSha256, $actual), 'Bundled official PHPUnit 9.5.8 PHAR SHA-256 (' . (string) $actual . ')');
    echo '[SUMMARY] ' . $checks . ' PHPUnit PHAR integrity checks passed.' . PHP_EOL;
    exit(0);
}

$pass(PHP_SAPI === 'cli', 'CLI runtime');
$pass(PHP_VERSION_ID >= 80100 && PHP_VERSION_ID < 80200, 'PHP 8.1.x target (' . PHP_VERSION . ')');
$extensions = ['json', 'mbstring', 'tokenizer'];
$missing = array_values(array_filter($extensions, static fn (string $extension): bool => !extension_loaded($extension)));
$pass($missing === [], $missing === [] ? 'U-2.2.1 extensions: json, mbstring, tokenizer' : 'Missing U-2.2.1 extensions: ' . implode(', ', $missing));

$required = [
    'workflow/engine/src/ProcessMaker/Util/LegacyUtf8.php',
    'tests/fixtures/legacy-utf8-contract.json',
    'tests/unit/Compatibility/LegacyUtf8ContractTest.php',
    'tests/tools/verify-u22.php',
    'tests/tools/run-u22-checks.cmd',
    'tests/tools/README-FA.md',
    'tests/tools/phpunit-9.5.8.phar',
];
foreach ($required as $relativeFile) {
    $pass(is_file($root . '/' . $relativeFile), 'Required U-2.2.1 file: ' . $relativeFile);
}

$pass(hash_file('sha256', $root . '/composer.json') === 'b3f0ff9a9882690f210fec2c8106175b47bed82f0aea1de035873a2d12295f0a', 'composer.json is unchanged from accepted U-2.1');
$pass(hash_file('sha256', $root . '/composer.lock') === '085ed0f8c619f302684dd8daba5dd65b5a47b4572aaf092731fefea30e15418c', 'composer.lock is unchanged from accepted U-2.1');

$decodeJson = static function (string $file): array {
    $contents = file_get_contents($file);
    if ($contents === false) {
        throw new RuntimeException('Unable to read ' . $file);
    }

    return json_decode($contents, true, 512, JSON_THROW_ON_ERROR);
};
$fixture = $decodeJson($root . '/tests/fixtures/legacy-utf8-contract.json');
$pass($fixture['schemaVersion'] === 1, 'Legacy UTF-8 fixture schema version');
$pass($fixture['encoding'] === 'lowercase hexadecimal bytes', 'Fixture stores binary values as portable hexadecimal');
$encodeCases = $fixture['singleByteEncodeCases'];
$pass(count($encodeCases) === 256, 'Fixture contains all 256 one-byte encode cases');
$expectedInputs = array_map(static fn (int $byte): string => sprintf('%02x', $byte), range(0, 255));
$pass(array_column($encodeCases, 'inputHex') === $expectedInputs, 'Fixture one-byte inputs are complete, unique, and ordered');
$expectedEncodeValues = [];
foreach (range(0, 255) as $byte) {
    $expectedEncodeValues[] = $byte < 0x80
        ? sprintf('%02x', $byte)
        : sprintf('%02x%02x', 0xC0 | ($byte >> 6), 0x80 | ($byte & 0x3F));
}
$pass(array_column($encodeCases, 'expectedHex') === $expectedEncodeValues, 'Fixture one-byte expectations follow the frozen ISO-8859-1 mapping');

$decodeCases = $fixture['decodeCases'];
$pass(count($decodeCases) >= 35, 'Fixture contains a broad curated decode corpus (' . count($decodeCases) . ' cases)');
$names = array_column($decodeCases, 'name');
$pass(count($names) === count(array_unique($names)), 'Fixture decode case names are unique');
$categories = array_values(array_unique(array_column($decodeCases, 'category')));
sort($categories, SORT_STRING);
$pass($categories === ['invalid-continuation', 'out-of-range', 'overlong', 'stray-continuation', 'surrogate', 'truncated', 'unmappable', 'valid-latin1'], 'Fixture covers every required decode category');
$hexIsValid = static function (string $hex): bool {
    return strlen($hex) % 2 === 0 && preg_match('/\A[0-9a-f]*\z/', $hex) === 1;
};
$allHexValid = true;
foreach ($encodeCases as $case) {
    $allHexValid = $allHexValid && $hexIsValid($case['inputHex']) && $hexIsValid($case['expectedHex']);
}
foreach ($decodeCases as $case) {
    $allHexValid = $allHexValid && $hexIsValid($case['inputHex']) && $hexIsValid($case['expectedHex']);
}
$pass($allHexValid, 'Every fixture byte string is valid lowercase hexadecimal');

require_once $root . '/tests/bootstrap.php';
$helperFile = $root . '/workflow/engine/src/ProcessMaker/Util/LegacyUtf8.php';
$helperSource = Tests\Support\PhpSourceScanner::read($helperFile);
token_get_all($helperSource, TOKEN_PARSE);
$pass(strpos($helperSource, 'declare(strict_types=1);') !== false, 'Compatibility helper enables strict types');
require_once $helperFile;
$reflection = new ReflectionClass(ProcessMaker\Util\LegacyUtf8::class);
$pass($reflection->isFinal(), 'Compatibility helper is final');
foreach (['encode', 'decode'] as $methodName) {
    $method = $reflection->getMethod($methodName);
    $pass($method->isPublic() && $method->isStatic(), 'Compatibility helper method is public static: ' . $methodName);
    $pass((string) $method->getReturnType() === 'string', 'Compatibility helper method returns string: ' . $methodName);
}
$forbiddenCalls = ['utf8_encode', 'utf8_decode', 'mb_convert_encoding', 'iconv', 'mb_substitute_character'];
$forbiddenFound = [];
foreach ($forbiddenCalls as $function) {
    if (preg_match('/\b' . preg_quote($function, '/') . '\s*\(/i', $helperSource) === 1) {
        $forbiddenFound[] = $function;
    }
}
$pass($forbiddenFound === [], 'Helper has no native, iconv, mbstring, or mutable-state conversion dependency');
$pass(ProcessMaker\Util\LegacyUtf8::encode("\x00\x7F\x80\xFF") === "\x00\x7F\xC2\x80\xC3\xBF", 'Helper encode boundary smoke contract');
$pass(ProcessMaker\Util\LegacyUtf8::decode("\x00\x7F\xC2\x80\xC3\xBF") === "\x00\x7F\x80\xFF", 'Helper decode boundary smoke contract');
$pass(ProcessMaker\Util\LegacyUtf8::decode("\xE2\x82A") === '?A', 'Helper malformed-sequence advancement smoke contract');

$compatibility = $decodeJson($root . '/tests/fixtures/php8-compatibility.json');
$utf8Pattern = '\\butf8_(?:encode|decode)\\s*\\(';
$encodePattern = '\\butf8_encode\\s*\\(';
$decodePattern = '\\butf8_decode\\s*\\(';
$utf8Hits = Tests\Support\CompatibilityLedger::locate($compatibility['scope'], $utf8Pattern, 20);
$encodeHits = Tests\Support\CompatibilityLedger::locate($compatibility['scope'], $encodePattern, 20);
$decodeHits = Tests\Support\CompatibilityLedger::locate($compatibility['scope'], $decodePattern, 20);
// U-2.2.2 migrated the nine executable encode call sites, U-2.2.3 both executable
// decode call sites, and U-2.3.3 the last one, Configurations.php:582. No executable
// native UTF-8 call site is left in scope.
$pass(count($utf8Hits) === 0, 'No executable legacy UTF-8 call site remains');
$pass(count($encodeHits) === 0, 'No executable native encode call site remains');
$pass(count($decodeHits) === 0, 'No executable native decode call site remains');

$helperReferences = [];
foreach (Tests\Support\CompatibilityLedger::phpFiles($compatibility['scope']) as $file) {
    if (realpath($file) === realpath($helperFile)) {
        continue;
    }
    $source = file_get_contents($file);
    if ($source !== false && strpos($source, 'LegacyUtf8') !== false) {
        $helperReferences[] = $file;
    }
}
$helperRelative = array_map(static fn (string $file): string => Tests\Support\CompatibilityLedger::relative($file), $helperReferences);
sort($helperRelative);
// U-2.2.2 migrated seven production files and U-6 added the eighth,
// workflow/engine/classes/class.pmScript.php (the generated catch block on
// line 479, which is executed through eval()). Configurations.php was the
// ninth until U-2.4.2: its line 582 no longer lifts anything, because
// LegacyLocaleDate returns UTF-8 directly, so it left this list.
$expectedConsumers = [
    'gulliver/system/class.g.php',
    'gulliver/system/class.inputfilter.php',
    'workflow/engine/classes/SpoolRun.php',
    // PHP's sort() is byte-ordered, so the lowercase 'class.' prefix sorts
    // after the uppercase file names in the same directory.
    'workflow/engine/classes/class.pmScript.php',
    'workflow/engine/controllers/pmTablesProxy.php',
    'workflow/engine/methods/events/eventsSetupGraph.php',
    'workflow/engine/methods/users/usersAjax.php',
    'workflow/engine/src/ProcessMaker/EmailOAuth/EmailBase.php',
];
$pass(count($helperReferences) === 8, 'Compatibility helper is consumed by exactly the eight migrated production files (found ' . count($helperReferences) . ')');
$pass($helperRelative === $expectedConsumers, 'Helper consumers are exactly the expected files: ' . implode(', ', $helperRelative));

echo '[SUMMARY] ' . $checks . ' U-2.2.1 preflight checks passed.' . PHP_EOL;
