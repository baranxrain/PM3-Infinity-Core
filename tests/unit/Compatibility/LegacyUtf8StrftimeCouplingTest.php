<?php

namespace Tests\Unit\Compatibility;

use PHPUnit\Framework\TestCase;
use ProcessMaker\Util\LegacyUtf8;
use Tests\Support\CompatibilityLedger;
use Tests\Support\PhpSourceScanner;

require_once PM_TEST_ROOT . '/workflow/engine/src/ProcessMaker/Util/LegacyUtf8.php';

/**
 * U-2.3.3: the last executable native utf8_encode() call site,
 * workflow/engine/classes/Configurations.php:582, now delegates to
 * ProcessMaker\Util\LegacyUtf8::encode().
 *
 * The strftime() call it wraps is deliberately NOT migrated. Lines 581-585
 * call setlocale(LC_TIME, ...) so that %A, %B and %p render localized month
 * and day names (the es/pt formats such as "d \d\e F \d\e Y"), while
 * ProcessMaker\Util\LegacyStrftime is intentionally locale independent.
 * Replacing it here would silently anglicize user-visible dates, so the two
 * strftime() sites stay native and the strftime ratchet stays at 142 / 2.
 */
class LegacyUtf8StrftimeCouplingTest extends TestCase
{
    private const TARGET = 'workflow/engine/classes/Configurations.php';
    private const NATIVE_ENCODE = '(?<![\w$>-])utf8_encode\s*\(';
    private const NATIVE_UTF8 = '(?<![\w$>-])(?:utf8_encode|utf8_decode)\s*\(';
    private const NATIVE_PAIR = '(?<![\w$>-])utf8_encode\s*\(\s*strftime\s*\(';
    private const HELPER_PAIR = '(?<![\w$>-])LegacyUtf8::encode\s*\(\s*strftime\s*\(';
    private const STRFTIME = '(?<![\w$>-])(?:strftime|gmstrftime)\s*\(';
    private const STRFTIME_HELPER = '(?<![\w$>-])LegacyStrftime::';

    private const MIGRATED_LINE = '$dateTime = \\ProcessMaker\\Util\\LegacyLocaleDate::format($newCreation, mktime($h, $i, $s, $m, $d, $y), $langLocate);';
    private const UNTOUCHED_LINE = '$dateTime = strftime($newCreation, mktime($h, $i, $s, $m, $d, $y));';

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

    private static function target(): string
    {
        return PhpSourceScanner::read(PM_TEST_ROOT . '/' . self::TARGET);
    }

    private static function targetCode(): string
    {
        return PhpSourceScanner::codeOnlySource(self::target());
    }

    public function testTheLastExecutableEncodeCallSiteUsesTheHelper(): void
    {
        $code = self::targetCode();

        self::assertSame(0, PhpSourceScanner::matchCount($code, self::NATIVE_ENCODE), 'No native utf8_encode() may survive in Configurations.php.');
        self::assertSame(0, PhpSourceScanner::matchCount($code, self::HELPER_PAIR), 'U-2.4.2 removed the wrapped pair; LegacyLocaleDate returns UTF-8 directly.');
        self::assertSame(0, PhpSourceScanner::matchCount($code, self::NATIVE_PAIR), 'The native utf8_encode(strftime(...)) pair must be gone.');
        self::assertSame(2, substr_count(self::target(), self::MIGRATED_LINE), 'Both branches must call the locale-aware helper with their original arguments.');
    }

    public function testTheCoupledStrftimeCallsStayNativeAndLocaleAware(): void
    {
        $code = self::targetCode();
        $raw = self::target();

        self::assertSame(0, PhpSourceScanner::matchCount($code, self::STRFTIME), 'U-2.4.2 migrated both locale-date calls.');
        self::assertSame([], PhpSourceScanner::matchLines($code, self::STRFTIME), 'No native strftime() line may remain.');
        self::assertSame(0, PhpSourceScanner::matchCount($code, self::STRFTIME_HELPER), 'The locale-aware sites must not use the locale-independent LegacyStrftime directly.');
        self::assertStringNotContainsString(self::UNTOUCHED_LINE, $raw, 'The native branch line must be gone.');
        self::assertSame(2, PhpSourceScanner::matchCount($code, '(?<![\w$>-])setlocale\s*\(\s*LC_TIME'), 'Both setlocale(LC_TIME, ...) calls must remain, they are what localizes %A/%B.');
    }

    public function testSurroundingGetSystemDateLogicIsUntouched(): void
    {
        $code = self::targetCode();

        self::assertSame(1, PhpSourceScanner::matchCount($code, 'function\s+getSystemDate\s*\('), 'getSystemDate() must still exist exactly once.');
        self::assertSame(1, PhpSourceScanner::matchCount($code, 'ucwords\s*\(\s*\$dateTime\s*\)'), 'The ucwords() post-processing must be untouched.');
        // String literals are blanked in the code-only projection, so the two
        // literal-dependent guards below run against the raw source.
        $raw = self::target();
        self::assertSame(1, PhpSourceScanner::matchCount($raw, "str_replace\\(\\s*'\\[xx\\]'"), 'The [xx] replacement must be untouched.');
        self::assertSame(1, PhpSourceScanner::matchCount($raw, "defined\\(\\s*'PARTNER_FLAG'\\s*\\)"), 'The PARTNER_FLAG branch must be untouched.');
    }

    public function testTheUtf8RatchetDroppedToFiveTextualAndZeroExecutable(): void
    {
        $budget = self::budget();
        $counts = CompatibilityLedger::counts($budget['scope'], [
            'utf8' => self::NATIVE_UTF8,
            'strftime' => self::STRFTIME,
            'strftimeHelper' => self::STRFTIME_HELPER,
        ]);

        self::assertSame(4, $counts['utf8']['textual'], 'U-2.3.3 dropped this to 5; U-6 dropped it again to 4.');
        self::assertSame(0, $counts['utf8']['code'], 'No executable native UTF-8 call site may remain in scope.');
        self::assertSame(4, $budget['budgets']['utf8_encode_decode'], 'The textual ratchet must be lowered with the migration.');
        self::assertSame(0, $budget['codeBudgets']['utf8_encode_decode'], 'The executable ratchet must be lowered with the migration.');
        self::assertArrayHasKey('_noteU233', $budget, 'The unit must record why the ratchet moved.');

        self::assertSame(140, $counts['strftime']['textual'], 'U-2.3.3 must not move the strftime textual ratchet.');
        self::assertSame(0, $counts['strftime']['code'], 'U-2.4.2 lowered the strftime executable ratchet to zero.');
        self::assertSame(137, $counts['strftimeHelper']['code'], 'The 136 accepted U-2.3.2 consumers plus LegacyLocaleDate must be present.');
        self::assertSame(140, $budget['budgets']['strftime']);
        self::assertSame(0, $budget['codeBudgets']['strftime']);
    }

    public function testTheRemainingTextualHitsAreTheKnownCommentOnlyOnes(): void
    {
        $budget = self::budget();
        $perFile = [];
        foreach (CompatibilityLedger::phpFiles($budget['scope']) as $file) {
            $hits = PhpSourceScanner::matchCount(PhpSourceScanner::read($file), self::NATIVE_UTF8);
            if ($hits > 0) {
                $perFile[CompatibilityLedger::relative($file)] = $hits;
            }
        }
        ksort($perFile);

        self::assertSame(
            [
                'gulliver/system/class.g.php' => 1,
                'workflow/engine/classes/model/Translation.php' => 3,
            ],
            $perFile,
            'Only comment hits may remain after U-6 migrated the PMScript generated string.'
        );

        self::assertSame(
            [],
            CompatibilityLedger::locate($budget['scope'], $budget['patterns']['utf8_encode_decode'], 20),
            'The ledger must not find any executable UTF-8 call site.'
        );
    }

    public function testTheHelperStillMatchesTheNativeEncoderForEveryByte(): void
    {
        for ($byte = 0; $byte <= 255; ++$byte) {
            self::assertSame(
                utf8_encode(chr($byte)),
                LegacyUtf8::encode(chr($byte)),
                'Helper diverges on byte ' . $byte
            );
        }

        // A latin-1 payload shaped like the localized strftime() output the
        // migrated line has to encode ("mi\xE9rcoles", "Febrero").
        $payload = "mi\xE9rcoles, 02 de Febrero de 2013";
        self::assertSame(utf8_encode($payload), LegacyUtf8::encode($payload), 'Localized date payload must encode identically.');
    }
}
