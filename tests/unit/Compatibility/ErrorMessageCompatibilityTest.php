<?php

declare(strict_types=1);

namespace Tests\Unit\Compatibility;

use PHPUnit\Framework\TestCase;
use Tests\Support\PhpSourceScanner;

final class ErrorMessageCompatibilityTest extends TestCase
{
    private const PADL = '/workflow/engine/classes/Padl.php';
    private const ERRORMSG_PATTERN = '\$php_errormsg\b';

    public function testPadlUsesErrorGetLastInsteadOfRemovedPhpErrormsg(): void
    {
        $source = self::padlSource();
        self::assertSame(0, PhpSourceScanner::matchCount($source, self::ERRORMSG_PATTERN));
        self::assertSame(2, substr_count($source, '$lastError = error_get_last();'));
        $guard = <<<'PHP'
        $lastErrorMessage = isset($lastError['message']) ? $lastError['message'] : '';
        PHP;
        self::assertSame(2, substr_count($source, $guard));
    }

    public function testPadlKeepsBothPublicExceptionMessagePrefixes(): void
    {
        $source = self::padlSource();
        self::assertSame(1, substr_count($source, 'throw new Exception("Problem with $url, $lastErrorMessage");'));
        self::assertSame(1, substr_count($source, 'throw new Exception("Problem reading data from $url, $lastErrorMessage");'));
    }

    public function testRemovedVariableGuardCountsCodeButNotLiteralText(): void
    {
        $source = <<<'PHP'
        <?php
        // $php_errormsg is removed.
        $literal = '$php_errormsg';
        $real = $php_errormsg;
        PHP;
        self::assertSame(3, PhpSourceScanner::matchCount($source, self::ERRORMSG_PATTERN));
        self::assertSame(1, PhpSourceScanner::matchCount(PhpSourceScanner::codeOnlySource($source), self::ERRORMSG_PATTERN));
    }

    private static function padlSource(): string
    {
        return PhpSourceScanner::read(PM_TEST_ROOT . self::PADL);
    }
}
