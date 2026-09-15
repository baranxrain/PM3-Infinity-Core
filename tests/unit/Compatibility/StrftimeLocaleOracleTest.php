<?php

declare(strict_types=1);

namespace Tests\Unit\Compatibility;

use PHPUnit\Framework\TestCase;
use Tests\Support\CompatibilityLedger;
use Tests\Support\PhpSourceScanner;

/**
 * U-2.4.1 guard: the locale-dependent behaviour of the last two executable
 * strftime() sites (Configurations.php:582 and :585) is captured as evidence,
 * and nothing is migrated.
 *
 * Two different kinds of assertion live here, deliberately separated:
 *  - Pure logic: the mask translation table of Configurations::getSystemDate()
 *    is reimplemented here and the derived strftime masks are asserted. This
 *    runs identically on every platform.
 *  - Schema only: the captured oracle fixture must exist, describe the
 *    acceptance runtime, and cover every locale name and mask the production
 *    branch can reach. Its rendered values are runtime facts and are never
 *    asserted, because Windows month names are not reproducible off-platform.
 */
final class StrftimeLocaleOracleTest extends TestCase
{
    private const TARGET = 'workflow/engine/classes/Configurations.php';

    private const ORACLE = '/tests/fixtures/legacy-strftime-locale-oracle.json';

    private const STRFTIME_PATTERN = '(?<![\w$>-])(?:strftime|gmstrftime)\s*\(';

    /**
     * The mask translation table of Configurations::getSystemDate(), with PHP's
     * duplicate-key resolution already applied: the production literal assigns
     * 'G' twice ('%I' then '%H') and 'g' twice ('%i' then '%I'), and the last
     * assignment wins.
     *
     * @var array<string, string>
     */
    private const MASK_TIME = [
        'd' => '%d', 'D' => '%A', 'j' => '%d', 'l' => '%A', 'N' => '%u',
        'S' => '%d', 'w' => '%w', 'z' => '%j', 'W' => '%W', 'F' => '%B',
        'm' => '%m', 'M' => '%B', 'n' => '%m', 'o' => '%Y', 'Y' => '%Y',
        'y' => '%g', 'a' => '%p', 'A' => '%p', 'g' => '%I', 'G' => '%H',
        'h' => '%I', 'H' => '%H', 'i' => '%M', 's' => '%S',
    ];

    /** @var array<string, string> */
    private const DATE_FORMATS = [
        'ID_DATE_FORMAT_1' => 'Y-m-d H:i:s',
        'ID_DATE_FORMAT_2' => 'd/m/Y',
        'ID_DATE_FORMAT_3' => 'm/d/Y',
        'ID_DATE_FORMAT_4' => 'Y/d/m',
        'ID_DATE_FORMAT_5' => 'Y/m/d',
        'ID_DATE_FORMAT_6' => 'F j, Y, g:i a',
        'ID_DATE_FORMAT_7' => 'm.d.y',
        'ID_DATE_FORMAT_8' => 'j, n, Y',
        'ID_DATE_FORMAT_9' => 'D M j G:i:s T Y',
        'ID_DATE_FORMAT_15' => 'M d, Y',
        'ID_DATE_FORMAT_16' => 'm D, Y',
        'ID_DATE_FORMAT_10' => 'D d M, Y',
        'ID_DATE_FORMAT_11' => 'D M, Y',
        'ID_DATE_FORMAT_12' => 'd M, Y',
        'ID_DATE_FORMAT_13' => 'd m, Y',
        'ID_DATE_FORMAT_14' => 'd.m.Y',
        'ID_DATE_FORMAT_17' => 'd \d\e F \d\e Y',
    ];

    private static function read(string $relative): string
    {
        return PhpSourceScanner::read(PM_TEST_ROOT . '/' . $relative);
    }

    /** @return array<string, mixed> */
    private static function oracle(): array
    {
        static $oracle = null;
        if ($oracle === null) {
            $path = PM_TEST_ROOT . self::ORACLE;
            self::assertFileExists(
                $path,
                'Run tests\\tools\\generate-strftime-locale-oracle.php on the acceptance runtime first; the fixture is captured, never committed from a foreign platform.'
            );
            $contents = file_get_contents($path);
            self::assertIsString($contents, 'The U-2.4.1 locale oracle is unreadable.');
            $oracle = json_decode($contents, true, 512, JSON_THROW_ON_ERROR);
        }

        return $oracle;
    }

    private static function translate(string $mask): string
    {
        $mask = trim($mask);
        if (strpos($mask, ' \d\e ') !== false) {
            $mask = str_replace(' \d\e ', ' [xx] ', $mask);
        }

        $out = '';
        for ($i = 0; $i < strlen($mask); ++$i) {
            $out .= ($mask[$i] !== ' ' && isset(self::MASK_TIME[$mask[$i]])) ? self::MASK_TIME[$mask[$i]] : $mask[$i];
        }

        return $out;
    }

    public function testTheProductionMaskTableIsStillTheOneReimplementedHere(): void
    {
        $raw = self::read(self::TARGET);

        foreach (self::MASK_TIME as $from => $to) {
            self::assertStringContainsString(
                "'" . $from . "' => '" . $to . "'",
                $raw,
                'The production mask table no longer contains the pair this unit reimplemented: ' . $from
            );
        }

        // The two entries that are assigned twice in the production literal.
        self::assertStringContainsString("'G' => '%I'", $raw, 'The first, overridden G assignment is part of the frozen literal.');
        self::assertStringContainsString("'g' => '%i'", $raw, 'The first, overridden g assignment is part of the frozen literal.');

        foreach (array_keys(self::DATE_FORMATS) as $id) {
            self::assertStringContainsString($id, $raw, 'The production date format list no longer offers ' . $id);
        }
    }

    public function testTheDerivedStrftimeMasksAreStable(): void
    {
        $derived = [];
        foreach (self::DATE_FORMATS as $id => $mask) {
            $derived[$id] = self::translate($mask);
        }

        self::assertSame(
            [
                'ID_DATE_FORMAT_1' => '%Y-%m-%d %H:%M:%S',
                'ID_DATE_FORMAT_2' => '%d/%m/%Y',
                'ID_DATE_FORMAT_3' => '%m/%d/%Y',
                'ID_DATE_FORMAT_4' => '%Y/%d/%m',
                'ID_DATE_FORMAT_5' => '%Y/%m/%d',
                'ID_DATE_FORMAT_6' => '%B %d, %Y, %I:%M %p',
                'ID_DATE_FORMAT_7' => '%m.%d.%g',
                'ID_DATE_FORMAT_8' => '%d, %m, %Y',
                'ID_DATE_FORMAT_9' => '%A %B %d %H:%M:%S T %Y',
                'ID_DATE_FORMAT_15' => '%B %d, %Y',
                'ID_DATE_FORMAT_16' => '%m %A, %Y',
                'ID_DATE_FORMAT_10' => '%A %d %B, %Y',
                'ID_DATE_FORMAT_11' => '%A %B, %Y',
                'ID_DATE_FORMAT_12' => '%d %B, %Y',
                'ID_DATE_FORMAT_13' => '%d %m, %Y',
                'ID_DATE_FORMAT_14' => '%d.%m.%Y',
                'ID_DATE_FORMAT_17' => '%d [xx] %B [xx] %Y',
            ],
            $derived,
            'The strftime masks getSystemDate() builds from the shipped date formats changed.'
        );
    }

    public function testTheSpanishFormatIsTheOnlyOneThatNeedsThePlaceholder(): void
    {
        $withPlaceholder = [];
        foreach (self::DATE_FORMATS as $id => $mask) {
            if (strpos(self::translate($mask), '[xx]') !== false) {
                $withPlaceholder[] = $id;
            }
        }

        self::assertSame(['ID_DATE_FORMAT_17'], $withPlaceholder, 'Only the Spanish long format uses the [xx] placeholder.');

        $raw = self::read(self::TARGET);
        self::assertStringContainsString("str_replace('[xx]', ' de ', \$dateTime)", $raw, 'The [xx] replacement must stay untouched by U-2.4.1.');
    }

    public function testEveryDerivedMaskOnlyUsesSpecifiersTheRuntimeSupports(): void
    {
        // The support set is not guessed: U-2.3.1 already captured it on the
        // acceptance runtime, so this reads that evidence instead.
        $specifierOracle = json_decode((string) file_get_contents(PM_TEST_ROOT . '/tests/fixtures/legacy-strftime-oracle.json'), true, 512, JSON_THROW_ON_ERROR);
        $unsupported = $specifierOracle['unsupportedSpecifiers'];
        self::assertNotSame([], $specifierOracle['specifiers'], 'The U-2.3.1 specifier oracle must be present to judge support.');

        foreach (self::DATE_FORMATS as $id => $mask) {
            $derived = self::translate($mask);
            preg_match_all('~%.~', $derived, $matches);
            foreach ($matches[0] as $specifier) {
                self::assertNotContains(
                    $specifier,
                    $unsupported,
                    'Mask ' . $id . ' relies on ' . $specifier . ', which the acceptance runtime does not support.'
                );
                self::assertArrayHasKey(
                    $specifier,
                    $specifierOracle['specifiers'],
                    'Mask ' . $id . ' relies on ' . $specifier . ', which the U-2.3.1 oracle never captured.'
                );
            }
        }
    }

    public function testTheOracleDescribesTheAcceptanceRuntime(): void
    {
        $oracle = self::oracle();

        self::assertArrayHasKey('_note', $oracle, 'The oracle must state that it is evidence only.');
        self::assertStringContainsString('evidence only', $oracle['_note']);
        self::assertSame('Windows', $oracle['runtime']['phpOs'], 'The oracle must be captured on the acceptance runtime, not on a foreign platform.');
        self::assertStringStartsWith('8.1.', $oracle['runtime']['phpVersion'], 'The oracle must be captured under PHP 8.1.');
        self::assertSame('UTC', $oracle['runtime']['timezoneUsed'], 'The capture must pin the timezone so the values are comparable.');
        self::assertNotSame('', (string) $oracle['runtime']['generatedAtUtc'], 'The oracle must record when it was captured.');
    }

    public function testTheOracleCoversEveryMaskAndLocaleTheBranchCanReach(): void
    {
        $oracle = self::oracle();

        self::assertSame(self::DATE_FORMATS, $oracle['dateFormats'], 'The oracle must cover exactly the shipped date formats.');

        foreach (self::DATE_FORMATS as $id => $mask) {
            self::assertArrayHasKey($id, $oracle['translatedMasks'], 'The oracle is missing mask ' . $id);
            self::assertSame(self::translate($mask), $oracle['translatedMasks'][$id]['strftimeMask'], 'The oracle disagrees about the derived mask for ' . $id);
        }

        foreach (['windows_en', 'windows_es', 'windows_pt', 'windows_en_utf8', 'windows_es_utf8', 'windows_pt_utf8', 'glibc_en', 'glibc_es', 'glibc_pt', 'c_locale'] as $name) {
            self::assertArrayHasKey($name, $oracle['locales'], 'The oracle is missing locale ' . $name);
            self::assertArrayHasKey('requested', $oracle['locales'][$name]);
            self::assertArrayHasKey('accepted', $oracle['locales'][$name]);
        }

        self::assertSame('EST', $oracle['locales']['windows_en']['requested'], 'The Windows English branch of getSystemDate() passes EST.');
        self::assertSame('ESN', $oracle['locales']['windows_es']['requested'], 'The Windows Spanish branch of getSystemDate() passes ESN.');
        self::assertSame('PTB', $oracle['locales']['windows_pt']['requested'], 'The Windows Portuguese branch of getSystemDate() passes PTB.');
        self::assertNotSame([], $oracle['acceptedLocales'], 'The runtime accepted no locale at all, so the capture is useless.');
    }

    public function testEveryAcceptedLocaleCarriesPortableEvidence(): void
    {
        $oracle = self::oracle();

        foreach ($oracle['acceptedLocales'] as $name) {
            $row = $oracle['locales'][$name];
            self::assertCount(count(self::DATE_FORMATS), $row['masks'], 'Locale ' . $name . ' did not capture every mask.');
            self::assertCount(12, $row['monthNames'], 'Locale ' . $name . ' did not capture twelve month names.');
            self::assertCount(7, $row['dayNames'], 'Locale ' . $name . ' did not capture seven day names.');

            foreach ($row['masks'] as $id => $stamps) {
                foreach ($stamps as $stamp => $capture) {
                    self::assertArrayHasKey('hex', $capture, 'Missing byte evidence for ' . $name . '/' . $id . '/' . $stamp);
                    self::assertArrayNotHasKey('value', $capture, 'The raw single-byte rendering must not be stored: json_encode() would mangle it.');
                    if ($capture['hex'] !== null) {
                        self::assertMatchesRegularExpression('~^([0-9a-f]{2})*$~', $capture['hex'], 'Byte evidence must be lowercase, byte-aligned hexadecimal.');
                        $decoded = hex2bin($capture['hex']);
                        self::assertIsString($decoded, 'Byte evidence must decode for ' . $name . '/' . $id . '/' . $stamp);
                        self::assertSame(
                            utf8_encode($decoded),
                            (string) ($capture['utf8'] ?? ''),
                            'The stored bytes and their ISO-8859-1 lift disagree for ' . $name . '/' . $id . '/' . $stamp
                        );
                    }
                }
            }
        }
    }

    public function testU241MigratesNothing(): void
    {
        $budget = json_decode((string) file_get_contents(PM_TEST_ROOT . '/tests/fixtures/deprecation-budget.json'), true, 512, JSON_THROW_ON_ERROR);
        $counts = CompatibilityLedger::counts($budget['scope'], [
            'strftime' => $budget['patterns']['strftime'],
            'utf8' => $budget['patterns']['utf8_encode_decode'],
        ]);

        self::assertSame(140, $counts['strftime']['textual'], 'U-2.4.1 must not move the textual strftime ledger.');
        self::assertSame(0, $counts['strftime']['code'], 'U-2.4.2 migrated both locale-aware call sites.');
        self::assertSame(140, $budget['budgets']['strftime'], 'The textual strftime ratchet must stay where U-2.3.2 left it.');
        self::assertSame(0, $budget['codeBudgets']['strftime'], 'U-2.4.2 lowered the executable strftime ratchet to zero.');
        self::assertSame(4, $counts['utf8']['textual'], 'U-2.4.1 must not move the UTF-8 ledger U-6 lowered to four.');
        self::assertSame(0, $counts['utf8']['code'], 'The executable UTF-8 ledger must stay at zero.');

        self::assertSame(
            [],
            PhpSourceScanner::matchLines(self::read(self::TARGET), self::STRFTIME_PATTERN),
            'No native strftime line may remain after U-2.4.2.'
        );
    }
}
