<?php

declare(strict_types=1);

namespace Tests\Support;

use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;
use SplFileInfo;

/**
 * Region-aware ledger over a fixed set of engine directories.
 *
 * Every family is counted twice: once over raw text, which is what the U-1
 * ratchet measures, and once over executable PHP only. The two numbers differ
 * a lot in this code base, because deprecated API names also appear in
 * comments, in single-quoted regular expressions and in JavaScript that is
 * stored inside PHP strings. Scans are memoised: the engine scope holds about
 * 1800 files and several tests share the same scan.
 */
final class CompatibilityLedger
{
    /** @var array<string, list<string>> */
    private static array $files = [];

    /** @var array<string, array<string, array{textual: int, code: int}>> */
    private static array $counts = [];

    /** @var array<string, array{count: int, files: list<string>}> */
    private static array $interpolations = [];

    /**
     * @param list<string> $scopes
     * @return list<string>
     */
    public static function phpFiles(array $scopes): array
    {
        $key = implode('|', $scopes);
        if (isset(self::$files[$key])) {
            return self::$files[$key];
        }

        $files = [];
        foreach ($scopes as $scope) {
            $directory = PM_TEST_ROOT . '/' . $scope;
            if (!is_dir($directory)) {
                throw new RuntimeException('Compatibility scope is missing: ' . $scope);
            }

            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($directory, RecursiveDirectoryIterator::SKIP_DOTS)
            );

            /** @var SplFileInfo $file */
            foreach ($iterator as $file) {
                if ($file->isFile() && strtolower($file->getExtension()) === 'php') {
                    $files[] = $file->getPathname();
                }
            }
        }

        sort($files, SORT_STRING);

        return self::$files[$key] = $files;
    }

    /**
     * @param list<string> $scopes
     * @param array<string, string> $patterns
     * @return array<string, array{textual: int, code: int}>
     */
    public static function counts(array $scopes, array $patterns): array
    {
        $key = md5(serialize([$scopes, $patterns]));
        if (isset(self::$counts[$key])) {
            return self::$counts[$key];
        }

        $totals = [];
        foreach (array_keys($patterns) as $family) {
            $totals[$family] = ['textual' => 0, 'code' => 0];
        }

        foreach (self::phpFiles($scopes) as $file) {
            $source = PhpSourceScanner::read($file);
            $code = PhpSourceScanner::codeOnlySource($source);

            foreach ($patterns as $family => $pattern) {
                $totals[$family]['textual'] += PhpSourceScanner::matchCount($source, $pattern);
                $totals[$family]['code'] += PhpSourceScanner::matchCount($code, $pattern);
            }
        }

        return self::$counts[$key] = $totals;
    }

    /**
     * Executable-code hits as "relative/path.php:line", for readable failures.
     *
     * @param list<string> $scopes
     * @return list<string>
     */
    public static function locate(array $scopes, string $pattern, int $limit = 10): array
    {
        $found = [];
        foreach (self::phpFiles($scopes) as $file) {
            $source = PhpSourceScanner::read($file);
            if (PhpSourceScanner::matchCount($source, $pattern) === 0) {
                continue;
            }

            foreach (PhpSourceScanner::matchLines(PhpSourceScanner::codeOnlySource($source), $pattern) as $line) {
                $found[] = self::relative($file) . ':' . $line;
                if (count($found) >= $limit) {
                    return $found;
                }
            }
        }

        return $found;
    }

    /**
     * @param list<string> $scopes
     * @return array{count: int, files: list<string>}
     */
    public static function dollarBraceInterpolations(array $scopes): array
    {
        $key = implode('|', $scopes);
        if (isset(self::$interpolations[$key])) {
            return self::$interpolations[$key];
        }

        $count = 0;
        $files = [];
        foreach (self::phpFiles($scopes) as $file) {
            $source = PhpSourceScanner::read($file);
            if (strpos($source, '${') === false) {
                continue;
            }

            $inFile = PhpSourceScanner::countDollarBraceInterpolations($source);
            if ($inFile === 0) {
                continue;
            }

            $count += $inFile;
            if (count($files) < 10) {
                $files[] = self::relative($file);
            }
        }

        return self::$interpolations[$key] = ['count' => $count, 'files' => $files];
    }

    public static function relative(string $file): string
    {
        $path = str_replace('\\', '/', $file);
        $root = rtrim(str_replace('\\', '/', PM_TEST_ROOT), '/') . '/';

        return strpos($path, $root) === 0 ? substr($path, strlen($root)) : $path;
    }
}
