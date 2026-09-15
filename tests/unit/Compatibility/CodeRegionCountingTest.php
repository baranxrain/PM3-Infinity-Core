<?php

declare(strict_types=1);

namespace Tests\Unit\Compatibility;

use PHPUnit\Framework\TestCase;
use Tests\Support\PhpSourceScanner;

final class CodeRegionCountingTest extends TestCase
{
    private const EACH_PATTERN = '(?<![\w$>-])each\s*\(';
    private const UTF8_PATTERN = '(?<![\w$>-])(?:utf8_encode|utf8_decode)\s*\(';

    public function testCommentsAndSingleQuotedStringsAreNotExecutableCode(): void
    {
        $source = <<<'PHP'
        <?php
        // each($legacy);
        /* each($legacy); */
        $pattern = 'each($legacy)';
        $real = each($legacy);
        PHP;

        self::assertSame(4, PhpSourceScanner::matchCount($source, self::EACH_PATTERN));
        self::assertSame(1, PhpSourceScanner::matchCount(PhpSourceScanner::codeOnlySource($source), self::EACH_PATTERN));
    }

    public function testJavaScriptStoredInsideAPhpStringIsNotExecutablePhp(): void
    {
        $source = <<<'PHP'
        <?php
        $functionsToReplace['function_listeners_resize'] = "function(panel) { panel.items.each(function(item) { item.setHeight(500);return true }); }";
        PHP;

        self::assertSame(1, PhpSourceScanner::matchCount($source, self::EACH_PATTERN));
        self::assertSame(0, PhpSourceScanner::matchCount(PhpSourceScanner::codeOnlySource($source), self::EACH_PATTERN));
    }

    public function testInterpolatedExpressionsRemainExecutableCode(): void
    {
        $source = <<<'PHP'
        <?php
        $note = 'utf8_encode($raw)';
        $value = utf8_encode($raw);
        $label = "ID_{$item['APP_STATUS']}";
        $like = "%{$search}%";
        PHP;

        $projection = PhpSourceScanner::codeOnlySource($source);
        self::assertSame(2, PhpSourceScanner::matchCount($source, self::UTF8_PATTERN));
        self::assertSame(1, PhpSourceScanner::matchCount($projection, self::UTF8_PATTERN));
        self::assertStringContainsString('{$item[', $projection);
        self::assertStringContainsString('{$search}', $projection);
    }

    public function testProjectionPreservesOffsetsAndLineNumbers(): void
    {
        $source = <<<'PHP'
        <?php
        // each($a);

        each($b);
        PHP;

        $projection = PhpSourceScanner::codeOnlySource($source);
        self::assertSame(strlen($source), strlen($projection));
        self::assertSame(substr_count($source, "\n"), substr_count($projection, "\n"));
        self::assertSame([4], PhpSourceScanner::matchLines($projection, self::EACH_PATTERN));
    }
}
