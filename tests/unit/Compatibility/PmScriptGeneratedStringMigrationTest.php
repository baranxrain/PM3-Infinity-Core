<?php

namespace Tests\Unit\Compatibility;

use PHPUnit\Framework\TestCase;
use Tests\Support\LegacyUtf8Oracle;
use ProcessMaker\Util\LegacyUtf8;
use Tests\Support\CompatibilityLedger;
use Tests\Support\PhpSourceScanner;

require_once PM_TEST_ROOT . '/workflow/engine/src/ProcessMaker/Util/LegacyUtf8.php';

/**
 * U-6: workflow/engine/classes/class.pmScript.php:479 builds a catch block into
 * the trigger script it later runs through eval(). That generated block used to
 * call the native LegacyUtf8Oracle::encode(); it now emits
 * \ProcessMaker\Util\LegacyUtf8::encode() instead.
 *
 * The hit was never visible in the code-only projection, because it lives inside
 * a double-quoted string, yet it really did execute at runtime. The textual
 * ratchet therefore drops from 5 to 4 while the executable ratchet stays 0.
 *
 * The emitted name is fully qualified on purpose: eval() inherits the calling
 * scope, and composer.json maps psr-0 "ProcessMaker\\" to workflow/engine/src,
 * which is exactly where the helper lives.
 */
class PmScriptGeneratedStringMigrationTest extends TestCase
{
    private const TARGET = 'workflow/engine/classes/class.pmScript.php';
    private const NATIVE_ENCODE = '(?<![\w$>-])utf8_encode\s*\(';
    private const NATIVE_UTF8 = '(?<![\w$>-])(?:utf8_encode|utf8_decode)\s*\(';
    private const HELPER_ENCODE = '(?<![\w$>-])LegacyUtf8::encode\s*\(';

    private const NATIVE_SNIPPET = 'LegacyUtf8Oracle::encode(\$oException->getMessage())';
    private const MIGRATED_SNIPPET = '\\\\ProcessMaker\\\\Util\\\\LegacyUtf8::encode(\$oException->getMessage())';

    private static function target(): string
    {
        return PhpSourceScanner::read(PM_TEST_ROOT . '/' . self::TARGET);
    }

    private static function targetCode(): string
    {
        return PhpSourceScanner::codeOnlySource(self::target());
    }

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

    public function testTheGeneratedCatchBlockNowEmitsTheHelper(): void
    {
        $raw = self::target();

        self::assertStringNotContainsString(self::NATIVE_SNIPPET, $raw, 'The generated catch block must not emit native LegacyUtf8Oracle::encode().');
        self::assertStringContainsString(self::MIGRATED_SNIPPET, $raw, 'The generated catch block must emit the fully qualified helper call.');
        self::assertSame(0, PhpSourceScanner::matchCount($raw, self::NATIVE_ENCODE), 'No native LegacyUtf8Oracle::encode() may remain anywhere in PMScript, not even inside a string.');
        self::assertSame(1, PhpSourceScanner::matchCount($raw, self::HELPER_ENCODE), 'Exactly one helper encode call must be emitted.');
    }

    public function testTheEmittedNameIsFullyQualifiedAndAutoloadable(): void
    {
        $composer = json_decode((string) file_get_contents(PM_TEST_ROOT . '/composer.json'), true, 512, JSON_THROW_ON_ERROR);

        self::assertSame('workflow/engine/src', $composer['autoload']['psr-0']['ProcessMaker\\'], 'The psr-0 mapping the generated code relies on must be intact.');
        self::assertFileExists(PM_TEST_ROOT . '/workflow/engine/src/ProcessMaker/Util/LegacyUtf8.php', 'The helper must sit where that mapping resolves it.');
        self::assertTrue(class_exists(LegacyUtf8::class), 'The helper class must be loadable.');
    }

    public function testTheSurroundingTriggerMachineryIsUntouched(): void
    {
        $code = self::targetCode();

        self::assertSame(0, PhpSourceScanner::matchCount($code, '(?<![\w$>-])eval\s*\('), 'U-3.17 closes the final trigger-body eval sites in PMScript.');
        self::assertSame(1, PhpSourceScanner::matchCount($code, 'function\s+executeAndCatchErrors\s*\('), 'executeAndCatchErrors() must still exist exactly once.');
        self::assertSame(0, PhpSourceScanner::matchCount($code, '(?<![\w$>-])set_error_handler\s*\('), 'U-2.8 removed the direct set_error_handler() ledger hit.');
        self::assertSame(1, PhpSourceScanner::matchCount($code, '\$this->installTriggerErrorHandler\s*\('), 'The trigger error handler is still installed through the U-2.8 helper.');

        // The literals below are blanked in the code-only projection, so they are
        // checked against the raw source.
        $raw = self::target();
        self::assertSame(2, PhpSourceScanner::matchCount($raw, '__ERROR__'), 'Both __ERROR__ usages must be untouched.');
        self::assertStringContainsString('catch (Exception \$oException)', $raw, 'The generated catch signature must be untouched.');
    }

    public function testTheGeneratedBlockStillProducesIdenticalBytes(): void
    {
        $message = "Error en la l\xEDnea 3: variable no v\xE1lida";
        $exception = new \Exception($message);

        self::assertSame(LegacyUtf8Oracle::encode($exception->getMessage()), LegacyUtf8::encode($exception->getMessage()), 'The migrated call must produce the native bytes.');

        foreach (range(0, 255) as $byte) {
            $input = 'trigger: ' . chr($byte);
            self::assertSame(LegacyUtf8Oracle::encode($input), LegacyUtf8::encode($input), 'Byte ' . $byte . ' must round-trip identically.');
        }
    }

    public function testTheUtf8RatchetDroppedToFourTextualAndZeroExecutable(): void
    {
        $budget = self::budget();
        $counts = CompatibilityLedger::counts($budget['scope'], ['utf8' => self::NATIVE_UTF8]);

        self::assertSame(4, $counts['utf8']['textual'], 'Textual UTF-8 hits must drop from 5 to 4.');
        self::assertSame(0, $counts['utf8']['code'], 'The executable ratchet must stay at zero.');
        self::assertSame(4, $budget['budgets']['utf8_encode_decode'], 'The textual ratchet must be lowered with the migration.');
        self::assertSame(0, $budget['codeBudgets']['utf8_encode_decode'], 'The executable budget must stay at zero.');
        self::assertArrayHasKey('_noteU6', $budget, 'The unit must record why the ratchet moved.');
    }

    public function testOnlyCommentHitsRemain(): void
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
            'After U-6 the only remaining textual hits are comments.'
        );
        self::assertArrayNotHasKey(self::TARGET, $perFile, 'PMScript must no longer carry a textual hit.');
    }

    public function testTheStrftimeRatchetIsUnaffected(): void
    {
        $budget = self::budget();

        self::assertSame(140, $budget['budgets']['strftime'], 'The textual strftime ratchet remains at the post-U-2.4.2 value.');
        self::assertSame(0, $budget['codeBudgets']['strftime'], 'U-2.4.2 lowered the strftime executable ratchet to zero.');
    }
}
