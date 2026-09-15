<?php

declare(strict_types=1);

namespace Tests\Unit\Compatibility;

use PHPUnit\Framework\TestCase;
use Tests\Support\CompatibilityLedger;
use Tests\Support\PhpSourceScanner;

/**
 * U-3.2: DynaformEditor reads its temporary-data file by parsing the exact
 * format written by _setTmpData(), instead of evaluating that file as PHP.
 */
final class DynaformEditorEvalMigrationTest extends TestCase
{
    private const TARGET = 'workflow/engine/classes/DynaformEditor.php';
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

    public function testDynaformEditorContainsNoExecutableEval(): void
    {
        self::assertSame(0, PhpSourceScanner::matchCount(self::code(), self::EVAL_PATTERN));
        self::assertStringNotContainsString("eval(implode('', file(\$file)))", self::source());
    }

    public function testTemporaryDataWriterFormatIsStillPinned(): void
    {
        $raw = self::source();

        self::assertStringContainsString("fwrite(\$fp, '\$tmpData=unserialize(\\'' . addcslashes(serialize(\$data), '\\\\\\'') . '\\');');", $raw);
        self::assertStringContainsString("PATH_C . 'dynEditor/' . session_id() . '.php'", $raw);
    }

    public function testTemporaryDataReaderParsesOnlyTheExpectedAssignmentShape(): void
    {
        $raw = self::source();

        self::assertStringContainsString('preg_match("/^\\$tmpData=unserialize', $raw);
        self::assertStringContainsString("strtr(\$match[1], array", $raw);
        self::assertStringContainsString("unserialize(strtr(\$match[1]", $raw);
        self::assertSame(1, PhpSourceScanner::matchCount(self::code(), '(?<![\w$>-])preg_match\s*\('));
        self::assertSame(1, PhpSourceScanner::matchCount(self::code(), '(?<![\w$>-])strtr\s*\('));
    }

    public function testEvalRatchetDroppedByTheDynaformEditorSite(): void
    {
        $budget = self::budget();
        $counts = CompatibilityLedger::counts($budget['scope'], ['eval' => self::EVAL_PATTERN]);

        self::assertSame(0, $counts['eval']['textual']);
        self::assertSame(0, $counts['eval']['code']);
        self::assertSame(0, $budget['budgets']['eval']);
        self::assertSame(0, $budget['codeBudgets']['eval']);
        self::assertArrayHasKey('_noteU32', $budget);
    }

    public function testEvalInventoryNoLongerListsDynaformEditor(): void
    {
        $inventory = self::inventory();
        $files = array_values(array_unique(array_column($inventory['executableSites'], 'file')));

        self::assertSame(0, $inventory['summary']['textual']);
        self::assertSame(0, $inventory['summary']['executable']);
        self::assertSame(0, $inventory['summary']['executableFiles']);
        self::assertSame(0, $inventory['categories']['legacy_engine_dynamic_runtime']['count']);
        self::assertArrayNotHasKey(self::TARGET, $inventory['perFileExecutable']);
        self::assertNotContains(self::TARGET, $files);
    }

    public function testOtherDynaformEditorFileOperationsRemain(): void
    {
        $raw = self::source();

        self::assertStringContainsString('public static function _setTmpData($data)', $raw);
        self::assertStringContainsString('public static function _getTmpData()', $raw);
        self::assertStringContainsString('public function _copyFile($from, $to)', $raw);
        self::assertStringContainsString('$copy = implode(\'\', file($from));', $raw);
    }
}
