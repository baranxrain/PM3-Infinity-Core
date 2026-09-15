<?php

declare(strict_types=1);

/**
 * U-3.6 preflight: ProcessMaker\Util\Cnn parses workspace db.php definitions
 * directly instead of generating assignment code and evaluating it.
 */

use ProcessMaker\Util\Cnn;
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
    'workflow/engine/src/ProcessMaker/Util/Cnn.php',
    'tests/fixtures/deprecation-budget.json',
    'tests/fixtures/eval-inventory.json',
    'tests/unit/Compatibility/CnnEvalMigrationTest.php',
    'tests/tools/verify-u36.php',
    'tests/tools/run-u36-checks.cmd',
];
foreach ($required as $relative) {
    $pass(is_file($root . '/' . $relative), 'Required U-3.6 file: ' . $relative);
}

$pass(hash_file('sha256', $root . '/composer.json') === 'b3f0ff9a9882690f210fec2c8106175b47bed82f0aea1de035873a2d12295f0a', 'Unchanged since U-1: composer.json');
$pass(hash_file('sha256', $root . '/composer.lock') === '085ed0f8c619f302684dd8daba5dd65b5a47b4572aaf092731fefea30e15418c', 'Unchanged since U-1: composer.lock');

require_once $root . '/tests/bootstrap.php';
require_once $root . '/workflow/engine/src/ProcessMaker/Util/Cnn.php';

$target = 'workflow/engine/src/ProcessMaker/Util/Cnn.php';
$pattern = '(?<![\w$>-])eval\s*\(';
$raw = PhpSourceScanner::read($root . '/' . $target);
$code = PhpSourceScanner::codeOnlySource($raw);
$budget = json_decode((string) file_get_contents($root . '/tests/fixtures/deprecation-budget.json'), true, 512, JSON_THROW_ON_ERROR);
$inventory = json_decode((string) file_get_contents($root . '/tests/fixtures/eval-inventory.json'), true, 512, JSON_THROW_ON_ERROR);
$counts = CompatibilityLedger::counts($budget['scope'], ['eval' => $pattern]);

token_get_all($raw, TOKEN_PARSE);
$pass(true, 'Cnn.php parses under PHP 8.1');
$pass(PhpSourceScanner::matchCount($code, $pattern) === 0, 'Cnn.php contains no executable eval() sites');
$pass(strpos($raw, 'eval($phpCode);') === false, 'The old generated-code eval was removed');
$pass(strpos($raw, '$phpCode = preg_replace') === false, 'The old generated assignment code path was removed');
$pass(strpos($raw, '$credentials = $this->parseDatabaseDefinitions($this->dbFile);') !== false, 'prepareDataSources() uses parsed credentials');
$pass(PhpSourceScanner::matchCount($code, 'function\s+parseDatabaseDefinitions\s*\(') === 1, 'parseDatabaseDefinitions() exists once');
$pass(PhpSourceScanner::matchCount($code, 'function\s+databaseDefinition\s*\(') === 1, 'databaseDefinition() exists once');
$pass(strpos($raw, '$credentials[$match[1]] = stripcslashes($match[3]);') !== false, 'Parser decodes quoted define values');
$pass(PhpSourceScanner::matchCount($code, '(?<![\w$>-])preg_match_all\s*\(') === 1, 'Cnn has exactly one preg_match_all() parser');
$pass(PhpSourceScanner::matchCount($code, '(?<![\w$>-])stripcslashes\s*\(') === 1, 'Cnn has exactly one stripcslashes() decoder');
$pass(PhpSourceScanner::matchCount($code, '\$this->databaseDefinition\s*\(') === 15, 'Cnn uses fifteen credential lookups for the three datasources');

$cnn = new Cnn();
$method = (new ReflectionClass($cnn))->getMethod('parseDatabaseDefinitions');
$method->setAccessible(true);
$parsed = $method->invoke($cnn, "<?php\n"
    . "define('DB_ADAPTER', 'mysql');\n"
    . "define('DB_HOST', 'localhost:3306');\n"
    . "define('DB_PASS', 'pa\\'ss');\n"
    . "define(\"DB_NAME\", \"wf\");\n"
);
$pass($parsed['DB_ADAPTER'] === 'mysql', 'Parser extracts single-quoted DB_ADAPTER');
$pass($parsed['DB_HOST'] === 'localhost:3306', 'Parser extracts single-quoted DB_HOST');
$pass($parsed['DB_PASS'] === "pa'ss", 'Parser decodes escaped quote in DB_PASS');
$pass($parsed['DB_NAME'] === 'wf', 'Parser extracts double-quoted DB_NAME');

$pass(($budget['_noteU36'] ?? '') !== '', 'The U-3.6 eval ratchet movement is documented');
$pass($budget['budgets']['eval'] === 0, 'Eval textual budget lowered to 19');
$pass($budget['codeBudgets']['eval'] === 0, 'Eval executable budget lowered to 7');
$pass($counts['eval']['textual'] === 0, 'Live textual eval count is 19');
$pass($counts['eval']['code'] === 0, 'Live executable eval count is 7');

$pass($inventory['summary']['textual'] === 0, 'Inventory textual eval count is 19');
$pass($inventory['summary']['executable'] === 0, 'Inventory executable eval count is 7');
$pass($inventory['summary']['nonExecutable'] === 0, 'Inventory non-executable eval count remains 12');
$pass($inventory['summary']['executableFiles'] === 0, 'Inventory executable file count is 4');
$pass(($inventory['categories']['core_bootstrap_dynamic_runtime']['count'] ?? null) === 0, 'Core bootstrap dynamic runtime category is closed');
$pass(!array_key_exists($target, $inventory['perFileExecutable']), 'Cnn.php is absent from per-file eval inventory');
$remainingFiles = array_values(array_unique(array_column($inventory['executableSites'], 'file')));
$pass(!in_array($target, $remainingFiles, true), 'Cnn.php is absent from executable eval sites');

$pass($budget['budgets']['strftime'] === 140 && $budget['codeBudgets']['strftime'] === 0, 'Strftime ratchet is unchanged');
$pass($budget['budgets']['utf8_encode_decode'] === 4 && $budget['codeBudgets']['utf8_encode_decode'] === 0, 'UTF-8 ratchet is unchanged');

echo '[SUMMARY] ' . $checks . ' U-3.6 preflight checks passed.' . PHP_EOL;
