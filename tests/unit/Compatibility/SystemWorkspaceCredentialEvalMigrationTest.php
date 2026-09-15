<?php

declare(strict_types=1);

namespace Tests\Unit\Compatibility;

use PHPUnit\Framework\TestCase;
use ProcessMaker\Core\System;
use Tests\Support\CompatibilityLedger;
use Tests\Support\PhpSourceScanner;

require_once PM_TEST_ROOT . '/workflow/engine/src/ProcessMaker/Core/System.php';

final class SystemWorkspaceCredentialEvalMigrationTest extends TestCase
{
    private const TARGET = 'workflow/engine/src/ProcessMaker/Core/System.php';
    private const EVAL_PATTERN = '(?<![\\w$>-])eval\\s*\\(';

    private static function source(): string
    {
        return PhpSourceScanner::read(PM_TEST_ROOT . '/' . self::TARGET);
    }

    private static function code(): string
    {
        return PhpSourceScanner::codeOnlySource(self::source());
    }

    private static function budget(): array
    {
        return json_decode((string) file_get_contents(PM_TEST_ROOT . '/tests/fixtures/deprecation-budget.json'), true, 512, JSON_THROW_ON_ERROR);
    }

    private static function inventory(): array
    {
        return json_decode((string) file_get_contents(PM_TEST_ROOT . '/tests/fixtures/eval-inventory.json'), true, 512, JSON_THROW_ON_ERROR);
    }

    public function testSystemContainsNoExecutableEval(): void
    {
        self::assertSame(0, PhpSourceScanner::matchCount(self::code(), self::EVAL_PATTERN));
        self::assertStringNotContainsString('eval($this->getDatabaseCredentials', self::source());
    }

    public function testWorkspaceCredentialLoopUsesParserAndAllowList(): void
    {
        $raw = self::source();
        self::assertStringContainsString('$databaseCredentials = $this->getDatabaseCredentials(PATH_DB . $sObject . PATH_SEP . \'db.php\');', $raw);
        self::assertStringContainsString('foreach ($databaseCredentials as $credentialName => $credentialValue)', $raw);
        self::assertStringContainsString("preg_match('/^DB_(?:ADAPTER|HOST|NAME|USER|PASS|RBAC_(?:HOST|NAME|USER|PASS)|REPORT_(?:HOST|NAME|USER|PASS))$/', \$credentialName)", $raw);
        self::assertStringContainsString('${$credentialName} = $credentialValue;', $raw);
    }

    public function testCredentialParserExtractsQuotedDefinitions(): void
    {
        $system = (new \ReflectionClass(System::class))->newInstanceWithoutConstructor();
        $file = tempnam(sys_get_temp_dir(), 'pm-u38-db-');
        self::assertNotFalse($file);
        file_put_contents($file, "<?php\n"
            . "define ('DB_ADAPTER', 'mysql' );\n"
            . "define('DB_HOST', 'localhost:3306');\n"
            . "define('DB_PASS', 'pa\\'ss');\n"
            . "define(\"DB_NAME\", \"wf\");\n");
        try {
            $parsed = $system->getDatabaseCredentials($file);
        } finally {
            @unlink($file);
        }
        self::assertSame('mysql', $parsed['DB_ADAPTER']);
        self::assertSame('localhost:3306', $parsed['DB_HOST']);
        self::assertSame("pa'ss", $parsed['DB_PASS']);
        self::assertSame('wf', $parsed['DB_NAME']);
    }

    public function testCredentialParserNoLongerGeneratesPhpAssignments(): void
    {
        $raw = self::source();
        self::assertStringContainsString('PREG_SET_ORDER', $raw);
        self::assertStringContainsString('$credentials[$match[1]] = stripcslashes($match[3]);', $raw);
        self::assertStringNotContainsString("\$sContent = str_replace('define', '', \$sContent);", $raw);
    }

    public function testEvalRatchetAndInventoryAreLowered(): void
    {
        $budget = self::budget();
        $counts = CompatibilityLedger::counts($budget['scope'], ['eval' => self::EVAL_PATTERN]);
        $inventory = self::inventory();
        self::assertSame(0, $counts['eval']['textual']);
        self::assertSame(0, $counts['eval']['code']);
        self::assertSame(0, $budget['budgets']['eval']);
        self::assertSame(0, $budget['codeBudgets']['eval']);
        self::assertArrayHasKey('_noteU38', $budget);
        self::assertSame(0, $inventory['summary']['executableFiles']);
        self::assertSame(0, $inventory['categories']['core_bootstrap_dynamic_runtime']['count']);
        self::assertArrayNotHasKey(self::TARGET, $inventory['perFileExecutable']);
    }
}
