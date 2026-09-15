<?php

declare(strict_types=1);

namespace Tests\Unit\Compatibility;

use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;
use SplFileInfo;
use Tests\Support\CompatibilityLedger;

final class DeprecatedApiBudgetTest extends TestCase
{
    public function testLegacyPhpApiTextualOccurrencesCanOnlyDecrease(): void
    {
        $fixture = self::fixture();
        $files = self::phpFiles($fixture['scope']);
        self::assertGreaterThan(1000, count($files));
        $actual = array_fill_keys(array_keys($fixture['patterns']), 0);
        foreach ($files as $file) {
            $source = file_get_contents($file);
            if ($source === false) {
                throw new RuntimeException('Unable to read ' . $file);
            }
            foreach ($fixture['patterns'] as $name => $pattern) {
                $matches = preg_match_all('~' . str_replace('~', '\\~', $pattern) . '~i', $source);
                if ($matches === false) {
                    throw new RuntimeException('Invalid compatibility pattern: ' . $name);
                }
                $actual[$name] += $matches;
            }
        }
        foreach ($fixture['budgets'] as $name => $maximum) {
            self::assertLessThanOrEqual($maximum, $actual[$name], sprintf('%s increased from %d to %d.', $name, $maximum, $actual[$name]));
        }
    }

    public function testLegacyPhpApiExecutableOccurrencesCanOnlyDecrease(): void
    {
        $fixture = self::fixture();
        $actual = CompatibilityLedger::counts($fixture['scope'], $fixture['patterns']);
        foreach ($fixture['codeBudgets'] as $name => $maximum) {
            self::assertLessThanOrEqual(
                $maximum,
                $actual[$name]['code'],
                sprintf('%s executable count increased from %d to %d: %s', $name, $maximum, $actual[$name]['code'], implode(', ', CompatibilityLedger::locate($fixture['scope'], $fixture['patterns'][$name], 5)))
            );
        }
    }

    public function testEveryTextualBudgetHasAnExecutableCodeBudget(): void
    {
        $fixture = self::fixture();
        self::assertSame(array_keys($fixture['budgets']), array_keys($fixture['codeBudgets']));
    }

    private static function fixture(): array
    {
        $contents = file_get_contents(PM_TEST_ROOT . '/tests/fixtures/deprecation-budget.json');
        self::assertNotFalse($contents);
        return json_decode($contents, true, 512, JSON_THROW_ON_ERROR);
    }

    private static function phpFiles(array $scopes): array
    {
        $files = [];
        foreach ($scopes as $scope) {
            $directory = PM_TEST_ROOT . '/' . $scope;
            self::assertDirectoryExists($directory);
            $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($directory, RecursiveDirectoryIterator::SKIP_DOTS));
            foreach ($iterator as $file) {
                if ($file instanceof SplFileInfo && $file->isFile() && strtolower($file->getExtension()) === 'php') {
                    $files[] = $file->getPathname();
                }
            }
        }
        sort($files, SORT_STRING);
        return $files;
    }
}
