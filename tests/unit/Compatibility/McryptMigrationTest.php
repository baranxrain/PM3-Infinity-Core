<?php

declare(strict_types=1);

namespace Tests\Unit\Compatibility;

use PHPUnit\Framework\TestCase;
use Tests\Support\CompatibilityLedger;
use Tests\Support\PhpSourceScanner;

require_once PM_TEST_ROOT . '/workflow/engine/classes/Padl.php';

/**
 * U-2.6 guard: PHP 8 no longer ships ext/mcrypt, so Padl must not keep an
 * executable mcrypt_* branch. The regular cipher path already handled the
 * acceptance runtime; this unit pins that path and its round-trip behaviour.
 */
final class McryptMigrationTest extends TestCase
{
    private const TARGET = 'workflow/engine/classes/Padl.php';
    private const MCRYPT = '(?<![\\w$>-])mcrypt_[a-z_]+\\s*\\(';

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

    public function testNoExecutableMcryptCallRemainsInScope(): void
    {
        $fixture = self::fixture();
        $pattern = $fixture['ratchet']['mcrypt_ext']['pattern'];
        $counts = CompatibilityLedger::counts($fixture['scope'], ['mcrypt_ext' => $pattern]);

        self::assertSame(0, $counts['mcrypt_ext']['code'], 'No executable mcrypt_* call may remain under PHP 8.');
        self::assertSame(0, $fixture['ratchet']['mcrypt_ext']['max'], 'U-2.6 lowers the mcrypt executable ratchet to zero.');
        self::assertArrayHasKey('_noteU26', $fixture, 'The fixture must record why the mcrypt ratchet moved.');
    }

    public function testPadlAlwaysDisablesTheMcryptBranchOnTheTargetRuntime(): void
    {
        $code = self::code();
        $raw = self::raw();

        self::assertSame(0, PhpSourceScanner::matchCount($code, self::MCRYPT), 'Padl.php must not contain executable mcrypt_* calls.');
        self::assertStringContainsString('$this->USE_MCRYPT = false;', $raw, 'init() must force the PHP 8-compatible cipher path.');
        self::assertStringNotContainsString("function_exists('mcrypt_generic')", $raw, 'Padl must not probe the removed extension.');
        self::assertStringContainsString('$query .= \'&MCRYPT=\' . $this->USE_MCRYPT;', $raw, 'The dial-home payload still reports the selected cipher path.');
    }

    public function testRegularCipherRoundTripsForEveryKeyType(): void
    {
        $padl = new \Padl();
        $padl->init(true, true, true, false, true);
        self::assertFalse($padl->USE_MCRYPT, 'The public flag must reflect that the regular cipher is selected.');

        $payload = [
            'ID' => $padl->ID1,
            'DATA' => [
                'TYPE' => 'unit-test',
                'PLAN' => 'php81',
                'UNICODE' => 'Español / português',
            ],
            'NUMBER' => 2425,
        ];

        foreach (['KEY', 'REQUESTKEY', 'HOMEKEY'] as $keyType) {
            $encrypted = $padl->_encrypt($payload, $keyType);
            self::assertIsString($encrypted);
            self::assertNotSame('', $encrypted);
            self::assertSame($payload, $padl->_decrypt($encrypted, $keyType), 'Regular cipher must round-trip for ' . $keyType . '.');
        }
    }

    public function testRegularCipherLoopBodiesStayInPlace(): void
    {
        $raw = self::raw();

        foreach ([
            '$char = chr(ord($char) + ord($keychar));',
            '$char = chr(ord($char) - ord($keychar));',
            '$keychar = substr($key, ($i % strlen($key)) - 1, 1);',
            'return $rand_add_on . base64_encode(base64_encode(trim($crypt)));',
            'return unserialize($decrypt);',
        ] as $snippet) {
            self::assertStringContainsString($snippet, $raw, 'The existing regular cipher path must stay unchanged: ' . $snippet);
        }
    }
}
