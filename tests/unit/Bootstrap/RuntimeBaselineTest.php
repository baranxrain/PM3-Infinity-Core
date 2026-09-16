<?php

declare(strict_types=1);

namespace Tests\Unit\Bootstrap;

use PHPUnit\Framework\TestCase;

final class RuntimeBaselineTest extends TestCase
{
    public function testTheApprovedRuntimeIsPhp81OrPhp82Cli(): void
    {
        self::assertSame('cli', PHP_SAPI);
        self::assertGreaterThanOrEqual(80100, PHP_VERSION_ID, 'ProcessMaker 3.8.3 compatibility tests require PHP 8.1 or PHP 8.2.');
        self::assertLessThan(80300, PHP_VERSION_ID, 'Use PHP 8.1.x or PHP 8.2.x for the approved compatibility lanes.');
    }

    public function testMinimalTestExtensionsAreAvailable(): void
    {
        foreach (['json', 'tokenizer'] as $extension) {
            self::assertTrue(extension_loaded($extension), 'Missing PHP extension: ' . $extension);
        }
    }

    public function testBootstrapIsDatabaseFree(): void
    {
        self::assertTrue(defined('PM_TEST_DATABASE_BOOTSTRAPPED'));
        self::assertFalse(PM_TEST_DATABASE_BOOTSTRAPPED);
        self::assertSame('testing', getenv('APP_ENV'));
        self::assertFileExists(PM_TEST_ROOT . '/composer.lock');
    }
}
