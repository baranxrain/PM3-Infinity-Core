<?php

declare(strict_types=1);

namespace Tests\Support;

use ParseError;
use RuntimeException;

/**
 * Reads API declarations without executing legacy ProcessMaker source files.
 */
final class PhpSourceScanner
{
    /**
     * Token types whose bytes are not executable PHP. They are blanked out
     * before a legacy-API pattern is applied, which is the difference between
     * counting real calls and counting comments, regex literals or JavaScript
     * that happens to live inside a PHP string.
     */
    private const BLANKED_TOKENS = [
        T_COMMENT,
        T_DOC_COMMENT,
        T_INLINE_HTML,
        T_CONSTANT_ENCAPSED_STRING,
        T_ENCAPSED_AND_WHITESPACE,
        T_START_HEREDOC,
        T_END_HEREDOC,
    ];

    /** @return list<string> */
    public static function declaredPublicMethods(string $file, string $expectedClass): array
    {
        $tokens = self::tokens($file);
        $methods = [];
        $namespace = '';
        $braceDepth = 0;
        $pendingClass = null;
        $classStack = [];

        foreach ($tokens as $index => $token) {
            if (is_array($token)) {
                [$id] = $token;

                if ($id === T_CURLY_OPEN || $id === T_DOLLAR_OPEN_CURLY_BRACES) {
                    // "{$var}" and "${var}" open a brace inside a string, but PHP closes
                    // it with a plain '}' token. Both openers must raise the depth or the
                    // scanner loses the class scope for every later declaration.
                    ++$braceDepth;
                    continue;
                }

                if ($id === T_NAMESPACE) {
                    $namespace = self::namespaceAfter($tokens, $index + 1);
                    continue;
                }

                if ($id === T_CLASS && self::previousSignificantToken($tokens, $index - 1) !== T_DOUBLE_COLON) {
                    $shortName = self::nameAfter($tokens, $index + 1);
                    $pendingClass = $shortName === null
                        ? null
                        : ltrim(($namespace === '' ? '' : $namespace . '\\') . $shortName, '\\');
                    continue;
                }

                if ($id !== T_FUNCTION || $classStack === []) {
                    continue;
                }

                $currentClass = $classStack[count($classStack) - 1];
                if ($currentClass['name'] !== ltrim($expectedClass, '\\') || $braceDepth !== $currentClass['depth']) {
                    continue;
                }

                $method = self::nameAfter($tokens, $index + 1);
                if ($method === null || strncmp($method, '__', 2) === 0) {
                    continue;
                }

                if (self::visibilityBefore($tokens, $index - 1) === 'public') {
                    $methods[] = $method;
                }
                continue;
            }

            if ($token === '{') {
                ++$braceDepth;
                if ($pendingClass !== null) {
                    $classStack[] = ['name' => $pendingClass, 'depth' => $braceDepth];
                    $pendingClass = null;
                }
                continue;
            }

            if ($token === '}') {
                if ($classStack !== [] && $classStack[count($classStack) - 1]['depth'] === $braceDepth) {
                    array_pop($classStack);
                }
                --$braceDepth;
            }
        }

        $methods = array_values(array_unique($methods));
        sort($methods, SORT_STRING);

        return $methods;
    }

    /** @return list<string> */
    public static function declaredGlobalFunctions(string $file): array
    {
        $tokens = self::tokens($file);
        $functions = [];
        $braceDepth = 0;
        $pendingClass = false;
        $classDepths = [];

        foreach ($tokens as $index => $token) {
            if (is_array($token)) {
                [$id] = $token;
                if ($id === T_CURLY_OPEN || $id === T_DOLLAR_OPEN_CURLY_BRACES) {
                    // Same string-interpolation rule as declaredPublicMethods().
                    ++$braceDepth;
                    continue;
                }

                if ($id === T_CLASS && self::previousSignificantToken($tokens, $index - 1) !== T_DOUBLE_COLON) {
                    $pendingClass = self::nameAfter($tokens, $index + 1) !== null;
                    continue;
                }

                if ($id === T_FUNCTION && $braceDepth === 0 && $classDepths === []) {
                    $function = self::nameAfter($tokens, $index + 1);
                    if ($function !== null) {
                        $functions[] = $function;
                    }
                }
                continue;
            }

            if ($token === '{') {
                ++$braceDepth;
                if ($pendingClass) {
                    $classDepths[] = $braceDepth;
                    $pendingClass = false;
                }
                continue;
            }

            if ($token === '}') {
                if ($classDepths !== [] && $classDepths[count($classDepths) - 1] === $braceDepth) {
                    array_pop($classDepths);
                }
                --$braceDepth;
            }
        }

        $functions = array_values(array_unique($functions));
        sort($functions, SORT_STRING);

        return $functions;
    }

    /**
     * Projects PHP source onto executable code only. Comments, inline HTML and
     * literal string bytes become spaces; interpolated variables and "{$expr}"
     * regions are kept because PHP really executes them. Byte offsets and line
     * breaks are preserved so hits can be reported with usable line numbers.
     */
    public static function codeOnlySource(string $source): string
    {
        $projection = '';

        foreach (token_get_all($source) as $token) {
            if (is_string($token)) {
                $projection .= $token;
                continue;
            }

            [$id, $text] = $token;
            if (in_array($id, self::BLANKED_TOKENS, true)) {
                $projection .= (string) preg_replace('/[^\r\n]/', ' ', $text);
                continue;
            }

            $projection .= $text;
        }

        return $projection;
    }

    public static function codeOnlyFile(string $file): string
    {
        return self::codeOnlySource(self::read($file));
    }

    /**
     * Counts "${name}" string interpolation, deprecated in PHP 8.2. Variable
     * variables written as ${$name} in ordinary code are a different construct
     * and are deliberately not counted.
     */
    public static function countDollarBraceInterpolations(string $source): int
    {
        if (!defined('T_DOLLAR_OPEN_CURLY_BRACES')) {
            return substr_count(self::codeOnlySource($source), '${');
        }

        $count = 0;
        foreach (token_get_all($source) as $token) {
            if (is_array($token) && $token[0] === T_DOLLAR_OPEN_CURLY_BRACES) {
                ++$count;
            }
        }

        return $count;
    }

    public static function matchCount(string $subject, string $pattern): int
    {
        $count = preg_match_all(self::regex($pattern), $subject);
        if ($count === false) {
            throw new RuntimeException('Invalid compatibility pattern: ' . $pattern);
        }

        return $count;
    }

    /** @return list<int> one-based line numbers of every match */
    public static function matchLines(string $subject, string $pattern): array
    {
        $matches = [];
        if (preg_match_all(self::regex($pattern), $subject, $matches, PREG_OFFSET_CAPTURE) === false) {
            throw new RuntimeException('Invalid compatibility pattern: ' . $pattern);
        }

        $lines = [];
        foreach ($matches[0] as $match) {
            $lines[] = substr_count($subject, "\n", 0, (int) $match[1]) + 1;
        }

        return $lines;
    }

    public static function regex(string $pattern): string
    {
        return '~' . str_replace('~', '\~', $pattern) . '~i';
    }

    public static function read(string $file): string
    {
        $source = @file_get_contents($file);
        if ($source === false) {
            throw new RuntimeException('Unable to read PHP source: ' . $file);
        }

        return $source;
    }

    /** @return array<int, array{0:int,1:string,2:int}|string> */
    private static function tokens(string $file): array
    {
        $source = @file_get_contents($file);
        if ($source === false) {
            throw new RuntimeException('Unable to read PHP source: ' . $file);
        }

        try {
            return token_get_all($source, TOKEN_PARSE);
        } catch (ParseError $error) {
            throw new RuntimeException('Unable to parse PHP source: ' . $file . ' — ' . $error->getMessage(), 0, $error);
        }
    }

    /** @param array<int, array{0:int,1:string,2:int}|string> $tokens */
    private static function namespaceAfter(array $tokens, int $start): string
    {
        $parts = [];
        $qualifiedNameTokens = [T_STRING, T_NS_SEPARATOR];
        if (defined('T_NAME_QUALIFIED')) {
            $qualifiedNameTokens[] = T_NAME_QUALIFIED;
        }

        for ($index = $start, $count = count($tokens); $index < $count; ++$index) {
            $token = $tokens[$index];
            if ($token === ';' || $token === '{') {
                break;
            }
            if (is_array($token) && in_array($token[0], $qualifiedNameTokens, true)) {
                $parts[] = $token[1];
            }
        }

        return trim(implode('', $parts), '\\');
    }

    /** @param array<int, array{0:int,1:string,2:int}|string> $tokens */
    private static function nameAfter(array $tokens, int $start): ?string
    {
        for ($index = $start, $count = count($tokens); $index < $count; ++$index) {
            $token = $tokens[$index];
            if ($token === '(') {
                return null;
            }
            if (is_array($token) && $token[0] === T_STRING) {
                return $token[1];
            }
            if (is_array($token) && in_array($token[0], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true)) {
                continue;
            }
            if ($token === '&') {
                continue;
            }
        }

        return null;
    }

    /** @param array<int, array{0:int,1:string,2:int}|string> $tokens */
    private static function visibilityBefore(array $tokens, int $start): string
    {
        for ($index = $start; $index >= 0; --$index) {
            $token = $tokens[$index];
            if (!is_array($token)) {
                break;
            }
            if (in_array($token[0], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT, T_STATIC, T_FINAL, T_ABSTRACT], true)) {
                continue;
            }
            if ($token[0] === T_PRIVATE) {
                return 'private';
            }
            if ($token[0] === T_PROTECTED) {
                return 'protected';
            }
            if ($token[0] === T_PUBLIC) {
                return 'public';
            }
            break;
        }

        return 'public';
    }

    /** @param array<int, array{0:int,1:string,2:int}|string> $tokens */
    private static function previousSignificantToken(array $tokens, int $start): int|string|null
    {
        for ($index = $start; $index >= 0; --$index) {
            $token = $tokens[$index];
            if (is_array($token) && in_array($token[0], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true)) {
                continue;
            }
            return is_array($token) ? $token[0] : $token;
        }

        return null;
    }
}
