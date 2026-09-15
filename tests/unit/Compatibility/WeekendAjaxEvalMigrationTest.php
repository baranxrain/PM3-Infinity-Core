<?php

declare(strict_types=1);

namespace Tests\Unit\Compatibility;

use PHPUnit\Framework\TestCase;
use Tests\Support\CompatibilityLedger;
use Tests\Support\PhpSourceScanner;

/**
 * U-3.3: weekendAjax keeps the existing user-function gate but dispatches the
 * selected function directly instead of building a PHP string for eval().
 */
final class WeekendAjaxEvalMigrationTest extends TestCase
{
    private const TARGET = 'workflow/engine/methods/setup/weekendAjax.php';
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

    public function testWeekendAjaxContainsNoExecutableEval(): void
    {
        self::assertSame(0, PhpSourceScanner::matchCount(self::code(), self::EVAL_PATTERN));
        self::assertStringNotContainsString("eval( \$funcion . '();' );", self::source());
    }

    public function testUserFunctionGateIsPreserved(): void
    {
        $raw = self::source();

        self::assertStringContainsString("\$funcion = strtolower( get_ajax_value( 'function' ) );", $raw);
        self::assertStringContainsString('$funcions = get_defined_functions();', $raw);
        self::assertStringContainsString("if (in_array( \$funcion, \$funcions['user'] ))", $raw);
        self::assertStringContainsString('call_user_func( $funcion );', $raw);
        self::assertSame(1, PhpSourceScanner::matchCount(self::code(), '(?<![\w$>-])call_user_func\s*\('));
    }

    public function testWeekendAjaxPublicFunctionsRemainAvailable(): void
    {
        $raw = self::source();

        self::assertStringContainsString('function setDays ()', $raw);
        self::assertStringContainsString('function setDay ($day, $dayValue)', $raw);
        self::assertStringContainsString("\$days = get_ajax_value( 'days' );", $raw);
        self::assertStringContainsString("\$values = get_ajax_value( 'values' );", $raw);
    }

    public function testEvalRatchetDroppedByTheWeekendAjaxSite(): void
    {
        $budget = self::budget();
        $counts = CompatibilityLedger::counts($budget['scope'], ['eval' => self::EVAL_PATTERN]);

        self::assertSame(0, $counts['eval']['textual']);
        self::assertSame(0, $counts['eval']['code']);
        self::assertSame(0, $budget['budgets']['eval']);
        self::assertSame(0, $budget['codeBudgets']['eval']);
        self::assertArrayHasKey('_noteU33', $budget);
    }

    public function testEvalInventoryNoLongerListsWeekendAjax(): void
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

    public function testOtherCompatibilityRatchetsAreUnaffected(): void
    {
        $budget = self::budget();

        self::assertSame(140, $budget['budgets']['strftime']);
        self::assertSame(0, $budget['codeBudgets']['strftime']);
        self::assertSame(4, $budget['budgets']['utf8_encode_decode']);
        self::assertSame(0, $budget['codeBudgets']['utf8_encode_decode']);
    }
}
