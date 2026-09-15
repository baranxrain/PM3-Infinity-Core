<?php

namespace Tests\Unit\Compatibility;

use PHPUnit\Framework\TestCase;
use Tests\Support\CompatibilityLedger;
use Tests\Support\PhpSourceScanner;

/**
 * U-2.3.2: the 136 uniform Propel om getters must call the accepted helper
 * instead of the deprecated native function, and nothing else may move.
 *
 * The generated getters are the source of truth for the shape of the edit:
 * every one of them was the single line
 *     return strftime($format, $ts);
 * and every one of them is now the single line
 *     return \ProcessMaker\Util\LegacyStrftime::format($format, $ts);
 * The surrounding getter logic, including the date() branch and the
 * PropelException path, is untouched.
 */
class StrftimeCallSiteMigrationTest extends TestCase
{
    private const OLD_LINE = 'return strftime($format, $ts);';
    private const NEW_LINE = 'return \\ProcessMaker\\Util\\LegacyStrftime::format($format, $ts);';
    private const HELPER = 'workflow/engine/src/ProcessMaker/Util/LegacyStrftime.php';
    private const HELPER_SHA256 = 'a992b694b354b3b86643e03525ce03864199214732d457934e15dab404fa560d';
    private const STRFTIME = '(?<![\w$>-])(?:strftime|gmstrftime)\s*\(';
    private const HELPER_USE = '(?<![\w$>-])LegacyStrftime::';

    /** @return array<string, mixed> */
    private static function budget(): array
    {
        return json_decode(
            (string) file_get_contents(PM_TEST_ROOT . '/tests/fixtures/deprecation-budget.json'),
            true,
            512,
            JSON_THROW_ON_ERROR
        );
    }

    /** @return list<string> */
    private static function generatedFiles(): array
    {
        $files = [];
        foreach (CompatibilityLedger::phpFiles(self::budget()['scope']) as $file) {
            if (strpos(str_replace('\\', '/', $file), '/model/om/') !== false) {
                $files[] = $file;
            }
        }

        return $files;
    }

    public function testTheRatchetDroppedToTheMigratedState(): void
    {
        $budget = self::budget();
        $counts = CompatibilityLedger::counts($budget['scope'], [
            'strftime' => self::STRFTIME,
            'helper' => self::HELPER_USE,
        ]);

        self::assertSame(140, $counts['strftime']['textual'], 'Textual strftime hits must drop from 278 to 140.');
        self::assertSame(0, $counts['strftime']['code'], 'U-2.4.2 migrated the last two call sites, so nothing may call it.');
        self::assertSame(137, $counts['helper']['code'], 'The 136 migrated getters plus LegacyLocaleDate must consume the helper.');

        self::assertSame(140, $budget['budgets']['strftime'], 'The textual ratchet must be lowered with the migration.');
        self::assertSame(0, $budget['codeBudgets']['strftime'], 'U-2.4.2 lowered the executable ratchet to zero.');
        self::assertArrayHasKey('_noteU232', $budget, 'The unit must record why the ratchet moved.');
    }

    public function testEveryGeneratedGetterWasMigratedAndNoneWasMissed(): void
    {
        $migrated = 0;
        $stale = [];
        $touched = [];

        foreach (self::generatedFiles() as $file) {
            $source = PhpSourceScanner::read($file);
            $hits = substr_count($source, self::NEW_LINE);
            if ($hits > 0) {
                $migrated += $hits;
                $touched[] = CompatibilityLedger::relative($file);
            }

            if (strpos($source, self::OLD_LINE) !== false) {
                $stale[] = CompatibilityLedger::relative($file);
            }
        }

        self::assertSame([], $stale, 'These generated files still contain the pre-migration line.');
        self::assertSame(136, $migrated, 'All 136 inventoried getters must be migrated.');
        self::assertCount(59, array_unique($touched), 'The migration must touch exactly the 59 inventoried files.');
    }

    public function testNoGeneratedFileCallsTheDeprecatedFunctionAnyMore(): void
    {
        foreach (self::generatedFiles() as $file) {
            $projection = PhpSourceScanner::codeOnlySource(PhpSourceScanner::read($file));
            self::assertSame(
                0,
                PhpSourceScanner::matchCount($projection, self::STRFTIME),
                CompatibilityLedger::relative($file) . ' still calls the deprecated function.'
            );
        }
    }

    public function testMigratedLineIsUniformAndFullyQualified(): void
    {
        foreach (self::generatedFiles() as $file) {
            $source = PhpSourceScanner::read($file);
            if (strpos($source, 'LegacyStrftime') === false) {
                continue;
            }

            $projection = PhpSourceScanner::codeOnlySource($source);
            $lines = preg_split('/\r\n|\n|\r/', $source) ?: [];

            foreach (PhpSourceScanner::matchLines($projection, self::HELPER_USE) as $line) {
                self::assertSame(
                    self::NEW_LINE,
                    trim($lines[$line - 1] ?? ''),
                    CompatibilityLedger::relative($file) . ':' . $line . ' is not the uniform migrated line.'
                );
            }

            self::assertSame(
                0,
                PhpSourceScanner::matchCount($projection, '(?<![\\\\])(?<![\w$>-])use\s+ProcessMaker'),
                CompatibilityLedger::relative($file) . ' must not gain an import; generated files stay generator-shaped.'
            );
        }
    }

    public function testSurroundingGetterLogicIsUntouched(): void
    {
        $dateBranch = 0;
        $nullBranch = 0;
        $percentBranch = 0;

        foreach (self::generatedFiles() as $file) {
            $source = PhpSourceScanner::read($file);
            $dateBranch += substr_count($source, 'return date($format, $ts);');
            $nullBranch += substr_count($source, 'if ($format === null) {');
            $percentBranch += substr_count($source, '} elseif (strpos($format, \'%\') !== false) {');
        }

        self::assertSame(136, $dateBranch, 'The date() branch must remain exactly as generated.');
        self::assertSame(136, $nullBranch, 'The null-format branch must remain exactly as generated.');
        self::assertSame(136, $percentBranch, 'Only the strftime branch body changed, never the branch itself.');
    }

    public function testHelperIsByteIdenticalToTheAcceptedUnit(): void
    {
        self::assertSame(
            self::HELPER_SHA256,
            hash_file('sha256', PM_TEST_ROOT . '/' . self::HELPER),
            'U-2.3.2 migrates call sites only. The accepted U-2.3.2a helper must not change.'
        );
    }

    public function testDeferredHandwrittenSitesAreUntouched(): void
    {
        $file = PM_TEST_ROOT . '/workflow/engine/classes/Configurations.php';
        $projection = PhpSourceScanner::codeOnlySource(PhpSourceScanner::read($file));

        self::assertSame(0, PhpSourceScanner::matchCount($projection, self::STRFTIME), 'U-2.4.2 migrated both handwritten sites.');
        self::assertSame(0, PhpSourceScanner::matchCount($projection, self::HELPER_USE), 'The locale-aware sites use LegacyLocaleDate, not LegacyStrftime, because LegacyStrftime is locale independent.');
        self::assertSame(
            0,
            PhpSourceScanner::matchCount($projection, '(?<![\w$>-])utf8_encode\s*\(\s*strftime\s*\('),
            'The native utf8_encode(strftime(...)) pair was removed by U-2.3.3.'
        );
        self::assertSame(
            0,
            PhpSourceScanner::matchCount($projection, '(?<![\w$>-])LegacyUtf8::encode\s*\(\s*strftime\s*\('),
            'U-2.4.2 removed the wrapped pair too: LegacyLocaleDate returns UTF-8 directly.'
        );
        self::assertSame(
            2,
            PhpSourceScanner::matchCount($projection, '(?<![\w$>-])LegacyLocaleDate::format\s*\('),
            'Both branches of getSystemDate() must call the locale-aware helper.'
        );
    }

    public function testNoMaskInScopeUsesASpecifierTheHelperRefuses(): void
    {
        $offenders = [];
        foreach (CompatibilityLedger::phpFiles(self::budget()['scope']) as $file) {
            $relative = CompatibilityLedger::relative($file);
            if ($relative === self::HELPER) {
                continue;
            }

            if (preg_match('~[\'"][^\'"]*%[zZ]~', PhpSourceScanner::read($file)) === 1) {
                $offenders[] = $relative;
            }
        }

        self::assertSame(
            [],
            $offenders,
            'A literal mask containing %z or %Z would change behaviour, because the helper refuses both.'
        );
    }

    public function testInventoryAgreesWithTheMigratedTree(): void
    {
        $inventory = json_decode(
            (string) file_get_contents(PM_TEST_ROOT . '/tests/fixtures/strftime-inventory.json'),
            true,
            512,
            JSON_THROW_ON_ERROR
        );

        self::assertSame(140, $inventory['summary']['textual']);
        self::assertSame(0, $inventory['summary']['executable']);
        self::assertSame(140, $inventory['summary']['nonExecutable']);
        self::assertSame(0, $inventory['summary']['executableFiles']);
        self::assertSame(0, $inventory['categories']['generated_propel_getter']['count']);
        self::assertSame(0, $inventory['categories']['handwritten_locale_date']['count']);
        self::assertSame([], $inventory['executableSites']);
    }
}
