<?php

declare(strict_types=1);

namespace Tests\Unit\Architecture;

use PHPUnit\Framework\TestCase;
use Tests\Support\PhpSourceScanner;

final class PublicApiCompatibilityTest extends TestCase
{
    /**
     * @dataProvider legacyClassProvider
     *
     * @param list<string> $expectedMethods
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('legacyClassProvider')]
    public function testLegacyPublicMethodsCannotDisappear(
        string $className,
        string $relativeFile,
        array $expectedMethods
    ): void {
        self::assertNotSame([], $expectedMethods, 'The API fixture must not be empty for ' . $className);

        $actualMethods = PhpSourceScanner::declaredPublicMethods(
            PM_TEST_ROOT . '/' . $relativeFile,
            $className
        );
        $missing = array_values(array_diff($expectedMethods, $actualMethods));

        self::assertSame(
            [],
            $missing,
            $className . ' lost backward-compatible public methods ('
                . count($actualMethods) . ' of ' . count($expectedMethods)
                . ' pinned methods were found): ' . implode(', ', $missing)
        );
    }

    /** @return iterable<string, array{0:string,1:string,2:list<string>}> */
    public static function legacyClassProvider(): iterable
    {
        $fixture = self::fixture();
        foreach ($fixture['classes'] as $className => $definition) {
            yield $className => [$className, $definition['file'], $definition['publicMethods']];
        }
    }

    public function testLegacyTriggerGlobalFunctionsCannotDisappear(): void
    {
        $fixture = self::fixture()['functions']['class.pmFunctions.php'];
        $actual = PhpSourceScanner::declaredGlobalFunctions(PM_TEST_ROOT . '/' . $fixture['file']);
        $expected = array_merge($fixture['triggerApi'], $fixture['otherGlobals']);
        $missing = array_values(array_diff($expected, $actual));

        self::assertCount(55, $fixture['triggerApi'], 'The baseline must retain all 55 PMF trigger functions.');
        self::assertSame(
            [],
            $missing,
            'Legacy global trigger API functions disappeared ('
                . count($actual) . ' of ' . count($expected)
                . ' pinned functions were found): ' . implode(', ', $missing)
        );
    }

    /**
     * Guards the scanner itself. PHP tokenizes "{$var}" and "${var}" with dedicated
     * opening tokens that are closed by a plain '}' token, so an interpolation-blind
     * scanner drifts out of scope and reports healthy APIs as removed.
     */
    public function testScannerKeepsScopeAcrossStringInterpolation(): void
    {
        $source = <<<'PHP'
            <?php

            namespace Legacy\Sample;

            class InterpolationSample
            {
                public function first(): string
                {
                    $name = 'engine';
                    $rows = ['key' => 'value'];

                    return "a {$name} b {$rows['key']} c ${name}";
                }

                public function second(): string
                {
                    return 'kept';
                }
            }

            function legacyGlobalAfterClass(): string
            {
                $unit = 'u1';

                return "tail {$unit}";
            }

            function legacyGlobalLast(): string
            {
                return 'kept';
            }
            PHP;

        $file = tempnam(sys_get_temp_dir(), 'u1scan');
        self::assertIsString($file);
        self::assertNotFalse(file_put_contents($file, $source . PHP_EOL));

        try {
            self::assertSame(
                ['first', 'second'],
                PhpSourceScanner::declaredPublicMethods($file, 'Legacy\Sample\InterpolationSample'),
                'String interpolation must not close a class scope early.'
            );
            self::assertSame(
                ['legacyGlobalAfterClass', 'legacyGlobalLast'],
                PhpSourceScanner::declaredGlobalFunctions($file),
                'String interpolation must not shift the global function scope.'
            );
        } finally {
            unlink($file);
        }
    }

    /** @return array<string, mixed> */
    private static function fixture(): array
    {
        $contents = file_get_contents(PM_TEST_ROOT . '/tests/fixtures/public-api-surface.json');
        self::assertNotFalse($contents);

        return json_decode($contents, true, 512, JSON_THROW_ON_ERROR);
    }
}
