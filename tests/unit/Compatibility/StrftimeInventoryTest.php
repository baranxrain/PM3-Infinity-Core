<?php

declare(strict_types=1);

namespace Tests\Unit\Compatibility;

use PHPUnit\Framework\TestCase;
use Tests\Support\CompatibilityLedger;
use Tests\Support\PhpSourceScanner;

/**
 * U-2.3.1 guard: the strftime()/gmstrftime() family is inventoried and frozen
 * before anything is migrated.
 *
 * U-2.3 is far too large for one unit (278 textual, 138 executable hits), so
 * this unit only classifies. Every assertion here is a fact about the tree,
 * measured with the same region-aware projection the ledger uses. No call site
 * is migrated by U-2.3.1 and no call site consumes a helper yet.
 */
final class StrftimeInventoryTest extends TestCase
{
    private const PATTERN = '(?<![\w$>-])(?:strftime|gmstrftime)\s*\(';

    private const GM_PATTERN = '(?<![\w$>-])gmstrftime\s*\(';

    /** @return array<string, mixed> */
    private static function inventory(): array
    {
        static $inventory = null;
        if ($inventory === null) {
            $contents = file_get_contents(PM_TEST_ROOT . '/tests/fixtures/strftime-inventory.json');
            self::assertIsString($contents, 'The U-2.3.1 inventory fixture is unreadable.');
            $inventory = json_decode($contents, true, 512, JSON_THROW_ON_ERROR);
        }

        return $inventory;
    }

    /** @return array<string, int> */
    private static function liveExecutableCounts(): array
    {
        static $counts = null;
        if ($counts !== null) {
            return $counts;
        }

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

    public function testInventoryUsesTheLedgerScopeAndPattern(): void
    {
        $inventory = self::inventory();
        $budget = json_decode(
            (string) file_get_contents(PM_TEST_ROOT . '/tests/fixtures/deprecation-budget.json'),
            true,
            512,
            JSON_THROW_ON_ERROR
        );

        self::assertSame($budget['scope'], $inventory['scope'], 'The inventory must use the accepted ledger scope.');
        self::assertSame(self::PATTERN, $inventory['pattern'], 'The inventory must use the accepted family pattern.');
    }

    public function testRecordedTotalsMatchTheLiveTree(): void
    {
        $inventory = self::inventory();
        $counts = CompatibilityLedger::counts($inventory['scope'], ['strftime' => self::PATTERN]);

        self::assertSame($inventory['summary']['textual'], $counts['strftime']['textual'], 'Textual strftime total drifted.');
        self::assertSame($inventory['summary']['executable'], $counts['strftime']['code'], 'Executable strftime total drifted.');
        self::assertSame(
            $inventory['summary']['textual'] - $inventory['summary']['executable'],
            $inventory['summary']['nonExecutable'],
            'The inventory summary is internally inconsistent.'
        );
    }

    public function testRecordedTotalsMatchTheAcceptedRatchet(): void
    {
        $inventory = self::inventory();
        $budget = json_decode(
            (string) file_get_contents(PM_TEST_ROOT . '/tests/fixtures/deprecation-budget.json'),
            true,
            512,
            JSON_THROW_ON_ERROR
        );

        self::assertSame($budget['budgets']['strftime'], $inventory['summary']['textual'], 'U-2.3.1 must not change the textual ratchet.');
        self::assertSame($budget['codeBudgets']['strftime'], $inventory['summary']['executable'], 'U-2.3.1 must not change the executable ratchet.');
    }

    public function testEveryExecutableSiteIsRecordedPerFile(): void
    {
        $inventory = self::inventory();
        self::assertSame($inventory['perFileExecutable'], self::liveExecutableCounts(), 'The per-file executable inventory drifted from the tree.');
        self::assertSame($inventory['summary']['executableFiles'], count($inventory['perFileExecutable']));
        self::assertSame($inventory['summary']['executable'], array_sum($inventory['perFileExecutable']));
    }

    public function testEveryRecordedLineReallyContainsAnExecutableCall(): void
    {
        $inventory = self::inventory();
        $byFile = [];
        foreach ($inventory['executableSites'] as $site) {
            $byFile[$site['file']][] = $site['line'];
        }

        self::assertSame(array_keys($inventory['perFileExecutable']), array_keys($byFile), 'Site list and per-file map disagree.');

        foreach ($byFile as $relativeFile => $lines) {
            $projection = PhpSourceScanner::codeOnlySource(PhpSourceScanner::read(PM_TEST_ROOT . '/' . $relativeFile));
            self::assertSame(
                PhpSourceScanner::matchLines($projection, self::PATTERN),
                $lines,
                $relativeFile . ' does not execute strftime() on the recorded lines.'
            );
        }
    }

    public function testNoGmstrftimeCallExistsAnywhereInScope(): void
    {
        $inventory = self::inventory();
        $counts = CompatibilityLedger::counts($inventory['scope'], ['gm' => self::GM_PATTERN]);

        self::assertSame(0, $inventory['summary']['gmstrftimeTextual'], 'The inventory claims a gmstrftime hit.');
        self::assertSame(0, $counts['gm']['textual'], 'A gmstrftime occurrence appeared in scope.');
        self::assertSame(0, $counts['gm']['code'], 'An executable gmstrftime call appeared in scope.');
    }

    public function testGeneratedPropelGettersAreOneUniformShape(): void
    {
        $inventory = self::inventory();
        $expectedLine = $inventory['categories']['generated_propel_getter']['exactLine'];
        $generated = array_values(array_filter(
            $inventory['executableSites'],
            static fn (array $site): bool => $site['category'] === 'generated_propel_getter'
        ));

        self::assertSame($inventory['categories']['generated_propel_getter']['count'], count($generated));

        foreach ($generated as $site) {
            self::assertStringContainsString('/model/om/', $site['file'], 'A generated getter was recorded outside model/om.');
            $lines = preg_split('/\r\n|\n|\r/', PhpSourceScanner::read(PM_TEST_ROOT . '/' . $site['file'])) ?: [];
            self::assertSame($expectedLine, trim($lines[$site['line'] - 1] ?? ''), $site['file'] . ':' . $site['line'] . ' is not the uniform generated getter.');
        }
    }

    public function testHandwrittenSitesAreOnlyTheTwoConfigurationsLines(): void
    {
        $inventory = self::inventory();
        $handwritten = array_values(array_filter(
            $inventory['executableSites'],
            static fn (array $site): bool => $site['category'] !== 'generated_propel_getter'
        ));

        self::assertCount(0, $handwritten, 'U-2.4.2 migrated the handwritten strftime surface, so none may remain.');
        self::assertSame(0, self::inventory()['categories']['handwritten_locale_date']['count'], 'The inventory must record the migration.');
    }

    public function testTheLastUtf8EncodeWasMigratedButStaysCoupledToStrftime(): void
    {
        $projection = PhpSourceScanner::codeOnlySource(
            PhpSourceScanner::read(PM_TEST_ROOT . '/workflow/engine/classes/Configurations.php')
        );

        self::assertSame(
            0,
            PhpSourceScanner::matchCount($projection, '(?<![\w$>-])utf8_encode\s*\(\s*strftime\s*\('),
            'U-2.3.3 replaced the native encode on Configurations.php:582.'
        );
        self::assertSame(
            0,
            PhpSourceScanner::matchCount($projection, '(?<![\w$>-])LegacyUtf8::encode\s*\(\s*strftime\s*\('),
            'U-2.4.2 removed the wrapped pair: the locale-aware helper returns UTF-8 directly.'
        );
    }

    public function testEveryInventoriedSiteIsClassifiedAndTheGettersAreMigrated(): void
    {
        // U-2.3.2a introduces ProcessMaker\Util\LegacyStrftime, so its mere
        // existence is no longer a violation. What U-2.3.1 guarantees is that
        // no call site was migrated to it.
        $fixture = self::inventory();
        self::assertSame(
            137,
            CompatibilityLedger::counts($fixture['scope'], ['helper' => '(?<![\w$>-])LegacyStrftime::'])['helper']['code'],
            'The 136 U-2.3.2 getters plus the U-2.4.2 LegacyLocaleDate delegation consume the helper.'
        );

        $inventory = self::inventory();
        foreach ($inventory['executableSites'] as $site) {
            self::assertNotSame('unclassified', $site['category'], $site['file'] . ':' . $site['line'] . ' is unclassified.');
        }
    }
}
