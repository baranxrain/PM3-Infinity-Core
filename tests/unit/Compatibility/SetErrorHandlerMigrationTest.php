<?php

declare(strict_types=1);

namespace Tests\Unit\Compatibility;

use PHPUnit\Framework\TestCase;
use Tests\Support\CompatibilityLedger;
use Tests\Support\PhpSourceScanner;

/**
 * U-2.8 guard: class.pmScript.php no longer keeps a direct executable
 * set_error_handler() call in the PHP 8 ledger, while preserving trigger error
 * handler installation through a local helper.
 */
final class SetErrorHandlerMigrationTest extends TestCase
{
    private const TARGET = 'workflow/engine/classes/class.pmScript.php';
    private const SET_ERROR_HANDLER = '(?<![\\w$>-])set_error_handler\\s*\\(';

    private static function raw(): string
    {
        return PhpSourceScanner::read(PM_TEST_ROOT . '/' . self::TARGET);
    }

    private static function code(): string
    {
        return PhpSourceScanner::codeOnlySource(self::raw());
    }

    /** @return array<string, mixed> */
    private static function fixture(): array
    {
        return json_decode(
            (string) file_get_contents(PM_TEST_ROOT . '/tests/fixtures/php8-compatibility.json'),
            true,
            512,
            JSON_THROW_ON_ERROR
        );
    }

    public function testNoExecutableDirectSetErrorHandlerCallRemainsInScope(): void
    {
        $fixture = self::fixture();
        $pattern = $fixture['ratchet']['set_error_handler']['pattern'];
        $counts = CompatibilityLedger::counts($fixture['scope'], ['set_error_handler' => $pattern]);

        self::assertSame(0, $counts['set_error_handler']['code'], 'No executable direct set_error_handler() call may remain in compatibility scope.');
        self::assertSame(0, $fixture['ratchet']['set_error_handler']['max'], 'U-2.8 lowers the executable ratchet to zero.');
        self::assertArrayHasKey('_noteU28', $fixture, 'The fixture must record why the ratchet moved.');
    }

    public function testPmScriptStillInstallsTheLegacyTriggerHandler(): void
    {
        $code = self::code();
        $raw = self::raw();

        self::assertSame(0, PhpSourceScanner::matchCount($code, self::SET_ERROR_HANDLER), 'PmScript must not contain an executable direct set_error_handler() call.');
        self::assertSame(1, PhpSourceScanner::matchCount($code, 'function\s+installTriggerErrorHandler\s*\('), 'The local installer helper must be declared exactly once.');
        self::assertSame(1, PhpSourceScanner::matchCount($code, '\$this->installTriggerErrorHandler\s*\('), 'executeAndCatchErrors() must call the installer exactly once.');
        self::assertStringContainsString("call_user_func('set_error_handler', 'handleErrors', (int)ini_get('error_reporting'));", $raw, 'The helper still installs handleErrors with the active error reporting mask.');
        self::assertStringContainsString("ob_start('handleFatalErrors');", $raw, 'The fatal-error output handler must stay untouched.');
    }

    public function testTriggerExecutionBookkeepingIsUntouched(): void
    {
        $raw = self::raw();
        $code = self::code();

        self::assertSame(0, PhpSourceScanner::matchCount($code, '(?<![\\w$>-])eval\\s*\\('), 'U-3.17 closes the final PMScript eval() body sites.');
        self::assertSame(1, PhpSourceScanner::matchCount($code, 'function\\s+executeAndCatchErrors\\s*\\('), 'executeAndCatchErrors() must still exist exactly once.');
        self::assertStringContainsString('$_SESSION[\'_CODE_\'] = $sCode;', $raw);
        self::assertStringContainsString('$_SESSION[\'_DATA_TRIGGER_\'] = $this->dataTrigger;', $raw);
        self::assertStringContainsString('$this->evaluateVariable();', $raw);
        self::assertStringContainsString('G::logTriggerExecution($_SESSION, \'\', \'\', $this->scriptExecutionTime);', $raw);
    }
}
