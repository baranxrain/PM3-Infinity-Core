<?php

declare(strict_types=1);

namespace Tests\Unit\Compatibility;

use PHPUnit\Framework\TestCase;
use ProcessMaker\Util\LegacyLocaleDate;
use ProcessMaker\Util\LegacyStrftime;
use Tests\Support\PhpSourceScanner;

require_once PM_TEST_ROOT . '/workflow/engine/src/ProcessMaker/Util/LegacyStrftime.php';
require_once PM_TEST_ROOT . '/workflow/engine/src/ProcessMaker/Util/LegacyLocaleDate.php';

/**
 * U-2.4.2 guard: the last two executable strftime() call sites,
 * Configurations.php:582 and :585, now render through
 * ProcessMaker\Util\LegacyLocaleDate.
 *
 * Two kinds of assertion live here, deliberately separated:
 *  - Evidence-based: every rendering the helper produces is compared byte for
 *    byte against the oracle U-2.4.1 captured on the acceptance runtime. The
 *    comparison always uses the oracle's `hex` field, never its `utf8` field,
 *    because for the '.utf8' locales the runtime already answered in UTF-8 and
 *    the capture lifted those bytes a second time.
 *  - Pure logic: language resolution, delegation and refusal behaviour, which
 *    run identically on every platform.
 *
 * The English expectation is taken from the C-locale capture on purpose. The
 * Windows CRT resolved the shipped 'EST' name to Spanish_United States, so the
 * English and default branch of getSystemDate() used to render Spanish month
 * and day names. By owner decision U-2.4.2 fixes that, and the test below
 * pins both halves of the fix: English output for 'EST', and a documented
 * difference from what the runtime used to produce.
 */
final class LegacyLocaleDateTest extends TestCase
{
    private const ORACLE = '/tests/fixtures/legacy-strftime-locale-oracle.json';

    private const TARGET = 'workflow/engine/classes/Configurations.php';

    private const HELPER = 'workflow/engine/src/ProcessMaker/Util/LegacyLocaleDate.php';

    /**
     * Oracle locale entries whose captured bytes the helper must reproduce
     * exactly, mapped onto the legacy locale name production passes in.
     *
     * @var array<string, string>
     */
    private const FAITHFUL = [
        'c_locale' => 'C',
        'windows_es_utf8' => 'ESN.utf8',
        'windows_pt_utf8' => 'PTB.utf8',
    ];

    /** @return array<string, mixed>|null */
    private static function oracle(): ?array
    {
        static $oracle = false;
        if ($oracle === false) {
            $path = PM_TEST_ROOT . self::ORACLE;
            $oracle = is_file($path)
                ? json_decode((string) file_get_contents($path), true, 512, JSON_THROW_ON_ERROR)
                : null;
        }

        return $oracle;
    }

    /** @return array<string, mixed> */
    private static function requireOracle(): array
    {
        $oracle = self::oracle();
        if ($oracle === null) {
            self::markTestSkipped(
                'The locale oracle fixture is captured on the acceptance runtime by '
                . 'tests/tools/generate-strftime-locale-oracle.php; run the offline check script first.'
            );
        }

        return $oracle;
    }

    public function testEveryFaithfulLocaleReproducesTheCapturedBytesForEveryMask(): void
    {
        $oracle = self::requireOracle();
        $compared = 0;

        foreach (self::FAITHFUL as $localeKey => $legacyName) {
            self::assertArrayHasKey($localeKey, $oracle['locales'], 'The oracle must cover ' . $localeKey . '.');
            $entry = $oracle['locales'][$localeKey];
            self::assertTrue($entry['accepted'], $localeKey . ' must be an accepted locale on the acceptance runtime.');

            foreach ($oracle['translatedMasks'] as $formatId => $translation) {
                $mask = $translation['strftimeMask'];

                foreach ($oracle['timestamps'] as $label => $timestamp) {
                    $expected = hex2bin($entry['masks'][$formatId][$label]['hex']);
                    $actual = LegacyLocaleDate::format($mask, (int) $timestamp, $legacyName);

                    self::assertSame(
                        $expected,
                        $actual,
                        sprintf(
                            'Rendering drifted for %s / %s / %s (mask %s): expected %s, got %s.',
                            $localeKey,
                            $formatId,
                            $label,
                            $mask,
                            bin2hex((string) $expected),
                            bin2hex((string) $actual)
                        )
                    );
                    ++$compared;
                }
            }
        }

        self::assertSame(count(self::FAITHFUL) * 17 * 3, $compared, 'Every locale, mask and timestamp must be compared.');
    }

    public function testTheEnglishBranchNowRendersEnglishInsteadOfSpanish(): void
    {
        $oracle = self::requireOracle();
        $english = $oracle['locales']['c_locale']['masks'];
        $captured = $oracle['locales']['windows_en_utf8']['masks'];
        $divergences = 0;

        foreach ($oracle['translatedMasks'] as $formatId => $translation) {
            foreach ($oracle['timestamps'] as $label => $timestamp) {
                $expected = hex2bin($english[$formatId][$label]['hex']);
                $wasRendered = hex2bin($captured[$formatId][$label]['hex']);

                foreach (['EST', 'EST.utf8', 'en', 'en_US', 'en-US', 'not-a-locale'] as $name) {
                    self::assertSame(
                        $expected,
                        LegacyLocaleDate::format($translation['strftimeMask'], (int) $timestamp, $name),
                        'The English and default branch must render the English names for ' . $name . '.'
                    );
                }

                if ($expected !== $wasRendered) {
                    ++$divergences;
                }
            }
        }

        self::assertGreaterThan(
            0,
            $divergences,
            'The fixed defect must be visible: the runtime used to render Spanish names for the English locale name.'
        );
    }

    public function testTheCapturedSpanishAndPortugueseMeridiemStaysEmpty(): void
    {
        self::requireOracle();

        // 1104537845 is 2005-01-01 00:04:05 UTC, 1119873600 is 2005-06-27 12:00:00 UTC.
        self::assertSame('12:04 ', LegacyLocaleDate::format('%I:%M %p', 1104537845, 'ESN.utf8'));
        self::assertSame('12:00 ', LegacyLocaleDate::format('%I:%M %p', 1119873600, 'PTB.utf8'));
        self::assertSame('12:04 AM', LegacyLocaleDate::format('%I:%M %p', 1104537845, 'EST'));
        self::assertSame('12:00 PM', LegacyLocaleDate::format('%I:%M %p', 1119873600, 'EST'));
    }

    public function testEveryRenderingIsValidUtf8(): void
    {
        $oracle = self::requireOracle();

        foreach (array_merge(array_values(self::FAITHFUL), ['EST', 'EST.utf8']) as $name) {
            foreach ($oracle['translatedMasks'] as $translation) {
                foreach ($oracle['timestamps'] as $timestamp) {
                    $rendered = LegacyLocaleDate::format($translation['strftimeMask'], (int) $timestamp, $name);
                    self::assertIsString($rendered);
                    self::assertTrue(
                        (bool) preg_match('//u', $rendered),
                        'The helper must always answer in UTF-8, so no call site needs a lift.'
                    );
                }
            }
        }
    }

    /** @return array<string, array{0: string, 1: string}> */
    public static function localeNameProvider(): array
    {
        return [
            'windows english' => ['EST', 'en'],
            'windows english utf8' => ['EST.utf8', 'en'],
            'windows spanish' => ['ESN', 'es'],
            'windows spanish utf8' => ['ESN.utf8', 'es'],
            'windows portuguese' => ['PTB', 'pt'],
            'windows portuguese utf8' => ['PTB.utf8', 'pt'],
            'glibc english' => ['en_US', 'en'],
            'glibc spanish' => ['es_ES', 'es'],
            'glibc spanish utf8' => ['es_ES.utf8', 'es'],
            'glibc portuguese' => ['pt_BR', 'pt'],
            'hyphenated portuguese' => ['pt-BR', 'pt'],
            'bare spanish' => ['es', 'es'],
            'bare portuguese' => ['pt', 'pt'],
            'c locale' => ['C', 'en'],
            'lowercase windows' => ['esn', 'es'],
            'padded name' => ['  PTB.utf8  ', 'pt'],
            'unknown name' => ['ja_JP', 'en'],
            'empty name' => ['', 'en'],
        ];
    }

    /** @dataProvider localeNameProvider */
    #[\PHPUnit\Framework\Attributes\DataProvider('localeNameProvider')]
    public function testLegacyLocaleNamesResolveByLanguage(string $locale, string $expected): void
    {
        self::assertSame($expected, LegacyLocaleDate::language($locale));
        self::assertContains($expected, LegacyLocaleDate::supportedLanguages());
    }

    public function testLocaleIndependentSpecifiersAreDelegatedToTheAcceptedHelper(): void
    {
        $timestamp = 1609459199;

        foreach (['%Y-%m-%d %H:%M:%S', '%d/%m/%Y', '%j %u %W %g', '%%', '%n%t', '%R %T', '%D %F'] as $mask) {
            foreach (['EST', 'ESN', 'PTB'] as $locale) {
                self::assertSame(
                    LegacyStrftime::format($mask, $timestamp),
                    LegacyLocaleDate::format($mask, $timestamp, $locale),
                    'Locale-independent specifiers must keep a single implementation.'
                );
            }
        }
    }

    public function testRefusedSpecifiersAreStillRefused(): void
    {
        foreach (LegacyStrftime::refusedSpecifiers() as $specifier) {
            self::assertFalse(
                LegacyLocaleDate::format('prefix ' . $specifier, 1609459199, 'EST'),
                'The helper must refuse ' . $specifier . ' exactly like the accepted contract does.'
            );
        }

        self::assertSame(
            ['%a', '%A', '%b', '%B', '%p'],
            LegacyLocaleDate::localeDependentSpecifiers(),
            'Only these five specifiers depended on the locale at the migrated call sites.'
        );
    }

    public function testLiteralTextAndTrailingPercentArePreserved(): void
    {
        self::assertSame(
            '01 [xx] January [xx] 2005',
            LegacyLocaleDate::format('%d [xx] %B [xx] %Y', 1104537845, 'EST'),
            'The [xx] placeholder the caller replaces with " de " must survive untouched.'
        );
        self::assertSame('%', LegacyLocaleDate::format('%', 1104537845, 'EST'), 'A trailing percent is literal text.');
        self::assertSame('', LegacyLocaleDate::format('', 1104537845, 'EST'));
    }

    public function testBothProductionCallSitesUseTheHelperAndNothingCallsStrftime(): void
    {
        $raw = PhpSourceScanner::read(PM_TEST_ROOT . '/' . self::TARGET);
        $code = PhpSourceScanner::codeOnlySource($raw);

        self::assertSame(
            2,
            PhpSourceScanner::matchCount($code, '(?<![\w$>-])LegacyLocaleDate::format\s*\('),
            'Both branches of getSystemDate() must call the locale-aware helper.'
        );
        self::assertSame(
            0,
            PhpSourceScanner::matchCount($code, '(?<![\w$>-])(?:strftime|gmstrftime)\s*\('),
            'No native strftime() call may remain in Configurations.php.'
        );
        self::assertSame(
            2,
            PhpSourceScanner::matchCount($code, '(?<![\w$>-])setlocale\s*\(\s*LC_TIME'),
            'Both setlocale(LC_TIME, ...) calls stay: other code in the request may still read LC_TIME.'
        );
        self::assertSame(
            0,
            PhpSourceScanner::matchCount($code, '(?<![\w$>-])LegacyUtf8::'),
            'Line 582 no longer needs a UTF-8 lift, so Configurations.php left the LegacyUtf8 consumer list.'
        );
        self::assertSame(
            1,
            PhpSourceScanner::matchCount($raw, "str_replace\\(\\s*'\\[xx\\]'"),
            'The [xx] post-processing must be untouched.'
        );
        self::assertSame(
            1,
            PhpSourceScanner::matchCount($raw, "defined\\(\\s*'PARTNER_FLAG'\\s*\\)"),
            'The PARTNER_FLAG branch must be untouched.'
        );
    }

    public function testTheHelperIsSelfContainedAndDocumented(): void
    {
        $raw = PhpSourceScanner::read(PM_TEST_ROOT . '/' . self::HELPER);
        $code = PhpSourceScanner::codeOnlySource($raw);

        self::assertTrue((bool) preg_match('//u', $raw), 'The helper source must be valid UTF-8.');
        self::assertStringStartsWith('<?php', $raw, 'The helper must not carry a UTF-8 BOM or any leading output.');
        self::assertSame(
            0,
            PhpSourceScanner::matchCount($code, '(?<![\w$>-])(?:strftime|gmstrftime|setlocale|utf8_encode|utf8_decode)\s*\('),
            'The helper must not reintroduce any of the deprecated functions the ledger tracks.'
        );
        self::assertSame(
            1,
            PhpSourceScanner::matchCount($code, '(?<![\w$>-])LegacyStrftime::format\s*\('),
            'Locale-independent specifiers must be delegated in exactly one place.'
        );
        self::assertStringContainsString(
            'legacy-strftime-locale-oracle.json',
            $raw,
            'The helper must name the evidence its tables were transcribed from.'
        );
    }
}
