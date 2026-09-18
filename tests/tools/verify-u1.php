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

$pharOnly = in_array('--phar-only', $argv, true);
if ($pharOnly) {
    $pass(extension_loaded('phar'), 'Required extension: phar');
    $pass(is_file($phpunitPhar), 'Required file: tests/tools/phpunit-9.5.8.phar');
    $actualPharSha256 = hash_file('sha256', $phpunitPhar);
    $pass(
        is_string($actualPharSha256) && hash_equals($phpunitPharSha256, $actualPharSha256),
        'Bundled official PHPUnit 9.5.8 PHAR SHA-256 (' . (string) $actualPharSha256 . ')'
    );
    echo '[SUMMARY] ' . $checks . ' PHPUnit PHAR integrity checks passed.' . PHP_EOL;
    exit(0);
}

$pass(PHP_SAPI === 'cli', 'CLI runtime');
$pass(PHP_VERSION_ID >= 80100 && PHP_VERSION_ID < 80400, 'PHP 8.1/8.2/8.3 compatibility target (' . PHP_VERSION . ')');
$xmlExtensions = ['dom', 'libxml', 'xml', 'xmlwriter'];
$missingXmlExtensions = array_values(array_filter(
    $xmlExtensions,
    static fn (string $extension): bool => !extension_loaded($extension)
));
$pass(
    $missingXmlExtensions === [],
    $missingXmlExtensions === []
        ? 'PHPUnit XML extensions: dom, libxml, xml, xmlwriter'
        : 'Missing PHPUnit XML extensions: ' . implode(', ', $missingXmlExtensions)
);
$textExtensions = ['json', 'mbstring', 'tokenizer'];
$missingTextExtensions = array_values(array_filter(
    $textExtensions,
    static fn (string $extension): bool => !extension_loaded($extension)
));
$pass(
    $missingTextExtensions === [],
    $missingTextExtensions === []
        ? 'PHPUnit text/data extensions: json, mbstring, tokenizer'
        : 'Missing PHPUnit text/data extensions: ' . implode(', ', $missingTextExtensions)
);
$pass(extension_loaded('phar'), 'Required extension: phar');

$required = [
    'phpunit.xml',
    'tests/bootstrap.php',
    'tests/Support/PhpSourceScanner.php',
    'tests/Support/CompatibilityLedger.php',
    'tests/fixtures/public-api-surface.json',
    'tests/fixtures/dependency-baseline.json',
    'tests/fixtures/deprecation-budget.json',
    'tests/fixtures/php8-compatibility.json',
    'tests/fixtures/legacy-utf8-contract.json',
    'workflow/engine/src/ProcessMaker/Util/LegacyUtf8.php',
    'tests/tools/verify-u22.php',
    'tests/unit/Architecture/PublicApiCompatibilityTest.php',
    'tests/unit/Bootstrap/RuntimeBaselineTest.php',
    'tests/unit/Compatibility/DeprecatedApiBudgetTest.php',
    'tests/unit/Compatibility/CodeRegionCountingTest.php',
    'tests/unit/Compatibility/PhpEightRemovedConstructsTest.php',
    'tests/unit/Compatibility/StringInterpolationCompatibilityTest.php',
    'tests/unit/Compatibility/ErrorMessageCompatibilityTest.php',
    'tests/unit/Compatibility/LegacyUtf8ContractTest.php',
    'tests/unit/Compatibility/LegacyUtf8CallSiteMigrationTest.php',
    'tests/tools/verify-u222.php',
    'tests/unit/Compatibility/LegacyUtf8DecodeCallSiteMigrationTest.php',
    'tests/tools/verify-u223.php',
    'tests/fixtures/strftime-inventory.json',
    'tests/unit/Compatibility/StrftimeInventoryTest.php',
    'tests/tools/generate-strftime-inventory.php',
    'tests/tools/generate-strftime-oracle.php',
    'tests/tools/verify-u231.php',
    'workflow/engine/src/ProcessMaker/Util/LegacyStrftime.php',
    'tests/fixtures/legacy-strftime-oracle.json',
    'tests/fixtures/legacy-strftime-contract.json',
    'tests/unit/Compatibility/LegacyStrftimeContractTest.php',
    'tests/tools/verify-u232a.php',
    // U-2.3.2 added the generated-getter migration harness.
    'tests/unit/Compatibility/StrftimeCallSiteMigrationTest.php',
    'tests/tools/verify-u232.php',
    'tests/tools/run-u232-checks.cmd',
    'tests/unit/Reproducibility/DependencyLockBaselineTest.php',
    'tests/unit/Util/ArrayUtilBehaviorTest.php',
    'tests/tools/phpunit-9.5.8.phar',
    'tests/unit/Compatibility/StrptimeMigrationTest.php',
    'tests/tools/verify-u25.php',
    'tests/tools/run-u25-checks.cmd',
    'tests/unit/Compatibility/McryptMigrationTest.php',
    'tests/tools/verify-u26.php',
    'tests/tools/run-u26-checks.cmd',
    'tests/unit/Compatibility/FilterSanitizeStringMigrationTest.php',
    'tests/tools/verify-u27.php',
    'tests/tools/run-u27-checks.cmd',
    'tests/unit/Compatibility/SetErrorHandlerMigrationTest.php',
    'tests/tools/verify-u28.php',
    'tests/tools/run-u28-checks.cmd',
    'tests/fixtures/eval-inventory.json',
    'tests/tools/generate-eval-inventory.php',
    'tests/unit/Compatibility/EvalInventoryTest.php',
    'tests/tools/verify-u29.php',
    'tests/tools/run-u29-checks.cmd',
    'tests/unit/Compatibility/PmScriptTriggerEvalMigrationTest.php',
    'tests/tools/verify-u30.php',
    'tests/tools/run-u30-checks.cmd',
    'tests/unit/Compatibility/PmDashletEvalMigrationTest.php',
    'tests/tools/verify-u31.php',
    'tests/tools/run-u31-checks.cmd',
    'tests/unit/Compatibility/DynaformEditorEvalMigrationTest.php',
    'tests/tools/verify-u32.php',
    'tests/tools/run-u32-checks.cmd',
    'tests/unit/Compatibility/WeekendAjaxEvalMigrationTest.php',
    'tests/tools/verify-u33.php',
    'tests/tools/run-u33-checks.cmd',
    'tests/unit/Compatibility/UpgradeSystemAjaxEvalMigrationTest.php',
    'tests/tools/verify-u34.php',
    'tests/tools/run-u34-checks.cmd',
    'tests/unit/Compatibility/FieldsAjaxEvalMigrationTest.php',
    'tests/tools/verify-u35.php',
    'tests/tools/run-u35-checks.cmd',
    'tests/unit/Compatibility/CnnEvalMigrationTest.php',
    'tests/tools/verify-u36.php',
    'tests/tools/run-u36-checks.cmd',
    'tests/unit/Compatibility/SystemGetPathsInstalledEvalMigrationTest.php',
    'tests/tools/verify-u37.php',
    'tests/tools/run-u37-checks.cmd',
    'tests/unit/Compatibility/SystemWorkspaceCredentialEvalMigrationTest.php',
    'tests/tools/verify-u38.php',
    'tests/tools/run-u38-checks.cmd',
    'tests/unit/Compatibility/PublisherEvalMigrationTest.php',
    'tests/tools/verify-u39.php',
    'tests/tools/run-u39-checks.cmd',
    'tests/unit/Compatibility/PagedTableEvalMigrationTest.php',
    'tests/tools/verify-u310.php',
    'tests/tools/run-u310-checks.cmd',
    'tests/unit/Compatibility/XmlFormEvalMigrationTest.php',
    'tests/tools/verify-u311.php',
    'tests/tools/run-u311-checks.cmd',
    'tests/unit/Compatibility/GulliverDirectDispatchEvalMigrationTest.php',
    'tests/tools/verify-u312.php',
    'tests/tools/run-u312-checks.cmd',
    'gulliver/system/class.xmlformSafeExpressionEvaluator.php',
    'tests/unit/Compatibility/GulliverFinalEvalMigrationTest.php',
    'tests/tools/verify-u313.php',
    'tests/tools/run-u313-checks.cmd',
    'tests/unit/Compatibility/DynamicModelDispatchEvalMigrationTest.php',
    'tests/tools/verify-u314.php',
    'tests/tools/run-u314-checks.cmd',
    'tests/unit/Compatibility/AdditionalTablesEvalMigrationTest.php',
    'tests/tools/verify-u315.php',
    'tests/tools/run-u315-checks.cmd',
    'workflow/engine/classes/XMLWhereExpressionEvaluator.php',
    'workflow/engine/src/ProcessMaker/BusinessModel/LegacyArrayLiteralParser.php',
    'tests/unit/Compatibility/ExpressionExecutionClosureTest.php',
    'tests/tools/verify-u316.php',
    'tests/tools/run-u316-checks.cmd',
    'workflow/engine/classes/PMScriptTemporaryExecutionTrait.php',
    'tests/unit/Compatibility/TriggerTemporaryExecutionClosureTest.php',
    'tests/tools/verify-u317.php',
    'tests/tools/run-u317-checks.cmd',
    'tests/unit/Compatibility/ClientEvalClosureTest.php',
    'tests/tools/verify-u318.php',
    'tests/tools/run-u318-checks.cmd',
    'tests/browser/u319-client-runtime.html',
    'tests/tools/run-u319-browser-checks.cmd',
    'tests/unit/Compatibility/BrowserRuntimeHarnessTest.php',
    'tests/tools/verify-u319.php',
    'tests/tools/run-u319-checks.cmd',
    // T-1A/T-1B dual-runner infrastructure.
    'phpunit-10.xml',
    'tests/tools/phpunit-10.phar',
    'tests/tools/phpunit-10.phar.sha256',
    'tests/tools/acquire-phpunit10.ps1',
    'tests/tools/verify-t1a-phpunit10.php',
    'tests/tools/verify-t1b-phpunit10.php',
    'tests/tools/run-t1a-phpunit10-checks.cmd',
    'tests/tools/run-t1a-checks.cmd',
    'tests/tools/run-t1b-phpunit10-checks.cmd',
    'tests/tools/run-t1b-checks.cmd',
    'tests/Support/LegacyUtf8Oracle.php',
    'tests/tools/verify-t2a-php82.php',
    'tests/tools/run-t2a-php82-checks.cmd',
    'tests/tools/run-t2a-checks.cmd',
];
foreach ($required as $relativeFile) {
    $pass(is_file($root . '/' . $relativeFile), 'Required file: ' . $relativeFile);
}

$actualPharSha256 = hash_file('sha256', $phpunitPhar);
$pass(
    is_string($actualPharSha256) && hash_equals($phpunitPharSha256, $actualPharSha256),
    'Bundled official PHPUnit 9.5.8 PHAR SHA-256 (' . (string) $actualPharSha256 . ')'
);

$iterator = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($root . '/tests', RecursiveDirectoryIterator::SKIP_DOTS)
);
$parsed = 0;
foreach ($iterator as $file) {
    if ($file->isFile() && strtolower($file->getExtension()) === 'php') {
        token_get_all((string) file_get_contents($file->getPathname()), TOKEN_PARSE);
        ++$parsed;
    }
}
// U-2.2.2 added tests/unit/Compatibility/LegacyUtf8CallSiteMigrationTest.php
// and tests/tools/verify-u222.php to the harness; U-2.2.3 added
// tests/unit/Compatibility/LegacyUtf8DecodeCallSiteMigrationTest.php and
// tests/tools/verify-u223.php. U-2.3.1 added
// tests/unit/Compatibility/StrftimeInventoryTest.php,
// tests/tools/generate-strftime-inventory.php,
// tests/tools/generate-strftime-oracle.php and tests/tools/verify-u231.php.
// U-2.3.2a added tests/unit/Compatibility/LegacyStrftimeContractTest.php and
// tests/tools/verify-u232a.php (the helper itself lives outside tests/).
// U-2.3.2 added tests/unit/Compatibility/StrftimeCallSiteMigrationTest.php and
// tests/tools/verify-u232.php. U-2.3.3 added
// tests/unit/Compatibility/LegacyUtf8StrftimeCouplingTest.php and
// tests/tools/verify-u233.php. U-6 added
// tests/unit/Compatibility/PmScriptGeneratedStringMigrationTest.php and
// tests/tools/verify-u6.php. U-2.4.1 added
// tests/unit/Compatibility/StrftimeLocaleOracleTest.php,
// tests/tools/generate-strftime-locale-oracle.php and
// tests/tools/verify-u241.php. U-2.4.2 added
// tests/unit/Compatibility/LegacyLocaleDateTest.php and
// tests/tools/verify-u242.php (the locale-aware helper itself lives
// outside tests/). U-2.5 added tests/unit/Compatibility/StrptimeMigrationTest.php
// and tests/tools/verify-u25.php. U-2.6 added tests/unit/Compatibility/McryptMigrationTest.php
// and tests/tools/verify-u26.php. U-2.7 added tests/unit/Compatibility/FilterSanitizeStringMigrationTest.php
// and tests/tools/verify-u27.php. U-2.8 added tests/unit/Compatibility/SetErrorHandlerMigrationTest.php
// and tests/tools/verify-u28.php. U-2.9 added tests/unit/Compatibility/EvalInventoryTest.php,
// tests/tools/generate-eval-inventory.php and tests/tools/verify-u29.php. U-3.0 added
// tests/unit/Compatibility/PmScriptTriggerEvalMigrationTest.php and
// tests/tools/verify-u30.php. U-3.1 added
// tests/unit/Compatibility/PmDashletEvalMigrationTest.php and
// tests/tools/verify-u31.php. U-3.2 added
// tests/unit/Compatibility/DynaformEditorEvalMigrationTest.php and
// tests/tools/verify-u32.php. U-3.3 added
// tests/unit/Compatibility/WeekendAjaxEvalMigrationTest.php and
// tests/tools/verify-u33.php. U-3.4 added
// tests/unit/Compatibility/UpgradeSystemAjaxEvalMigrationTest.php and
// tests/tools/verify-u34.php. U-3.5 added
// tests/unit/Compatibility/FieldsAjaxEvalMigrationTest.php and
// tests/tools/verify-u35.php. U-3.6 added
// tests/unit/Compatibility/CnnEvalMigrationTest.php and
// tests/tools/verify-u36.php. U-3.7 added
// tests/unit/Compatibility/SystemGetPathsInstalledEvalMigrationTest.php and
// tests/tools/verify-u37.php.
// U-3.8 added SystemWorkspaceCredentialEvalMigrationTest.php and verify-u38.php.
// U-3.9 added PublisherEvalMigrationTest.php and verify-u39.php.
// U-3.10 added PagedTableEvalMigrationTest.php and verify-u310.php.
// U-3.11 added XmlFormEvalMigrationTest.php and verify-u311.php.
// U-3.12 added GulliverDirectDispatchEvalMigrationTest.php and verify-u312.php.
// U-3.13 added GulliverFinalEvalMigrationTest.php and verify-u313.php.
// U-3.14 added DynamicModelDispatchEvalMigrationTest.php and verify-u314.php.
// U-3.16 added ExpressionExecutionClosureTest.php and verify-u316.php; U-3.17 added TriggerTemporaryExecutionClosureTest.php and verify-u317.php; U-3.18 added ClientEvalClosureTest.php and verify-u318.php.
// T-1A and T-1B add one verifier each; T-3A adds the PHPUnit 11 verifier.
// Keep the syntax-file ratchet exact.
$pass($parsed === 99, 'All ninety-nine harness PHP files parse under PHP 8.1/8.2/8.3');

$decode = static function (string $file): array {
    $contents = file_get_contents($file);
    if ($contents === false) {
        throw new RuntimeException('Unable to read ' . $file);
    }
    return json_decode($contents, true, 512, JSON_THROW_ON_ERROR);
};

$baseline = $decode($root . '/tests/fixtures/dependency-baseline.json');
$lock = $decode($root . '/composer.lock');
$pass($baseline['contentHash'] === $lock['content-hash'], 'composer.lock content hash matches the U-1 fixture');
$pass(count($lock['packages']) === $baseline['counts']['packages'], 'Locked production package count');
$pass(count($lock['packages-dev']) === $baseline['counts']['packagesDev'], 'Locked development package count');

$configuration = (string) file_get_contents($root . '/phpunit.xml');
$pass(strpos($configuration, 'bootstrap="tests/bootstrap.php"') !== false, 'PHPUnit uses the DB-free bootstrap');
$pass(strpos($configuration, '<directory suffix="Test.php">tests/unit</directory>') !== false, 'PHPUnit unit suite path');

$bootstrap = (string) file_get_contents($root . '/tests/bootstrap.php');
$forbiddenBoot = preg_match('~(?:require|include)(?:_once)?[^;]*(?:bootstrap/app\\.php|artisan)~i', $bootstrap);
$pass($forbiddenBoot === 0, 'Test bootstrap does not boot Laravel or Artisan');

$surface = $decode($root . '/tests/fixtures/public-api-surface.json');
$pass(count($surface['classes']) === 5, 'Five engine API anchors are pinned');
$pass(count($surface['functions']['class.pmFunctions.php']['triggerApi']) === 55, 'Fifty-five PMF trigger functions are pinned');

echo '[SUMMARY] ' . $checks . ' preflight checks passed.' . PHP_EOL;
