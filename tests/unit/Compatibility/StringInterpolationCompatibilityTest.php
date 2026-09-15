<?php

declare(strict_types=1);

namespace Tests\Unit\Compatibility;

use PHPUnit\Framework\TestCase;
use Tests\Support\CompatibilityLedger;
use Tests\Support\PhpSourceScanner;

final class StringInterpolationCompatibilityTest extends TestCase
{
    public function testDeprecatedDollarBraceStringInterpolationIsDetected(): void
    {
        $source = <<<'PHP'
        <?php
        $legacy = "Hello ${name}";
        PHP;
        self::assertSame(1, PhpSourceScanner::countDollarBraceInterpolations($source));
    }

    public function testModernCurlyStringInterpolationIsNotMisclassified(): void
    {
        $source = <<<'PHP'
        <?php
        $modern = "Hello {$name}";
        $variableVariable = ${$field};
        PHP;
        self::assertSame(0, PhpSourceScanner::countDollarBraceInterpolations($source));
    }

    public function testEngineScopeStaysAtZeroDeprecatedInterpolations(): void
    {
        $fixture = self::fixture();
        $actual = CompatibilityLedger::dollarBraceInterpolations($fixture['scope']);
        self::assertSame((int) $fixture['stringInterpolation']['dollarBraceMax'], $actual['count'], 'Deprecated interpolation returned in: ' . implode(', ', $actual['files']));
    }

    private static function fixture(): array
    {
        $contents = file_get_contents(PM_TEST_ROOT . '/tests/fixtures/php8-compatibility.json');
        self::assertNotFalse($contents);
        return json_decode($contents, true, 512, JSON_THROW_ON_ERROR);
    }
}
