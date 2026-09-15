<?php

declare(strict_types=1);

namespace Tests\Unit\Compatibility;

use PHPUnit\Framework\TestCase;
use Tests\Support\CompatibilityLedger;
use Tests\Support\PhpSourceScanner;

/**
 * U-3.7: System::getPathsInstalled() reads paths_installed.php directly with
 * require_once and constants instead of assembling a PHP string for eval().
 */
final class SystemGetPathsInstalledEvalMigrationTest extends TestCase
{
    private const TARGET = 'workflow/engine/src/ProcessMaker/Core/System.php';
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

    public function testGetPathsInstalledNoLongerEvaluatesGeneratedScript(): void
    {
        $raw = self::source();

        self::assertStringNotContainsString('$result = eval($script);', $raw);
        self::assertStringNotContainsString('$script = "require_once', $raw);
        self::assertSame(0, PhpSourceScanner::matchCount(self::code(), self::EVAL_PATTERN), 'System.php remains eval-free after the follow-up credential migration.');
    }

    public function testGetPathsInstalledStillLoadsTheSameFile(): void
    {
        $raw = self::source();

        self::assertStringContainsString('$pathsInstalled = getcwd() . "/workflow/engine/config/paths_installed.php";', $raw);
        self::assertStringContainsString('if (file_exists($pathsInstalled))', $raw);
        self::assertStringContainsString('require_once $pathsInstalled;', $raw);
    }

    public function testGetPathsInstalledReturnsTheSameConstantKeys(): void
    {
        $raw = self::source();

        foreach (['pathData' => 'PATH_DATA', 'pathCompiled' => 'PATH_C', 'hashInstallation' => 'HASH_INSTALLATION', 'systemHash' => 'SYSTEM_HASH'] as $key => $constant) {
            self::assertStringContainsString("'" . $key . "' => " . $constant, $raw);
        }
        self::assertStringContainsString('return (object) $result;', $raw);
    }

    public function testEvalRatchetDroppedByTheGetPathsInstalledSite(): void
    {
        $budget = self::budget();
        $counts = CompatibilityLedger::counts($budget['scope'], ['eval' => self::EVAL_PATTERN]);

        self::assertSame(0, $counts['eval']['textual']);
        self::assertSame(0, $counts['eval']['code']);
        self::assertSame(0, $budget['budgets']['eval']);
        self::assertSame(0, $budget['codeBudgets']['eval']);
        self::assertArrayHasKey('_noteU37', $budget);
    }

    public function testEvalInventoryReflectsTheFollowUpSystemMigration(): void
    {
        $inventory = self::inventory();
        $systemSites = array_values(array_filter(
            $inventory['executableSites'],
            static fn (array $site): bool => $site['file'] === self::TARGET
        ));

        self::assertSame(0, $inventory['summary']['textual']);
        self::assertSame(0, $inventory['summary']['executable']);
        self::assertSame(0, $inventory['summary']['executableFiles']);
        self::assertSame(0, $inventory['categories']['core_bootstrap_dynamic_runtime']['count']);
        self::assertArrayNotHasKey(self::TARGET, $inventory['perFileExecutable']);
        self::assertCount(0, $systemSites);
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
