<?php

declare(strict_types=1);

namespace Tests\Unit\Compatibility;

use PHPUnit\Framework\TestCase;
use Tests\Support\CompatibilityLedger;
use Tests\Support\PhpSourceScanner;

final class PhpEightRemovedConstructsTest extends TestCase
{
    public function testConstructsRemovedByPhpEightAreAbsentFromEngineCode(): void
    {
        $fixture = self::fixture();
        $patterns = self::zeroLockedPatterns($fixture);
        self::assertGreaterThan(1000, count(CompatibilityLedger::phpFiles($fixture['scope'])));
        $counts = CompatibilityLedger::counts($fixture['scope'], $patterns);
        foreach ($patterns as $family => $pattern) {
            self::assertSame(
                0,
                $counts[$family]['code'],
                sprintf('%s is executable PHP again (removed in PHP %s): %s', $family, (string) $fixture['zeroLocked'][$family]['removedIn'], implode(', ', CompatibilityLedger::locate($fixture['scope'], $pattern, 5)))
            );
        }
    }

    public function testKnownDeprecationDebtCanOnlyShrink(): void
    {
        $fixture = self::fixture();
        $patterns = [];
        foreach ($fixture['ratchet'] as $family => $definition) {
            $patterns[$family] = (string) $definition['pattern'];
        }
        $counts = CompatibilityLedger::counts($fixture['scope'], $patterns);
        foreach ($fixture['ratchet'] as $family => $definition) {
            self::assertLessThanOrEqual(
                (int) $definition['max'],
                $counts[$family]['code'],
                sprintf('%s grew from %d to %d. Owner: %s', $family, (int) $definition['max'], $counts[$family]['code'], (string) $definition['owner'])
            );
        }
    }

    public function testEveryZeroLockDetectsTheConstructItClaimsToBlock(): void
    {
        $legacy = <<<'PHP'
        <?php
        $callback = create_function('$a', 'return $a;');
        while (list($key, $value) = each($array)) {
        }
        if (ereg('[a-z]', $text)) {
        }
        $rows = mysql_query($sql);
        $other = mssql_query($sql);
        $more = sybase_query($sql);
        function __autoload($class)
        {
        }
        $raw = $HTTP_RAW_POST_DATA;
        $message = $php_errormsg;
        $first = $text{0};
        $price = money_format('%i', $value);
        PHP;

        $projection = PhpSourceScanner::codeOnlySource($legacy);
        foreach (self::zeroLockedPatterns(self::fixture()) as $family => $pattern) {
            self::assertGreaterThan(0, PhpSourceScanner::matchCount($projection, $pattern), $family . ' guard has no teeth.');
        }
    }

    private static function zeroLockedPatterns(array $fixture): array
    {
        $patterns = [];
        foreach ($fixture['zeroLocked'] as $family => $definition) {
            $patterns[$family] = (string) $definition['pattern'];
        }
        return $patterns;
    }

    private static function fixture(): array
    {
        $contents = file_get_contents(PM_TEST_ROOT . '/tests/fixtures/php8-compatibility.json');
        self::assertNotFalse($contents);
        return json_decode($contents, true, 512, JSON_THROW_ON_ERROR);
    }
}
