<?php

declare(strict_types=1);

namespace Tests\Unit\Reproducibility;

use PHPUnit\Framework\TestCase;

final class DependencyLockBaselineTest extends TestCase
{
    public function testComposerLockIdentityAndPackageCountsArePinned(): void
    {
        $baseline = self::json('tests/fixtures/dependency-baseline.json');
        $lock = self::json('composer.lock');
        $composer = self::json('composer.json');

        self::assertSame($baseline['contentHash'], $lock['content-hash']);
        self::assertCount($baseline['counts']['packages'], $lock['packages']);
        self::assertCount($baseline['counts']['packagesDev'], $lock['packages-dev']);
        self::assertSame($baseline['platform'], $lock['platform']);
        self::assertSame($baseline['phpConstraint'], $composer['require']['php']);

        $developmentPackages = self::packagesByName($lock['packages-dev']);
        self::assertSame($baseline['phpunit'], $developmentPackages['phpunit/phpunit']['version']);
    }

    public function testPrivateDependencyReferencesCannotDriftSilently(): void
    {
        $baseline = self::json('tests/fixtures/dependency-baseline.json');
        $lock = self::json('composer.lock');
        $packages = self::packagesByName($lock['packages']);

        foreach ($baseline['privatePackages'] as $name => $expected) {
            self::assertArrayHasKey($name, $packages);
            self::assertSame($expected['version'], $packages[$name]['version'], $name . ' version drifted.');
            self::assertSame($expected['reference'], self::referenceOf($packages[$name]), $name . ' reference drifted.');
        }
    }

    public function testEngineCriticalDependencyPinsCannotDriftSilently(): void
    {
        $baseline = self::json('tests/fixtures/dependency-baseline.json');
        $lock = self::json('composer.lock');
        $packages = self::packagesByName($lock['packages']);

        foreach ($baseline['watchedPins'] as $name => $expected) {
            self::assertArrayHasKey($name, $packages);
            self::assertSame($expected['version'], $packages[$name]['version'], $name . ' version drifted.');
            self::assertSame($expected['reference'], self::referenceOf($packages[$name]), $name . ' reference drifted.');
        }
    }

    /** @return array<string, mixed> */
    private static function json(string $relativeFile): array
    {
        $contents = file_get_contents(PM_TEST_ROOT . '/' . $relativeFile);
        self::assertNotFalse($contents, 'Unable to read ' . $relativeFile);

        return json_decode($contents, true, 512, JSON_THROW_ON_ERROR);
    }

    /**
     * @param list<array<string, mixed>> $packages
     * @return array<string, array<string, mixed>>
     */
    private static function packagesByName(array $packages): array
    {
        $result = [];
        foreach ($packages as $package) {
            $result[$package['name']] = $package;
        }

        return $result;
    }

    /** @param array<string, mixed> $package */
    private static function referenceOf(array $package): ?string
    {
        return $package['source']['reference'] ?? $package['dist']['reference'] ?? null;
    }
}
