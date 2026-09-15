<?php

declare(strict_types=1);

namespace Tests\Unit\Compatibility;

use PHPUnit\Framework\TestCase;
use Tests\Support\CompatibilityLedger;
use Tests\Support\PhpSourceScanner;

/**
 * U-3.5: fields_Ajax keeps executing CURRENT_PAGE_INITILIZATION in the current
 * script scope, but does so through a temporary PHP include instead of eval().
 */
final class FieldsAjaxEvalMigrationTest extends TestCase
{
    private const TARGET = 'workflow/engine/methods/dynaforms/fields_Ajax.php';
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

    public function testFieldsAjaxContainsNoExecutableEval(): void
    {
        self::assertSame(0, PhpSourceScanner::matchCount(self::code(), self::EVAL_PATTERN));
        self::assertStringNotContainsString("eval( \$_SESSION['CURRENT_PAGE_INITILIZATION'] );", self::source());
    }

    public function testCurrentPageInitializationGateIsPreserved(): void
    {
        $raw = self::source();

        self::assertStringContainsString("if (isset( \$_SESSION['CURRENT_PAGE_INITILIZATION'] ))", $raw);
        self::assertStringContainsString('$currentPageInitialization = tempnam(sys_get_temp_dir(), \'pm_page_init_\');', $raw);
        self::assertStringContainsString('if ($currentPageInitialization !== false)', $raw);
    }

    public function testInitializationScriptRunsThroughTemporaryInclude(): void
    {
        $raw = self::source();
        $code = self::code();

        self::assertStringContainsString('file_put_contents($currentPageInitialization, "<?php\\n" . $_SESSION[\'CURRENT_PAGE_INITILIZATION\']);', $raw);
        self::assertStringContainsString('include $currentPageInitialization;', $raw);
        self::assertStringContainsString('@unlink($currentPageInitialization);', $raw);
        self::assertStringContainsString('finally', $raw);
        self::assertSame(1, PhpSourceScanner::matchCount($code, '(?<![\w$>-])tempnam\s*\('));
        self::assertSame(1, PhpSourceScanner::matchCount($code, '(?<![\w$>-])file_put_contents\s*\('));
        self::assertSame(1, PhpSourceScanner::matchCount($code, '(?<![\w$>-])unlink\s*\('));
    }

    public function testEvalRatchetDroppedByTheFieldsAjaxSite(): void
    {
        $budget = self::budget();
        $counts = CompatibilityLedger::counts($budget['scope'], ['eval' => self::EVAL_PATTERN]);

        self::assertSame(0, $counts['eval']['textual']);
        self::assertSame(0, $counts['eval']['code']);
        self::assertSame(0, $budget['budgets']['eval']);
        self::assertSame(0, $budget['codeBudgets']['eval']);
        self::assertArrayHasKey('_noteU35', $budget);
    }

    public function testEvalInventoryNoLongerListsFieldsAjax(): void
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

    public function testDependentFieldAjaxFlowIsStillPresent(): void
    {
        $raw = self::source();

        self::assertStringContainsString('$G_FORM = new Form( G::getUIDName( urlDecode( $_POST[\'form\'] ) ) );', $raw);
        self::assertStringContainsString('$newValues = (Bootstrap::json_decode( urlDecode( stripslashes( $_POST[\'fields\'] ) ) ));', $raw);
        self::assertStringContainsString('function subDependencies ($k, &$G_FORM, &$aux)', $raw);
        self::assertStringContainsString('echo (Bootstrap::json_encode( $sendContent ));', $raw);
    }
}
