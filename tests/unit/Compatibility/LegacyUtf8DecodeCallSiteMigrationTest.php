<?php

declare(strict_types=1);

namespace Tests\Unit\Compatibility;

use PHPUnit\Framework\TestCase;
use Tests\Support\LegacyUtf8Oracle;
use ProcessMaker\Util\LegacyUtf8;
use RuntimeException;
use Tests\Support\CompatibilityLedger;
use Tests\Support\PhpSourceScanner;

require_once PM_TEST_ROOT . '/workflow/engine/src/ProcessMaker/Util/LegacyUtf8.php';

/**
 * U-2.2.3 guard: both executable utf8_decode call sites now delegate to
 * ProcessMaker\Util\LegacyUtf8::decode(). Every behavioural assertion is
 * byte-for-byte against the native function, and every structural assertion is
 * made on the code-only projection of the migrated files, so comments and
 * string literals cannot fake a pass.
 */
final class LegacyUtf8DecodeCallSiteMigrationTest extends TestCase
{
    private const ENCODE_PATTERN = '(?<![\w$>-])utf8_encode\s*\(';
    private const DECODE_PATTERN = '(?<![\w$>-])utf8_decode\s*\(';
    private const HELPER_DECODE_PATTERN = '(?<![\w$>-])LegacyUtf8::decode\s*\(';

    /**
     * The exact scope approved for U-2.2.3: relative file => migrated decode
     * call sites in that file.
     *
     * @return array<string, int>
     */
    private static function migratedFiles(): array
    {
        return [
            'workflow/engine/classes/SpoolRun.php' => 1,
            'workflow/engine/methods/events/eventsSetupGraph.php' => 1,
        ];
    }

    private static function read(string $relativeFile): string
    {
        $file = PM_TEST_ROOT . '/' . $relativeFile;
        if (!is_file($file)) {
            throw new RuntimeException('Missing migrated file: ' . $relativeFile);
        }

        return PhpSourceScanner::read($file);
    }

    private static function codeOnly(string $relativeFile): string
    {
        return PhpSourceScanner::codeOnlySource(self::read($relativeFile));
    }

    public function testEveryMigratedFileStillParsesUnderPhpEightOne(): void
    {
        foreach (array_keys(self::migratedFiles()) as $relativeFile) {
            $tokens = token_get_all(self::read($relativeFile), TOKEN_PARSE);
            $this->assertNotSame([], $tokens, $relativeFile . ' must tokenise under PHP 8.1');
        }
    }

    public function testMigratedFilesCallTheHelperExactlyOncePerSite(): void
    {
        foreach (self::migratedFiles() as $relativeFile => $expected) {
            $projection = self::codeOnly($relativeFile);
            $this->assertSame(
                $expected,
                PhpSourceScanner::matchCount($projection, self::HELPER_DECODE_PATTERN),
                $relativeFile . ' must call LegacyUtf8::decode() exactly ' . $expected . ' time(s)'
            );
        }
    }

    public function testNoExecutableNativeDecodeRemainsInMigratedFiles(): void
    {
        foreach (array_keys(self::migratedFiles()) as $relativeFile) {
            $projection = self::codeOnly($relativeFile);
            $this->assertSame(
                0,
                PhpSourceScanner::matchCount($projection, self::DECODE_PATTERN),
                $relativeFile . ' must not contain an executable native LegacyUtf8Oracle::decode() call'
            );
        }
    }

    public function testMigratedCallSitesKeepTheirOriginalArgumentExpressions(): void
    {
        // codeOnlySource() blanks string literals, so the array key itself can
        // only be asserted on the raw source; the projection is used for the
        // structural assertion that exactly one fileData value is decoded.
        $spoolRun = self::codeOnly('workflow/engine/classes/SpoolRun.php');
        $this->assertSame(
            1,
            PhpSourceScanner::matchCount($spoolRun, 'LegacyUtf8::decode\(\$this->fileData\['),
            'Exactly one SpoolRun fileData value must be decoded'
        );
        $this->assertSame(
            1,
            PhpSourceScanner::matchCount(
                self::read('workflow/engine/classes/SpoolRun.php'),
                'LegacyUtf8::decode\(\$this->fileData\[\s*.from_name.\s*\]\)'
            ),
            'The SpoolRun sender name must still be the decoded argument'
        );

        $graph = self::codeOnly('workflow/engine/methods/events/eventsSetupGraph.php');
        $this->assertSame(
            1,
            PhpSourceScanner::matchCount(
                $graph,
                'LegacyUtf8::decode\(\s*G::LoadTranslation\('
            ),
            'The GD label must still decode the loaded translation'
        );
    }

    public function testTheLastEncodeCallSiteWasMigratedByTheFollowUpUnit(): void
    {
        $projection = self::codeOnly('workflow/engine/classes/Configurations.php');
        $this->assertSame(
            0,
            PhpSourceScanner::matchCount($projection, self::ENCODE_PATTERN),
            'U-2.3.3 migrated the strftime-coupled encode on Configurations.php:582'
        );
        $this->assertSame(
            0,
            PhpSourceScanner::matchCount($projection, '(?<![\w$>-])LegacyUtf8::encode\s*\(\s*strftime\s*\('),
            'U-2.4.2 removed the wrapped pair too: LegacyLocaleDate returns UTF-8 directly.'
        );
        $this->assertSame(
            2,
            PhpSourceScanner::matchCount($projection, '(?<![\w$>-])LegacyLocaleDate::format\s*\('),
            'Both Configurations.php branches now use the locale-aware helper.'
        );
    }

    public function testLedgerFindsNoRemainingExecutableCallSite(): void
    {
        $budget = json_decode(
            (string) file_get_contents(PM_TEST_ROOT . '/tests/fixtures/deprecation-budget.json'),
            true,
            512,
            JSON_THROW_ON_ERROR
        );

        $hits = CompatibilityLedger::locate(
            $budget['scope'],
            $budget['patterns']['utf8_encode_decode'],
            20
        );

        $this->assertCount(0, $hits, 'Remaining executable UTF-8 hits: ' . implode(', ', $hits));
        $this->assertSame(4, $budget['budgets']['utf8_encode_decode']);
        $this->assertSame(0, $budget['codeBudgets']['utf8_encode_decode']);
    }

    public function testHelperDecodeMatchesTheNativeDecoderForEverySingleByte(): void
    {
        for ($byte = 0; $byte <= 255; ++$byte) {
            $input = chr($byte);
            $this->assertSame(
                LegacyUtf8Oracle::decode($input),
                LegacyUtf8::decode($input),
                'Byte 0x' . strtoupper(dechex($byte)) . ' must decode identically'
            );
        }
    }

    public function testHelperDecodeMatchesTheNativeDecoderForEveryTwoByteSequence(): void
    {
        $divergent = [];
        for ($lead = 0xC0; $lead <= 0xDF; ++$lead) {
            for ($trail = 0x00; $trail <= 0xFF; ++$trail) {
                $input = chr($lead) . chr($trail);
                if (LegacyUtf8Oracle::decode($input) !== LegacyUtf8::decode($input)) {
                    $divergent[] = sprintf('%02X%02X', $lead, $trail);
                }
            }
        }

        $this->assertSame([], $divergent, 'Divergent two-byte sequences: ' . implode(', ', $divergent));
    }

    /**
     * @return array<string, array{string}>
     */
    public static function realisticPayloadProvider(): array
    {
        return [
            'ascii sender name' => ['ProcessMaker Notifications'],
            'latin1 sender name' => ["Jos\xC3\xA9 Mu\xC3\xB1oz"],
            'german sender name' => ["Gr\xC3\xBC\xC3\x9Fe vom B\xC3\xBCro"],
            'translation label' => ["D\xC3\xADas"],
            'multi byte cjk label' => ["\xE6\x97\xA5\xE6\x9C\xAC\xE8\xAA\x9E"],
            'four byte emoji label' => ["ok \xF0\x9F\x91\x8D"],
            'truncated two byte tail' => ["caf\xC3"],
            'stray continuation byte' => ["a\xA9b"],
            'overlong encoding' => ["\xC0\xAF"],
            'surrogate encoding' => ["\xED\xA0\x80"],
            'already latin1 bytes' => ["caf\xE9 cr\xE8me"],
            'empty string' => [''],
            'null bytes' => ["a\x00b"],
            'crlf and tabs' => ["line1\r\n\tline2"],
        ];
    }

    /**
     * @dataProvider realisticPayloadProvider
     */
    public function testHelperDecodeMatchesTheNativeDecoderForRealisticPayloads(string $payload): void
    {
        $this->assertSame(LegacyUtf8Oracle::decode($payload), LegacyUtf8::decode($payload));
    }

    public function testRoundTripOfEveryLatin1ByteIsPreserved(): void
    {
        for ($byte = 0; $byte <= 255; ++$byte) {
            $input = chr($byte);
            $this->assertSame(
                LegacyUtf8Oracle::decode(LegacyUtf8Oracle::encode($input)),
                LegacyUtf8::decode(LegacyUtf8::encode($input)),
                'Round trip must match the native round trip for byte ' . $byte
            );
            $this->assertSame($input, LegacyUtf8::decode(LegacyUtf8::encode($input)));
        }
    }

    public function testHelperDecodeMatchesTheNativeDecoderForTheFullByteString(): void
    {
        $allBytes = implode('', array_map('chr', range(0, 255)));
        $this->assertSame(LegacyUtf8Oracle::decode($allBytes), LegacyUtf8::decode($allBytes));
        $this->assertSame(LegacyUtf8Oracle::decode(LegacyUtf8Oracle::encode($allBytes)), LegacyUtf8::decode(LegacyUtf8::encode($allBytes)));
    }
}
