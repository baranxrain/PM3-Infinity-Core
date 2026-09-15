<?php

declare(strict_types=1);

namespace Tests\Unit\Compatibility;

use PHPUnit\Framework\TestCase;
use Tests\Support\CompatibilityLedger;
use Tests\Support\PhpSourceScanner;

/**
 * Current eval() inventory guard. U-2.9 introduced the fixture; later
 * hardening units lower the ratchet and refresh this inventory.
 */
final class EvalInventoryTest extends TestCase
{
    private const PATTERN = '(?<![\w$>-])eval\s*\(';

    /** @return array<string, mixed> */
    private static function inventory(): array
    {
        static $inventory = null;
        if ($inventory === null) {
            $contents = file_get_contents(PM_TEST_ROOT . '/tests/fixtures/eval-inventory.json');
            self::assertIsString($contents, 'The U-2.9 eval inventory fixture is unreadable.');
            $inventory = json_decode($contents, true, 512, JSON_THROW_ON_ERROR);
        }

        return $inventory;
    }

    /** @return array<string, int> */
    private static function liveExecutableCounts(): array
    {
        $counts = [];
        foreach (CompatibilityLedger::phpFiles(self::inventory()['scope']) as $file) {
            $source = PhpSourceScanner::read($file);
            if (PhpSourceScanner::matchCount($source, self::PATTERN) === 0) {
                continue;
            }
            $inFile = PhpSourceScanner::matchCount(PhpSourceScanner::codeOnlySource($source), self::PATTERN);
            if ($inFile > 0) {
                $counts[CompatibilityLedger::relative($file)] = $inFile;
            }
        }
        ksort($counts);

        return $counts;
    }

    public function testInventoryUsesTheAcceptedLedgerScopeAndPattern(): void
    {
        $inventory = self::inventory();
        $budget = json_decode((string) file_get_contents(PM_TEST_ROOT . '/tests/fixtures/deprecation-budget.json'), true, 512, JSON_THROW_ON_ERROR);

        self::assertSame($budget['scope'], $inventory['scope']);
        self::assertSame($budget['patterns']['eval'], $inventory['pattern']);
    }

    public function testRecordedTotalsMatchTheLiveTreeAndRatchet(): void
    {
        $inventory = self::inventory();
        $budget = json_decode((string) file_get_contents(PM_TEST_ROOT . '/tests/fixtures/deprecation-budget.json'), true, 512, JSON_THROW_ON_ERROR);
        $counts = CompatibilityLedger::counts($inventory['scope'], ['eval' => self::PATTERN]);

        self::assertSame($inventory['summary']['textual'], $counts['eval']['textual']);
        self::assertSame($inventory['summary']['executable'], $counts['eval']['code']);
        self::assertSame($inventory['summary']['textual'] - $inventory['summary']['executable'], $inventory['summary']['nonExecutable']);
        self::assertSame($budget['budgets']['eval'], $inventory['summary']['textual']);
        self::assertSame($budget['codeBudgets']['eval'], $inventory['summary']['executable']);
        self::assertSame(0, $inventory['summary']['textual']);
        self::assertSame(0, $inventory['summary']['executable']);
    }

    public function testEveryExecutableSiteIsRecordedPerFile(): void
    {
        $inventory = self::inventory();

        self::assertSame($inventory['perFileExecutable'], self::liveExecutableCounts());
        self::assertSame($inventory['summary']['executableFiles'], count($inventory['perFileExecutable']));
        self::assertSame($inventory['summary']['executable'], array_sum($inventory['perFileExecutable']));
    }

    public function testEveryRecordedLineReallyContainsAnExecutableEval(): void
    {
        $inventory = self::inventory();
        $byFile = [];
        foreach ($inventory['executableSites'] as $site) {
            $byFile[$site['file']][] = $site['line'];
        }

        self::assertSame(array_keys($inventory['perFileExecutable']), array_keys($byFile));
        foreach ($byFile as $relativeFile => $lines) {
            $projection = PhpSourceScanner::codeOnlySource(PhpSourceScanner::read(PM_TEST_ROOT . '/' . $relativeFile));
            self::assertSame(PhpSourceScanner::matchLines($projection, self::PATTERN), $lines, $relativeFile . ' eval lines drifted.');
        }
    }

    public function testEveryExecutableSiteIsClassified(): void
    {
        $inventory = self::inventory();
        $sum = 0;
        foreach ($inventory['categories'] as $name => $definition) {
            self::assertNotSame('unclassified', $name);
            if ($name !== 'non_executable') {
                $sum += (int) $definition['count'];
            }
        }
        self::assertSame($inventory['summary']['executable'], $sum);
        foreach ($inventory['executableSites'] as $site) {
            self::assertArrayHasKey($site['category'], $inventory['categories'], $site['file'] . ':' . $site['line'] . ' has an unknown category.');
            self::assertNotSame('unclassified', $site['category'], $site['file'] . ':' . $site['line'] . ' is unclassified.');
        }
    }

    public function testMajorRiskClustersArePinned(): void
    {
        $categories = self::inventory()['categories'];

        self::assertSame(0, $categories['trigger_script_eval']['count']);
        self::assertSame(0, $categories['dynamic_model_or_criteria']['count']);
        self::assertSame(0, $categories['gulliver_dynamic_runtime']['count']);
        self::assertSame(0, $categories['engine_method_dynamic_runtime']['count']);
        self::assertSame(0, $categories['core_bootstrap_dynamic_runtime']['count']);
        self::assertSame(0, $categories['legacy_engine_dynamic_runtime']['count']);
        self::assertSame(0, $categories['non_executable']['count']);
    }

    public function testPmscriptTriggerExecutionClusterIsClosed(): void
    {
        $triggerSites = array_values(array_filter(
            self::inventory()['executableSites'],
            static fn (array $site): bool => $site['category'] === 'trigger_script_eval'
        ));

        self::assertSame([], $triggerSites);
    }
}
