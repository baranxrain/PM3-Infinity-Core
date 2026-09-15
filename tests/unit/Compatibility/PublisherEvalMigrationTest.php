<?php

declare(strict_types=1);

namespace Tests\Unit\Compatibility;

use PHPUnit\Framework\TestCase;
use Tests\Support\CompatibilityLedger;
use Tests\Support\PhpSourceScanner;

final class PublisherEvalMigrationTest extends TestCase
{
    private const TARGET = 'gulliver/system/class.publisher.php';
    private const EVAL_PATTERN = '(?<![\\w$>-])eval\\s*\\(';
    private static function source(): string { return PhpSourceScanner::read(PM_TEST_ROOT . '/' . self::TARGET); }
    private static function code(): string { return PhpSourceScanner::codeOnlySource(self::source()); }
    private static function budget(): array { return json_decode((string) file_get_contents(PM_TEST_ROOT . '/tests/fixtures/deprecation-budget.json'), true, 512, JSON_THROW_ON_ERROR); }
    private static function inventory(): array { return json_decode((string) file_get_contents(PM_TEST_ROOT . '/tests/fixtures/eval-inventory.json'), true, 512, JSON_THROW_ON_ERROR); }
    public function testPublisherContainsNoExecutableEval(): void
    {
        self::assertSame(0, PhpSourceScanner::matchCount(self::code(), self::EVAL_PATTERN));
        self::assertStringNotContainsString("eval( '\$G_FORM = new '", self::source());
    }
    public function testDynamicTemplateConstructionUsesNativePhpSyntax(): void
    {
        $raw = self::source();
        self::assertStringContainsString("\$templateClass = \$Part['Template'];", $raw);
        self::assertStringContainsString("\$G_FORM = new \$templateClass(\$Part['File'], \$sPath);", $raw);
    }
    public function testFallbackFormBranchIsPreserved(): void
    {
        $raw = self::source();
        self::assertStringContainsString("if (! class_exists( \$Part['Template'] ) || \$Part['Template'] === 'xmlform')", $raw);
        self::assertStringContainsString("\$G_FORM = new Form( \$Part['File'], \$sPath, SYS_LANG, false );", $raw);
    }
    public function testEvalRatchetDropsByOne(): void
    {
        $budget = self::budget(); $counts = CompatibilityLedger::counts($budget['scope'], ['eval' => self::EVAL_PATTERN]);
        self::assertSame(0, $counts['eval']['textual']); self::assertSame(0, $counts['eval']['code']);
        self::assertSame(0, $budget['budgets']['eval']); self::assertSame(0, $budget['codeBudgets']['eval']); self::assertArrayHasKey('_noteU39', $budget);
    }
    public function testInventoryNoLongerListsPublisher(): void
    {
        $inventory = self::inventory();
        self::assertSame(0, $inventory['summary']['textual']); self::assertSame(0, $inventory['summary']['executable']); self::assertSame(0, $inventory['summary']['executableFiles']);
        self::assertSame(0, $inventory['categories']['gulliver_dynamic_runtime']['count']); self::assertArrayNotHasKey(self::TARGET, $inventory['perFileExecutable']);
        self::assertCount(0, array_filter($inventory['executableSites'], static fn (array $site): bool => $site['file'] === self::TARGET));
    }
}
