<?php

declare(strict_types=1);

namespace Tests\Unit\Compatibility;

use PHPUnit\Framework\TestCase;
use ProcessMaker\Util\LegacyUtf8;
use RuntimeException;
use Tests\Support\PhpSourceScanner;

require_once PM_TEST_ROOT . '/workflow/engine/src/ProcessMaker/Util/LegacyUtf8.php';

/**
 * U-2.2.2 guard: the nine executable utf8_encode call sites now delegate to
 * ProcessMaker\Util\LegacyUtf8::encode(). Every assertion here is byte-for-byte:
 * the helper must return exactly what the native function returns, and each
 * migrated file must contain the helper call and no executable native call.
 */
final class LegacyUtf8CallSiteMigrationTest extends TestCase
{
    private const ENCODE_PATTERN = '(?<![\w$>-])utf8_encode\s*\(';
    private const DECODE_PATTERN = '(?<![\w$>-])utf8_decode\s*\(';

    /**
     * The exact scope approved for U-2.2.2: relative file => migrated encode
     * call sites in that file.
     *
     * @return array<string, int>
     */
    private static function migratedFiles(): array
    {
        return [
            'gulliver/system/class.g.php' => 1,
            'gulliver/system/class.inputfilter.php' => 2,
            'workflow/engine/classes/SpoolRun.php' => 2,
            'workflow/engine/controllers/pmTablesProxy.php' => 1,
            'workflow/engine/methods/users/usersAjax.php' => 1,
            'workflow/engine/src/ProcessMaker/EmailOAuth/EmailBase.php' => 2,
        ];
    }

    private static function read(string $relativeFile): string
    {
        $file = PM_TEST_ROOT . '/' . $relativeFile;
        self::assertFileExists($file);
        $source = file_get_contents($file);
        if ($source === false) {
            throw new RuntimeException('Unable to read ' . $relativeFile);
        }

        return $source;
    }

    public function testEveryMigratedFileStillParsesUnderPhpEight(): void
    {
        foreach (array_keys(self::migratedFiles()) as $relativeFile) {
            $source = self::read($relativeFile);
            token_get_all($source, TOKEN_PARSE);
            self::assertStringContainsString('LegacyUtf8', $source, $relativeFile . ' lost its helper reference.');
        }
    }

    public function testMigratedFilesContainNoExecutableNativeEncodeCall(): void
    {
        foreach (array_keys(self::migratedFiles()) as $relativeFile) {
            $projection = PhpSourceScanner::codeOnlySource(self::read($relativeFile));
            self::assertSame(
                0,
                PhpSourceScanner::matchCount($projection, self::ENCODE_PATTERN),
                $relativeFile . ' still executes native utf8_encode().'
            );
        }
    }

    public function testEachMigratedFileCallsTheHelperExactlyAsOftenAsApproved(): void
    {
        foreach (self::migratedFiles() as $relativeFile => $expectedCalls) {
            $projection = PhpSourceScanner::codeOnlySource(self::read($relativeFile));
            self::assertSame(
                $expectedCalls,
                PhpSourceScanner::matchCount($projection, '(?<![\w$>-])LegacyUtf8::encode\s*\('),
                $relativeFile . ' does not call the helper the approved number of times.'
            );
        }
    }

    public function testDeferredCallSitesAreUntouched(): void
    {
        $configurations = PhpSourceScanner::codeOnlySource(self::read('workflow/engine/classes/Configurations.php'));
        self::assertSame(0, PhpSourceScanner::matchCount($configurations, self::ENCODE_PATTERN), 'Configurations.php:582 was migrated by U-2.3.3 and must not be native any more.');

        // U-2.2.3 migrated both decode call sites, so the native call must now be
        // gone and the helper call must be present. Ownership of these two sites
        // moved from "deferred" to "migrated" with the accepted U-2.2.3 unit.
        $spoolRun = PhpSourceScanner::codeOnlySource(self::read('workflow/engine/classes/SpoolRun.php'));
        self::assertSame(0, PhpSourceScanner::matchCount($spoolRun, self::DECODE_PATTERN), 'The SpoolRun decode call was migrated by U-2.2.3.');
        self::assertSame(1, PhpSourceScanner::matchCount($spoolRun, '(?<![\w$>-])LegacyUtf8::decode\s*\('), 'The SpoolRun decode call must use the helper after U-2.2.3.');

        $graph = PhpSourceScanner::codeOnlySource(self::read('workflow/engine/methods/events/eventsSetupGraph.php'));
        self::assertSame(0, PhpSourceScanner::matchCount($graph, self::DECODE_PATTERN), 'The GD decode call was migrated by U-2.2.3.');
        self::assertSame(1, PhpSourceScanner::matchCount($graph, '(?<![\w$>-])LegacyUtf8::decode\s*\('), 'The GD decode call must use the helper after U-2.2.3.');

        // U-6 migrated the PMScript generated catch block; the hit never was in the
        // code-only projection because it lives inside a double-quoted string.
        $pmScriptRaw = self::read('workflow/engine/classes/class.pmScript.php');
        $pmScript = PhpSourceScanner::codeOnlySource($pmScriptRaw);
        self::assertSame(0, PhpSourceScanner::matchCount($pmScript, self::ENCODE_PATTERN), 'No executable native encode may remain in PMScript.');
        self::assertStringNotContainsString('utf8_encode(\\$oException->getMessage())', $pmScriptRaw, 'U-6 replaced the native encode inside the generated string.');
        self::assertStringContainsString('\\\\ProcessMaker\\\\Util\\\\LegacyUtf8::encode(\\$oException->getMessage())', $pmScriptRaw, 'The generated catch block must call the helper.');
    }

    public function testHelperEncodeIsByteForByteIdenticalToNativeForEverySingleByte(): void
    {
        foreach (range(0, 255) as $byte) {
            $input = chr($byte);
            self::assertSame(
                utf8_encode($input),
                LegacyUtf8::encode($input),
                sprintf('Byte 0x%02x diverges from the native encoder.', $byte)
            );
        }
    }

    public function testHelperEncodeIsByteForByteIdenticalForRealisticCallSitePayloads(): void
    {
        $payloads = [
            'empty' => '',
            'ascii-subject' => 'Task assigned: invoice #4711',
            'latin1-sender' => "Jos\xE9 Mu\xF1oz",
            'latin1-accents' => "\xC0\xC9\xCE\xD5\xDC\xE4\xF6\xFC\xFF",
            'html-entity-char' => chr(233),
            'hex-entity-char' => chr((int) '0x41'),
            'already-utf8-double-encoded' => "\xC3\xA9\xC3\xA8",
            'binary-body' => "line1\r\nline2\x00\x1F\x7F\x80\xFE\xFF",
            'username' => "m\xFCller.admin",
            'all-bytes' => implode('', array_map('chr', range(0, 255))),
        ];

        foreach ($payloads as $name => $payload) {
            self::assertSame(utf8_encode($payload), LegacyUtf8::encode($payload), 'Payload diverges: ' . $name);
        }
    }

    public function testEntityDecodeCallbacksProduceIdenticalBytesAfterMigration(): void
    {
        // Mirrors gulliver/system/class.inputfilter.php decimal and hex callbacks.
        foreach (range(0, 255) as $byte) {
            self::assertSame(utf8_encode(chr($byte)), LegacyUtf8::encode(chr($byte)));
            $hex = sprintf('%x', $byte);
            self::assertSame(
                utf8_encode(chr((int) ('0x' . $hex))),
                LegacyUtf8::encode(chr((int) ('0x' . $hex))),
                'Hex notation callback diverges for 0x' . $hex
            );
        }
    }

    public function testTranslationTableMigrationKeepsIdenticalBytes(): void
    {
        // Mirrors gulliver/system/class.g.php is_utf8/html-entity table build.
        $table = get_html_translation_table(HTML_ENTITIES, ENT_COMPAT, 'ISO-8859-1');
        self::assertNotSame([], $table);

        $native = [];
        $migrated = [];
        foreach ($table as $character => $entity) {
            $native[$entity] = utf8_encode($character);
            $migrated[$entity] = LegacyUtf8::encode($character);
        }

        self::assertSame($native, $migrated);
    }

    public function testHelperDoesNotDependOnMbstringSubstituteState(): void
    {
        $before = mb_substitute_character();
        mb_substitute_character(0x3F);
        $withQuestionMark = LegacyUtf8::encode("\x80\xFF");
        mb_substitute_character('none');
        $withNone = LegacyUtf8::encode("\x80\xFF");
        mb_substitute_character($before);

        self::assertSame("\xC2\x80\xC3\xBF", $withQuestionMark);
        self::assertSame($withQuestionMark, $withNone);
    }
}
