<?php

declare(strict_types=1);

namespace Tests\Unit\Compatibility;

use PHPUnit\Framework\TestCase;
use Tests\Support\CompatibilityLedger;
use Tests\Support\PhpSourceScanner;

/**
 * U-3.4: upgrade_SystemAjax reads workspace db.php credentials with a direct
 * define parser and setter instead of evaluating generated assignment code.
 */
final class UpgradeSystemAjaxEvalMigrationTest extends TestCase
{
    private const TARGET = 'workflow/engine/methods/setup/upgrade_SystemAjax.php';
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

    public function testUpgradeSystemAjaxContainsNoExecutableEval(): void
    {
        self::assertSame(0, PhpSourceScanner::matchCount(self::code(), self::EVAL_PATTERN));
        self::assertStringNotContainsString('eval(getDatabaseCredentials', self::source());
    }

    public function testWorkspaceCredentialLoadUsesParserAndSetter(): void
    {
        $raw = self::source();

        self::assertStringContainsString("setDatabaseCredentials(getDatabaseCredentials(PATH_DB . \$workspace . PATH_SEP . 'db.php'));", $raw);
        self::assertStringContainsString('$database = new database($DB_ADAPTER, $DB_HOST, $DB_USER, $DB_PASS, $DB_NAME);', $raw);
        self::assertSame(1, PhpSourceScanner::matchCount(self::code(), 'function\s+getDatabaseCredentials\s*\('));
        self::assertSame(1, PhpSourceScanner::matchCount(self::code(), 'function\s+setDatabaseCredentials\s*\('));
    }

    public function testCredentialParserTargetsDbPhpDefineStatements(): void
    {
        $raw = self::source();

        self::assertStringContainsString('preg_match_all("/define\s*\(', $raw);
        self::assertStringContainsString('PREG_SET_ORDER', $raw);
        self::assertStringContainsString('$credentials[$match[1]] = stripcslashes($match[2]);', $raw);
        self::assertSame(1, PhpSourceScanner::matchCount(self::code(), '(?<![\w$>-])preg_match_all\s*\('));
        self::assertSame(1, PhpSourceScanner::matchCount(self::code(), '(?<![\w$>-])stripcslashes\s*\('));
    }

    public function testCredentialSetterAssignsTheFiveLegacyGlobals(): void
    {
        $raw = self::source();

        foreach (['DB_ADAPTER', 'DB_HOST', 'DB_USER', 'DB_PASS', 'DB_NAME'] as $name) {
            self::assertStringContainsString('global $' . $name . ';', $raw);
            self::assertStringContainsString('$' . $name . " = isset(\$credentials['" . $name . "']) ? \$credentials['" . $name . "'] : null;", $raw);
        }
    }

    public function testEvalRatchetDroppedByTheUpgradeSystemAjaxSite(): void
    {
        $budget = self::budget();
        $counts = CompatibilityLedger::counts($budget['scope'], ['eval' => self::EVAL_PATTERN]);

        self::assertSame(0, $counts['eval']['textual']);
        self::assertSame(0, $counts['eval']['code']);
        self::assertSame(0, $budget['budgets']['eval']);
        self::assertSame(0, $budget['codeBudgets']['eval']);
        self::assertArrayHasKey('_noteU34', $budget);
    }

    public function testEvalInventoryNoLongerListsUpgradeSystemAjax(): void
    {
        $inventory = self::inventory();
        $files = array_values(array_unique(array_column($inventory['executableSites'], 'file')));

        self::assertSame(0, $inventory['summary']['textual']);
        self::assertSame(0, $inventory['summary']['executable']);
        self::assertSame(0, $inventory['summary']['executableFiles']);
        self::assertSame(0, $inventory['categories']['engine_method_dynamic_runtime']['count']);
        self::assertArrayNotHasKey(self::TARGET, $inventory['perFileExecutable']);
        self::assertNotContains(self::TARGET, $files);
    }
}
