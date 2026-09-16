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
$pass(PHP_VERSION_ID >= 80100 && PHP_VERSION_ID < 80300, 'PHP 8.1/8.2 target (' . PHP_VERSION . ')');
$extensions = ['json', 'mbstring', 'pcre', 'tokenizer'];
$missing = array_values(array_filter($extensions, static fn (string $extension): bool => !extension_loaded($extension)));
$pass($missing === [], $missing === [] ? 'U-2.1 extensions: json, mbstring, pcre, tokenizer' : 'Missing U-2.1 extensions: ' . implode(', ', $missing));

$required = [
    'tests/Support/CompatibilityLedger.php',
    'tests/fixtures/deprecation-budget.json',
    'tests/fixtures/php8-compatibility.json',
    'tests/unit/Compatibility/DeprecatedApiBudgetTest.php',
    'tests/unit/Compatibility/CodeRegionCountingTest.php',
    'tests/unit/Compatibility/PhpEightRemovedConstructsTest.php',
    'tests/unit/Compatibility/StringInterpolationCompatibilityTest.php',
    'tests/unit/Compatibility/ErrorMessageCompatibilityTest.php',
    'workflow/engine/src/ProcessMaker/Model/Delegation.php',
    'workflow/engine/src/ProcessMaker/Model/ProcessVariables.php',
    'workflow/engine/classes/Padl.php',
    'tests/tools/phpunit-9.5.8.phar',
];
foreach ($required as $relativeFile) {
    $pass(is_file($root . '/' . $relativeFile), 'Required U-2.1 file: ' . $relativeFile);
}
require_once $root . '/tests/bootstrap.php';
$decode = static function (string $file): array {
    $contents = file_get_contents($file);
    if ($contents === false) {
        throw new RuntimeException('Unable to read ' . $file);
    }
    return json_decode($contents, true, 512, JSON_THROW_ON_ERROR);
};
$budget = $decode($root . '/tests/fixtures/deprecation-budget.json');
$compatibility = $decode($root . '/tests/fixtures/php8-compatibility.json');
$pass($budget['scope'] === $compatibility['scope'], 'Both compatibility ledgers scan the same scope');
$pass(array_keys($budget['budgets']) === array_keys($budget['codeBudgets']), 'Every textual budget has an executable-code budget');
$pass($compatibility['zeroLocked'] !== [], 'PHP 8 zero-lock map is not empty');
$pass($compatibility['ratchet'] !== [], 'PHP 8 deprecation ratchet is not empty');
$files = Tests\Support\CompatibilityLedger::phpFiles($compatibility['scope']);
$pass(count($files) >= 1000, 'Compatibility scope contains at least 1000 PHP files (' . count($files) . ')');
if (count($files) !== (int) $compatibility['filesScanned']) {
    echo '[NOTE] PHP file count differs from source fixture: expected ' . (int) $compatibility['filesScanned'] . ', found ' . count($files) . '.' . PHP_EOL;
}
$delegation = Tests\Support\PhpSourceScanner::read($root . '/workflow/engine/src/ProcessMaker/Model/Delegation.php');
$processVariables = Tests\Support\PhpSourceScanner::read($root . '/workflow/engine/src/ProcessMaker/Model/ProcessVariables.php');
$padl = Tests\Support\PhpSourceScanner::read($root . '/workflow/engine/classes/Padl.php');
$pass(substr_count($delegation, '$join->where(\'TASK.TAS_TITLE\', \'LIKE\', "%{$search}%");') === 1, 'Delegation task-title interpolation is clean');
$pass(substr_count($delegation, '$join->where(\'APP_DELEGATION.DEL_TITLE\', \'LIKE\', "%{$search}%");') === 1, 'Delegation title interpolation is clean');
$pass(substr_count($delegation, '$item[\'APP_STATUS_LABEL\'] = G::LoadTranslation("ID_{$item[\'APP_STATUS\']}");') === 1, 'Delegation status interpolation is clean');
$pass(substr_count($processVariables, '$query->where(\'VAR_NAME\', \'LIKE\', "{$search}%");') === 1, 'ProcessVariables interpolation is clean');
$pass(substr_count($padl, '$lastError = error_get_last();') === 2, 'Padl reads both last errors');
$pass(substr_count($padl, '$lastErrorMessage = isset($lastError[\'message\']) ? $lastError[\'message\'] : \'\';') === 2, 'Padl safely handles missing errors');
$pass(strpos($padl, '$php_errormsg') === false, 'Padl has no removed $php_errormsg');
foreach ([$delegation, $processVariables, $padl] as $source) {
    $pass(Tests\Support\PhpSourceScanner::countDollarBraceInterpolations($source) === 0, 'Changed engine file has no deprecated interpolation');
}
foreach ($compatibility['zeroLocked'] as $family => $definition) {
    $hits = Tests\Support\CompatibilityLedger::locate($compatibility['scope'], (string) $definition['pattern'], 1);
    $pass($hits === [], $family . ' remains zero-locked in executable PHP');
}
foreach ($compatibility['ratchet'] as $family => $definition) {
    $limit = (int) $definition['max'];
    $hits = Tests\Support\CompatibilityLedger::locate($compatibility['scope'], (string) $definition['pattern'], $limit + 1);
    $pass(count($hits) <= $limit, $family . ' executable-code ratchet <= ' . $limit);
}
foreach ($budget['codeBudgets'] as $family => $limit) {
    $hits = Tests\Support\CompatibilityLedger::locate($budget['scope'], (string) $budget['patterns'][$family], (int) $limit + 1);
    $pass(count($hits) <= (int) $limit, $family . ' executable-code budget <= ' . (int) $limit);
}
$interpolations = Tests\Support\CompatibilityLedger::dollarBraceInterpolations($compatibility['scope']);
$pass($interpolations['count'] <= (int) $compatibility['stringInterpolation']['dollarBraceMax'], 'Deprecated interpolation budget is zero');
echo '[SUMMARY] ' . $checks . ' U-2.1 preflight checks passed.' . PHP_EOL;
