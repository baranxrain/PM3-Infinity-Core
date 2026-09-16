<?php

declare(strict_types=1);

namespace Tests\Unit\Compatibility;

use PHPUnit\Framework\TestCase;
use Tests\Support\CompatibilityLedger;
use Tests\Support\PhpSourceScanner;

require_once PM_TEST_ROOT . '/gulliver/system/class.inputfilter.php';

/**
 * U-2.7 guard: InputFilter no longer uses PHP 8.1 deprecated string-sanitizer
 * constants, but preserves the legacy behaviour used by its call sites.
 */
final class FilterSanitizeStringMigrationTest extends TestCase
{
    private const TARGET = 'gulliver/system/class.inputfilter.php';
    private const LEGACY_FILTER = 'FILTER_SANITIZE_STRING|FILTER_FLAG_STRIP_(?:LOW|HIGH)';

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

    private static function expectedLegacySanitizeString($value, int $flags = 0): string
    {
        $value = strip_tags((string) $value);
        $value = str_replace(['"', "'"], ['&#34;', '&#39;'], $value);
        if (($flags & FILTER_FLAG_STRIP_LOW) !== 0) {
            $value = (string) preg_replace('/[\x00-\x1F]/', '', $value);
        }
        if (($flags & FILTER_FLAG_STRIP_HIGH) !== 0) {
            $value = (string) preg_replace('/[\x7F-\xFF]/', '', $value);
        }
        return $value;
    }

    public function testNoExecutableLegacyStringSanitizerConstantRemainsInScope(): void
    {
        $fixture = self::fixture();
        $pattern = $fixture['ratchet']['filter_sanitize_string']['pattern'];
        $counts = CompatibilityLedger::counts($fixture['scope'], ['filter_sanitize_string' => $pattern]);

        self::assertSame(0, $counts['filter_sanitize_string']['code'], 'No executable legacy string-sanitizer constant may remain under PHP 8.');
        self::assertSame(0, $fixture['ratchet']['filter_sanitize_string']['max'], 'U-2.7 lowers the executable ratchet to zero.');
        self::assertArrayHasKey('_noteU27', $fixture, 'The fixture must record why the ratchet moved.');
    }

    public function testInputFilterCallSitesUseTheLocalCompatibilityHelper(): void
    {
        $code = self::code();
        $raw = self::raw();

        self::assertSame(0, PhpSourceScanner::matchCount($code, self::LEGACY_FILTER), 'class.inputfilter.php must not contain executable legacy constants.');
        self::assertSame(1, PhpSourceScanner::matchCount($code, 'function\s+sanitizeLegacyString\s*\('), 'The local helper must be declared exactly once.');
        self::assertSame(6, PhpSourceScanner::matchCount($code, '\$this->sanitizeLegacyString\s*\('), 'The six migrated call sites must use the local helper.');
        self::assertStringContainsString('$this->sanitizeLegacyString($value, true, true)', $raw, 'nosql keeps low/high stripping.');
        self::assertStringContainsString('$this->sanitizeLegacyString($value, true)', $raw, 'default sanitize keeps low-byte stripping.');
    }

    public function testXssFilterKeepsTheLegacySanitizedOutput(): void
    {
        $filter = new \InputFilter();
        $sample = '<b>Tom "O\'Neil"</b><script>alert(1)</script>';

        self::assertSame(
            addslashes(htmlspecialchars(self::expectedLegacySanitizeString($sample), ENT_COMPAT, 'UTF-8')),
            $filter->xssFilter($sample),
            'Default xssFilter output must match the legacy sanitizer plus existing escaping.'
        );
        self::assertSame(
            self::expectedLegacySanitizeString($sample),
            $filter->xssFilter($sample, 'url'),
            'URL mode must return only the legacy sanitized string.'
        );
    }

    public function testValidateInputSanitizeModesMatchTheLegacyFlags(): void
    {
        $filter = new \InputFilter();
        $sample = "abc\x01<em>TAG</em>ñ\x7F\xC3\xB1 safe";

        self::assertSame(
            self::expectedLegacySanitizeString($sample, FILTER_FLAG_STRIP_LOW),
            $filter->sanitizeInputValue($sample, 'string'),
            'Default string sanitizing keeps the low-byte stripping behaviour.'
        );
        self::assertSame(
            self::expectedLegacySanitizeString($sample, FILTER_FLAG_STRIP_LOW | FILTER_FLAG_STRIP_HIGH),
            $filter->sanitizeInputValue($sample, 'nosql'),
            'nosql sanitizing keeps both low-byte and high-byte stripping before keyword truncation.'
        );
        self::assertStringNotContainsString("\x7F", $filter->sanitizeInputValue($sample, 'nosql'), 'FILTER_FLAG_STRIP_HIGH also removes DEL (0x7F) on the acceptance runtime.');
    }
}
