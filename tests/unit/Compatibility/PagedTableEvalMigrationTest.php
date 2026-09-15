<?php

declare(strict_types=1);
namespace Tests\Unit\Compatibility;
use PHPUnit\Framework\TestCase;
use Tests\Support\CompatibilityLedger;
use Tests\Support\PhpSourceScanner;
final class PagedTableEvalMigrationTest extends TestCase
{
    private const TARGET = 'gulliver/system/class.pagedTable.php';
    private const EVAL_PATTERN = '(?<![\\w$>-])eval\\s*\\(';
    private static function source(): string { return PhpSourceScanner::read(PM_TEST_ROOT . '/' . self::TARGET); }
    private static function code(): string { return PhpSourceScanner::codeOnlySource(self::source()); }
    private static function budget(): array { return json_decode((string) file_get_contents(PM_TEST_ROOT . '/tests/fixtures/deprecation-budget.json'), true, 512, JSON_THROW_ON_ERROR); }
    private static function inventory(): array { return json_decode((string) file_get_contents(PM_TEST_ROOT . '/tests/fixtures/eval-inventory.json'), true, 512, JSON_THROW_ON_ERROR); }
    public function testPagedTableContainsNoExecutableEval(): void
    {
        self::assertSame(0, PhpSourceScanner::matchCount(self::code(), self::EVAL_PATTERN));
    }
    public function testFiveDynamicDatabaseConstantsUseConstant(): void
    {
        $raw=self::source();
        foreach (['DBC_SERVER'=>'HOST','DBC_USERNAME'=>'USER','DBC_PASSWORD'=>'PASS','DBC_DATABASE'=>'NAME','DBC_TYPE'=>'TYPE'] as $field=>$suffix) {
            self::assertStringContainsString("\$res['{$field}'] = constant('DB_' . \$this->sqlConnection . '_{$suffix}');",$raw);
        }
        self::assertSame(5, PhpSourceScanner::matchCount(self::code(), '(?<![\\w$>-])constant\\s*\\('));
    }
    public function testExistingDefinedGatesAndFallbacksArePreserved(): void
    {
        $raw=self::source();
        foreach (['HOST','USER','PASS','NAME','TYPE'] as $suffix) self::assertStringContainsString("defined('DB_' . \$this->sqlConnection . '_{$suffix}')",$raw);
        self::assertStringContainsString("\$res['DBC_SERVER'] = DB_HOST;",$raw);
        self::assertStringContainsString("\$res['DBC_PASSWORD'] = DB_PASS;",$raw);
        self::assertStringContainsString("\$res['DBC_DATABASE'] = DB_NAME;",$raw);
    }
    public function testDynamicAttributeAssignmentUsesVariableProperties(): void
    {
        $raw=self::source();
        self::assertStringContainsString('settype($value, gettype($this->{$atrib}));',$raw);
        self::assertStringContainsString('$this->{$atrib} = $value;',$raw);
        self::assertStringContainsString("if (\$value !== '')",$raw);
    }
    public function testEvalRatchetDropsBySeven(): void
    {
        $budget=self::budget(); $counts=CompatibilityLedger::counts($budget['scope'],['eval'=>self::EVAL_PATTERN]);
        self::assertSame(0, $counts['eval']['textual']); self::assertSame(0, $counts['eval']['code']); self::assertSame(0, $budget['budgets']['eval']); self::assertSame(0, $budget['codeBudgets']['eval']); self::assertArrayHasKey('_noteU310',$budget);
    }
    public function testInventoryNoLongerListsPagedTable(): void
    {
        $i=self::inventory(); self::assertSame(0, $i['summary']['textual']); self::assertSame(0, $i['summary']['executable']); self::assertSame(0, $i['summary']['executableFiles']); self::assertSame(0,$i['categories']['gulliver_dynamic_runtime']['count']); self::assertArrayNotHasKey(self::TARGET,$i['perFileExecutable']); self::assertCount(0,array_filter($i['executableSites'],static fn(array $site):bool=>$site['file']===self::TARGET));
    }
}
