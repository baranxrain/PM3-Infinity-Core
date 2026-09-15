<?php

declare(strict_types=1);

namespace Tests\Unit\Compatibility;

use PHPUnit\Framework\TestCase;
use Tests\Support\CompatibilityLedger;
use Tests\Support\PhpSourceScanner;

/**
 * U-3.1: PmDashlet no longer uses eval() for dynamic static dashlet calls or
 * version constant lookup.
 */
final class PmDashletEvalMigrationTest extends TestCase
{
    private const TARGET = 'workflow/engine/classes/PmDashlet.php';
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

    public function testPmDashletContainsNoExecutableEval(): void
    {
        self::assertSame(0, PhpSourceScanner::matchCount(self::code(), self::EVAL_PATTERN));
        self::assertStringNotContainsString('eval("$additionalFields', self::source());
        self::assertStringNotContainsString('eval("\\$additionalFields', self::source());
        self::assertStringNotContainsString('eval("$row', self::source());
        self::assertStringNotContainsString('eval("\\$row', self::source());
    }

    public function testDynamicStaticDashletCallsUseCallUserFunc(): void
    {
        $raw = self::source();

        self::assertStringContainsString('$additionalFields = call_user_func([$className, \'getAdditionalFields\'], $className);', $raw);
        self::assertStringContainsString('$additionalFields = call_user_func([$className, \'getXTemplate\'], $className);', $raw);
        self::assertSame(2, PhpSourceScanner::matchCount(self::code(), '(?<![\w$>-])call_user_func\s*\('));
    }

    public function testVersionConstantLookupUsesDefinedAndConstant(): void
    {
        $raw = self::source();

        self::assertStringContainsString("\$versionConstant = \$row['DAS_CLASS'] . '::version';", $raw);
        self::assertStringContainsString("\$row['DAS_VERSION'] = defined(\$versionConstant) ? constant(\$versionConstant) : \$row['DAS_VERSION'];", $raw);
        self::assertSame(1, PhpSourceScanner::matchCount(self::code(), '(?<![\w$>-])constant\s*\('));
    }

    public function testEvalRatchetDroppedByTheThreePmDashletSites(): void
    {
        $budget = self::budget();
        $counts = CompatibilityLedger::counts($budget['scope'], ['eval' => self::EVAL_PATTERN]);

        self::assertSame(0, $counts['eval']['textual']);
        self::assertSame(0, $counts['eval']['code']);
        self::assertSame(0, $budget['budgets']['eval']);
        self::assertSame(0, $budget['codeBudgets']['eval']);
        self::assertArrayHasKey('_noteU31', $budget);
    }

    public function testEvalInventoryNoLongerListsPmDashlet(): void
    {
        $inventory = self::inventory();
        $files = array_values(array_unique(array_column($inventory['executableSites'], 'file')));

        self::assertSame(0, $inventory['summary']['textual']);
        self::assertSame(0, $inventory['summary']['executable']);
        self::assertSame(0, $inventory['categories']['legacy_engine_dynamic_runtime']['count']);
        self::assertArrayNotHasKey(self::TARGET, $inventory['perFileExecutable']);
        self::assertNotContains(self::TARGET, $files);
    }

    public function testOtherPmDashletBehaviourAnchorsRemain(): void
    {
        $raw = self::source();

        self::assertStringContainsString('public static function getAdditionalFields($className)', $raw);
        self::assertStringContainsString('public static function getXTemplate($className)', $raw);
        self::assertStringContainsString('$this->dashletObject = new $className();', $raw);
        self::assertStringContainsString('$this->dashletObject->setup($this->dashletInstance);', $raw);
    }
}
