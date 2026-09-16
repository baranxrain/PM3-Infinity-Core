<?php

declare(strict_types=1);

namespace Tests\Support;

/**
 * Frozen PHP 8.1 byte oracle used only by compatibility tests.
 * It avoids PHP 8.2 deprecated utf8_encode()/utf8_decode() calls.
 */
final class LegacyUtf8Oracle
{
    public static function encode(string $value): string
    {
        $result = '';
        $length = strlen($value);

        for ($offset = 0; $offset < $length; ++$offset) {
            $byte = ord($value[$offset]);
            if ($byte < 0x80) {
                $result .= $value[$offset];
                continue;
            }

            $result .= chr(0xC0 | ($byte >> 6));
            $result .= chr(0x80 | ($byte & 0x3F));
        }

        return $result;
    }

    public static function decode(string $value): string
    {
        $result = '';
        $length = strlen($value);
        $offset = 0;

        while ($offset < $length) {
            $byte = ord($value[$offset]);
            $available = $length - $offset;
            $advance = 1;
            $codePoint = null;

            if ($byte < 0x80) {
                $codePoint = $byte;
            } elseif ($byte < 0xC2) {
                // Invalid initial byte.
            } elseif ($byte < 0xE0) {
                if ($available >= 2) {
                    $second = ord($value[$offset + 1]);
                    if (self::isTrailByte($second)) {
                        $codePoint = (($byte & 0x1F) << 6) | ($second & 0x3F);
                        $advance = 2;
                        if ($codePoint < 0x80) {
                            $codePoint = null;
                        }
                    } else {
                        $advance = self::isLeadByte($second) ? 1 : 2;
                    }
                }
            } elseif ($byte < 0xF0) {
                $invalidSequence = $available < 3;
                if (!$invalidSequence) {
                    $invalidSequence = !self::isTrailByte(ord($value[$offset + 1]))
                        || !self::isTrailByte(ord($value[$offset + 2]));
                }

                if ($invalidSequence) {
                    if ($available < 2 || self::isLeadByte(ord($value[$offset + 1]))) {
                        $advance = 1;
                    } elseif ($available < 3 || self::isLeadByte(ord($value[$offset + 2]))) {
                        $advance = 2;
                    } else {
                        $advance = 3;
                    }
                } else {
                    $codePoint = (($byte & 0x0F) << 12)
                        | ((ord($value[$offset + 1]) & 0x3F) << 6)
                        | (ord($value[$offset + 2]) & 0x3F);
                    $advance = 3;
                    if ($codePoint < 0x800 || ($codePoint >= 0xD800 && $codePoint <= 0xDFFF)) {
                        $codePoint = null;
                    }
                }
            } elseif ($byte < 0xF5) {
                $invalidSequence = $available < 4;
                if (!$invalidSequence) {
                    $invalidSequence = !self::isTrailByte(ord($value[$offset + 1]))
                        || !self::isTrailByte(ord($value[$offset + 2]))
                        || !self::isTrailByte(ord($value[$offset + 3]));
                }

                if ($invalidSequence) {
                    if ($available < 2 || self::isLeadByte(ord($value[$offset + 1]))) {
                        $advance = 1;
                    } elseif ($available < 3 || self::isLeadByte(ord($value[$offset + 2]))) {
                        $advance = 2;
                    } elseif ($available < 4 || self::isLeadByte(ord($value[$offset + 3]))) {
                        $advance = 3;
                    } else {
                        $advance = 4;
                    }
                } else {
                    $codePoint = (($byte & 0x07) << 18)
                        | ((ord($value[$offset + 1]) & 0x3F) << 12)
                        | ((ord($value[$offset + 2]) & 0x3F) << 6)
                        | (ord($value[$offset + 3]) & 0x3F);
                    $advance = 4;
                    if ($codePoint < 0x10000 || $codePoint > 0x10FFFF) {
                        $codePoint = null;
                    }
                }
            }

            $offset += $advance;
            $result .= $codePoint !== null && $codePoint <= 0xFF ? chr($codePoint) : '?';
        }

        return $result;
    }

    private static function isLeadByte(int $byte): bool
    {
        return $byte < 0x80 || ($byte >= 0xC2 && $byte <= 0xF4);
    }

    private static function isTrailByte(int $byte): bool
    {
        return $byte >= 0x80 && $byte <= 0xBF;
    }
}
