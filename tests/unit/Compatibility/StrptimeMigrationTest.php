<?php

declare(strict_types=1);

namespace Tests\Unit\Compatibility;

use PHPUnit\Framework\TestCase;
use Tests\Support\CompatibilityLedger;
use Tests\Support\PhpSourceScanner;

/**
 * U-2.5 guard: class.xmlform.php carried the remaining two executable
 * strptime-name hits: one call site and one function_exists-guarded fallback.
 * PHP 8.1 deprecates the native name and PHP 8.2 removes it, while the
 * Windows acceptance runtime already used the fallback. The behaviour-preserving
 * edit is therefore to keep the fallback body in place under a project-owned
 * name and update the single call site.
 */
final class StrptimeMigrationTest extends TestCase
{
    private const TARGET = 'gulliver/system/class.xmlform.php';
    private const STRPTIME = '(?<![\\w$>-])strptime\\s*\\(';
    private const HELPER = '(?<![\\w$>-])pmStrptimeCompat\\s*\\(';

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

    public function testNoExecutableStrptimeNameRemainsInTheCompatibilityScope(): void
    {
        $fixture = self::fixture();
        $pattern = $fixture['ratchet']['strptime']['pattern'];
        $counts = CompatibilityLedger::counts($fixture['scope'], ['strptime' => $pattern]);

        self::assertSame(0, $counts['strptime']['code'], 'No executable PHP may call or declare the deprecated strptime name.');
        self::assertSame(0, $fixture['ratchet']['strptime']['max'], 'U-2.5 lowers the executable strptime ratchet to zero.');
        self::assertArrayHasKey('_noteU25', $fixture, 'The fixture must record why the ratchet moved.');
    }

    public function testXmlFormUsesTheProjectOwnedFallbackNameExactlyTwice(): void
    {
        $code = self::code();

        self::assertSame(0, PhpSourceScanner::matchCount($code, self::STRPTIME), 'class.xmlform.php must not contain executable strptime-name hits.');
        self::assertSame(2, PhpSourceScanner::matchCount($code, self::HELPER), 'One call plus one fallback declaration must use pmStrptimeCompat().');
        self::assertStringContainsString('$ugly = pmStrptimeCompat($schedule, $schedule_format);', self::raw());
        self::assertStringContainsString("if (!function_exists('pmStrptimeCompat'))", self::raw());
        self::assertStringContainsString('function pmStrptimeCompat($date, $format)', self::raw());
    }

    public function testFallbackBodyKeepsTheAcceptedFieldMapping(): void
    {
        $raw = self::raw();

        foreach ([
            "'%d' => '(?P<d>[0-9]{2})'",
            "'%m' => '(?P<m>[0-9]{2})'",
            "'%Y' => '(?P<Y>[0-9]{4})'",
            "'%H' => '(?P<H>[0-9]{2})'",
            "'%M' => '(?P<M>[0-9]{2})'",
            "'%S' => '(?P<S>[0-9]{2})'",
            '"tm_sec" => $out[\'S\']',
            '"tm_min" => $out[\'M\']',
            '"tm_hour" => $out[\'H\']',
            '"tm_mday" => $out[\'d\']',
            '"tm_mon" => $out[\'m\'] ? $out[\'m\'] - 1 : 0',
            '"tm_year" => $out[\'Y\'] > 1900 ? $out[\'Y\'] - 1900 : 0',
        ] as $snippet) {
            self::assertStringContainsString($snippet, $raw, 'The local fallback mapping must stay unchanged: ' . $snippet);
        }
    }

    public function testDateCreateFromFormatStillBuildsTheSameScheduleFormat(): void
    {
        $raw = self::raw();

        self::assertStringContainsString(
            "str_replace(array('Y', 'y', 'm', 'B', 'b', 'd', 'e', 'H', 'I', 'k', 'l', 'M', 'S'), array('%Y', '%y', '%m', '%B', '%b', '%d', '%e', '%H', '%I', '%k', '%l', '%M', '%S'), \$dformat)",
            $raw,
            'The caller must still translate the same date mask characters before parsing.'
        );
        self::assertStringContainsString("return \$new_schedule->format('Y-m-d' . (\$withHours ? ' H:i:s' : ''));", $raw);
    }
}
