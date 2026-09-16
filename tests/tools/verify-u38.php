<?php

declare(strict_types=1);

use ProcessMaker\Core\System;
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

$required = [
    'workflow/engine/src/ProcessMaker/Core/System.php',
    'tests/fixtures/deprecation-budget.json',
    'tests/fixtures/eval-inventory.json',
    'tests/unit/Compatibility/SystemWorkspaceCredentialEvalMigrationTest.php',
    'tests/tools/verify-u38.php',
    'tests/tools/run-u38-checks.cmd',
];
foreach ($required as $relative) {
    $pass(is_file($root . '/' . $relative), 'Required U-3.8 file: ' . $relative);
}
$pass(hash_file('sha256', $root . '/composer.json') === '708119e1eb1f15b263a35366ff18116dcd828329f2481aa588efc49d81a33ad2', 'Unchanged since U-1: composer.json');
$pass(hash_file('sha256', $root . '/composer.lock') === '9f879af7b047666ee70708741d74521c91925e1b6addd80a9d465b6ea76e9cb3', 'Accepted T-2B rev E Composer lock');

require_once $root . '/tests/bootstrap.php';
require_once $root . '/workflow/engine/src/ProcessMaker/Core/System.php';
$target = 'workflow/engine/src/ProcessMaker/Core/System.php';
$pattern = '(?<![\\w$>-])eval\\s*\\(';
$raw = PhpSourceScanner::read($root . '/' . $target);
$code = PhpSourceScanner::codeOnlySource($raw);
$budget = json_decode((string) file_get_contents($root . '/tests/fixtures/deprecation-budget.json'), true, 512, JSON_THROW_ON_ERROR);
$inventory = json_decode((string) file_get_contents($root . '/tests/fixtures/eval-inventory.json'), true, 512, JSON_THROW_ON_ERROR);
$counts = CompatibilityLedger::counts($budget['scope'], ['eval' => $pattern]);

token_get_all($raw, TOKEN_PARSE);
$pass(true, 'System.php parses under PHP 8.1');
$pass(PhpSourceScanner::matchCount($code, $pattern) === 0, 'System.php contains no executable eval sites');
$pass(strpos($raw, 'eval($this->getDatabaseCredentials') === false, 'Old workspace credential eval was removed');
$pass(strpos($raw, '$databaseCredentials = $this->getDatabaseCredentials(PATH_DB . $sObject . PATH_SEP . \'db.php\');') !== false, 'Workspace credential loop uses the direct parser');
$pass(strpos($raw, 'foreach ($databaseCredentials as $credentialName => $credentialValue)') !== false, 'Parsed credentials are assigned without PHP execution');
$pass(strpos($raw, "preg_match('/^DB_(?:ADAPTER|HOST|NAME|USER|PASS|RBAC_(?:HOST|NAME|USER|PASS)|REPORT_(?:HOST|NAME|USER|PASS))$/', \$credentialName)") !== false, 'Credential assignment is restricted to legacy DB names');
$pass(strpos($raw, '${$credentialName} = $credentialValue;') !== false, 'Allow-listed credentials retain method-scope assignment semantics');
$pass(strpos($raw, 'PREG_SET_ORDER') !== false, 'Credential parser scans define statements');
$pass(strpos($raw, '$credentials[$match[1]] = stripcslashes($match[3]);') !== false, 'Credential parser decodes quoted values');

$system = (new ReflectionClass(System::class))->newInstanceWithoutConstructor();
$temp = tempnam(sys_get_temp_dir(), 'pm-u38-db-');
$pass($temp !== false, 'Temporary parser fixture created');
file_put_contents($temp, "<?php\ndefine ('DB_ADAPTER', 'mysql' );\ndefine('DB_PASS', 'pa\\'ss');\ndefine(\"DB_NAME\", \"wf\");\n");
$parsed = $system->getDatabaseCredentials($temp);
@unlink($temp);
$pass(($parsed['DB_ADAPTER'] ?? null) === 'mysql', 'Parser reads spaced single-quoted definitions');
$pass(($parsed['DB_PASS'] ?? null) === "pa'ss", 'Parser decodes escaped credentials');
$pass(($parsed['DB_NAME'] ?? null) === 'wf', 'Parser reads double-quoted definitions');

$pass(($budget['_noteU38'] ?? '') !== '', 'The U-3.8 eval ratchet movement is documented');
$pass($budget['budgets']['eval'] === 0, 'Eval textual budget lowered to 19');
$pass($budget['codeBudgets']['eval'] === 0, 'Eval executable budget lowered to 7');
$pass($counts['eval']['textual'] === 0, 'Live textual eval count is 19');
$pass($counts['eval']['code'] === 0, 'Live executable eval count is 7');
$pass($inventory['summary']['textual'] === 0, 'Inventory textual eval count is 19');
$pass($inventory['summary']['executable'] === 0, 'Inventory executable eval count is 7');
$pass($inventory['summary']['nonExecutable'] === 0, 'Inventory non-executable eval count remains 12');
$pass($inventory['summary']['executableFiles'] === 0, 'Inventory executable file count lowered to 4');
$pass(($inventory['categories']['core_bootstrap_dynamic_runtime']['count'] ?? null) === 0, 'Core bootstrap dynamic runtime category closed');
$pass(!array_key_exists($target, $inventory['perFileExecutable']), 'System.php is absent from per-file eval inventory');
$systemSites = array_values(array_filter($inventory['executableSites'], static fn (array $site): bool => $site['file'] === $target));
$pass(count($systemSites) === 0, 'System.php has no listed executable eval site');
$pass($budget['budgets']['strftime'] === 140 && $budget['codeBudgets']['strftime'] === 0, 'Strftime ratchet is unchanged');
$pass($budget['budgets']['utf8_encode_decode'] === 4 && $budget['codeBudgets']['utf8_encode_decode'] === 0, 'UTF-8 ratchet is unchanged');

echo '[SUMMARY] ' . $checks . ' U-3.8 preflight checks passed.' . PHP_EOL;
