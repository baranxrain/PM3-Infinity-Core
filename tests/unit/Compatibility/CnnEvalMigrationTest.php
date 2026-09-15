<?php

declare(strict_types=1);

namespace Tests\Unit\Compatibility;

use PHPUnit\Framework\TestCase;
use ProcessMaker\Util\Cnn;
use ReflectionClass;
use Tests\Support\CompatibilityLedger;
use Tests\Support\PhpSourceScanner;

require_once PM_TEST_ROOT . '/workflow/engine/src/ProcessMaker/Util/Cnn.php';

/**
 * U-3.6: Cnn parses workspace db.php define() statements directly instead of
 * generating assignment code and evaluating it.
 */
final class CnnEvalMigrationTest extends TestCase
{
    private const TARGET = 'workflow/engine/src/ProcessMaker/Util/Cnn.php';
    private const EVAL_PATTERN = '(?<![\w$>-])eval\s*\(';

    private static function source(): string
    {
        return PhpSourceScanner::read(PM_TEST_ROOT . '/' . self::TARGET);
    }

    private static function code(): string
    {
        return PhpSourceScanner::codeOnlySource(self::source());
    }

    /** @return array<string, mixed> */
    private static function budget(): array
    {
        return json_decode((string) file_get_contents(PM_TEST_ROOT . '/tests/fixtures/deprecation-budget.json'), true, 512, JSON_THROW_ON_ERROR);
    }

    /** @return array<string, mixed> */
    private static function inventory(): array
    {
        return json_decode((string) file_get_contents(PM_TEST_ROOT . '/tests/fixtures/eval-inventory.json'), true, 512, JSON_THROW_ON_ERROR);
    }

    public function testCnnContainsNoExecutableEval(): void
    {
        self::assertSame(0, PhpSourceScanner::matchCount(self::code(), self::EVAL_PATTERN));
        self::assertStringNotContainsString('eval($phpCode);', self::source());
        self::assertStringNotContainsString('$phpCode = preg_replace', self::source());
    }

    public function testCnnParsesDatabaseDefinitionsDirectly(): void
    {
        $raw = self::source();

        self::assertStringContainsString('$credentials = $this->parseDatabaseDefinitions($this->dbFile);', $raw);
        self::assertStringContainsString('function parseDatabaseDefinitions($dbFile)', $raw);
        self::assertStringContainsString('preg_match_all', $raw);
        self::assertStringContainsString('$credentials[$match[1]] = stripcslashes($match[3]);', $raw);
        self::assertSame(1, PhpSourceScanner::matchCount(self::code(), '(?<![\w$>-])preg_match_all\s*\('));
        self::assertSame(1, PhpSourceScanner::matchCount(self::code(), '(?<![\w$>-])stripcslashes\s*\('));
    }

    public function testParserExtractsQuotedDbPhpDefines(): void
    {
        $cnn = new Cnn();
        $method = (new ReflectionClass($cnn))->getMethod('parseDatabaseDefinitions');
        $method->setAccessible(true);

        $parsed = $method->invoke($cnn, "<?php\n"
            . "define('DB_ADAPTER', 'mysql');\n"
            . "define('DB_HOST', 'localhost:3306');\n"
            . "define('DB_PASS', 'pa\\'ss');\n"
            . "define(\"DB_NAME\", \"wf\");\n"
        );

        self::assertSame('mysql', $parsed['DB_ADAPTER']);
        self::assertSame('localhost:3306', $parsed['DB_HOST']);
        self::assertSame("pa'ss", $parsed['DB_PASS']);
        self::assertSame('wf', $parsed['DB_NAME']);
    }

    public function testDataSourceBuilderUsesCredentialArrayLookups(): void
    {
        $raw = self::source();

        foreach (['DB_ADAPTER', 'DB_HOST', 'DB_NAME', 'DB_USER', 'DB_PASS', 'DB_RBAC_HOST', 'DB_REPORT_HOST'] as $name) {
            self::assertStringContainsString('$this->databaseDefinition($credentials, \'' . $name . '\')', $raw);
        }
        self::assertStringContainsString('function databaseDefinition(array $credentials, $name)', $raw);
        self::assertSame(15, PhpSourceScanner::matchCount(self::code(), '\$this->databaseDefinition\s*\('));
    }

    public function testEvalRatchetDroppedByTheCnnSite(): void
    {
        $budget = self::budget();
        $counts = CompatibilityLedger::counts($budget['scope'], ['eval' => self::EVAL_PATTERN]);

        self::assertSame(0, $counts['eval']['textual']);
        self::assertSame(0, $counts['eval']['code']);
        self::assertSame(0, $budget['budgets']['eval']);
        self::assertSame(0, $budget['codeBudgets']['eval']);
        self::assertArrayHasKey('_noteU36', $budget);
    }

    public function testEvalInventoryNoLongerListsCnn(): void
    {
        $inventory = self::inventory();
        $files = array_values(array_unique(array_column($inventory['executableSites'], 'file')));

        self::assertSame(0, $inventory['summary']['textual']);
        self::assertSame(0, $inventory['summary']['executable']);
        self::assertSame(0, $inventory['summary']['executableFiles']);
        self::assertSame(0, $inventory['categories']['core_bootstrap_dynamic_runtime']['count']);
        self::assertArrayNotHasKey(self::TARGET, $inventory['perFileExecutable']);
        self::assertNotContains(self::TARGET, $files);
    }
}
