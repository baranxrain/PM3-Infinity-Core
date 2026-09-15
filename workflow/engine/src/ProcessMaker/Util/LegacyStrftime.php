<?php

namespace ProcessMaker\Util;

use DateTimeImmutable;
use DateTimeZone;

/**
 * Behaviour-preserving replacement for the deprecated native strftime function.
 *
 * U-2.3.2a introduces this helper and its contract only. It is deliberately
 * NOT wired into any call site yet; the 138 executable call sites stay
 * exactly where U-2.3.1 inventoried them (U-2.3.2 and U-2.3.3 migrate them).
 *
 * The contract is derived from a real oracle captured on the acceptance
 * runtime (Windows, PHP 8.1.10 ZTS, LC_TIME=C) and stored in
 * tests/fixtures/legacy-strftime-contract.json. Two rules follow from it:
 *
 * 1. The native function rendered every date and time field in PHP's default
 *    timezone, so this helper does the same and nothing else.
 * 2. On that runtime the native function returned false for %k, %l, %P and %s.
 *    In addition, native %z and %Z did NOT follow PHP's timezone at all: they
 *    printed the operating system zone ('+0100', 'W. Europe Standard Time')
 *    for every timestamp, including summer dates and while PHP ran in UTC.
 *    That output cannot be reproduced portably or even consistently, so this
 *    helper refuses %z and %Z instead of inventing a value. A call site that
 *    needs a zone must be migrated explicitly, never silently.
 *
 * Locale independence is intentional: output follows the C locale, which is
 * what the acceptance runtime records, so setlocale() cannot change it.
 */
final class LegacyStrftime
{
    /** Specifiers this helper answers, all verified against the oracle. */
    private const SUPPORTED = [
        '%%', '%a', '%A', '%b', '%B', '%c', '%C', '%d', '%D', '%e', '%F',
        '%g', '%G', '%h', '%H', '%I', '%j', '%m', '%M', '%n', '%p', '%r',
        '%R', '%S', '%t', '%T', '%u', '%U', '%V', '%w', '%W', '%x', '%X',
        '%y', '%Y',
    ];

    /**
     * Specifiers this helper refuses, mirroring native false returns
     * (%k, %l, %P, %s) plus the two non-reproducible zone specifiers.
     */
    private const REFUSED = ['%k', '%l', '%P', '%s', '%z', '%Z'];

    private const DAYS = [
        'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday',
        'Sunday',
    ];

    private const MONTHS = [
        'January', 'February', 'March', 'April', 'May', 'June', 'July',
        'August', 'September', 'October', 'November', 'December',
    ];

    private function __construct()
    {
    }

    /**
     * Drop-in replacement for the native call with a mask and a timestamp.
     *
     * @param string   $format    strftime mask.
     * @param int|null $timestamp Unix timestamp; null means "now".
     *
     * @return string|false false when the mask contains a specifier this
     *                      helper refuses or does not know, exactly like
     *                      the native function on the acceptance runtime.
     */
    public static function format(string $format, ?int $timestamp = null)
    {
        $timestamp = $timestamp ?? time();
        $date = (new DateTimeImmutable('@' . $timestamp))
            ->setTimezone(new DateTimeZone(date_default_timezone_get()));

        $out = '';
        $length = strlen($format);

        for ($i = 0; $i < $length; $i++) {
            if ($format[$i] !== '%' || $i + 1 >= $length) {
                $out .= $format[$i];
                continue;
            }

            $specifier = substr($format, $i, 2);
            if (!in_array($specifier, self::SUPPORTED, true)) {
                return false;
            }

            $out .= self::renderSpecifier($specifier, $date);
            $i++;
        }

        return $out;
    }

    /** Specifiers this helper renders. */
    public static function supportedSpecifiers(): array
    {
        return self::SUPPORTED;
    }

    /** Specifiers this helper deliberately refuses. */
    public static function refusedSpecifiers(): array
    {
        return self::REFUSED;
    }

    /** True when every specifier in $format is supported. */
    public static function supports(string $format): bool
    {
        return self::format($format, 0) !== false;
    }

    private static function renderSpecifier(string $specifier, DateTimeImmutable $date): string
    {
        $year = (int) $date->format('Y');
        $month = (int) $date->format('n');
        $day = (int) $date->format('j');
        $hour24 = (int) $date->format('G');
        $minute = (int) $date->format('i');
        $second = (int) $date->format('s');
        $isoDay = (int) $date->format('N');
        $isoYear = (int) $date->format('o');
        $dayOfYear = ((int) $date->format('z')) + 1;
        $hour12 = $hour24 % 12 === 0 ? 12 : $hour24 % 12;
        $meridiem = $hour24 < 12 ? 'AM' : 'PM';

        switch ($specifier) {
            case '%%':
                return '%';
            case '%a':
                return substr(self::DAYS[$isoDay - 1], 0, 3);
            case '%A':
                return self::DAYS[$isoDay - 1];
            case '%b':
            case '%h':
                return substr(self::MONTHS[$month - 1], 0, 3);
            case '%B':
                return self::MONTHS[$month - 1];
            case '%c':
                return sprintf(
                    '%s %s %2d %02d:%02d:%02d %04d',
                    substr(self::DAYS[$isoDay - 1], 0, 3),
                    substr(self::MONTHS[$month - 1], 0, 3),
                    $day,
                    $hour24,
                    $minute,
                    $second,
                    $year
                );
            case '%C':
                return sprintf('%02d', intdiv($year, 100));
            case '%d':
                return sprintf('%02d', $day);
            case '%D':
            case '%x':
                return sprintf('%02d/%02d/%02d', $month, $day, $year % 100);
            case '%e':
                return sprintf('%2d', $day);
            case '%F':
                return sprintf('%04d-%02d-%02d', $year, $month, $day);
            case '%g':
                return sprintf('%02d', $isoYear % 100);
            case '%G':
                return sprintf('%04d', $isoYear);
            case '%H':
                return sprintf('%02d', $hour24);
            case '%I':
                return sprintf('%02d', $hour12);
            case '%j':
                return sprintf('%03d', $dayOfYear);
            case '%m':
                return sprintf('%02d', $month);
            case '%M':
                return sprintf('%02d', $minute);
            case '%n':
                return "\n";
            case '%p':
                return $meridiem;
            case '%r':
                return sprintf('%02d:%02d:%02d %s', $hour12, $minute, $second, $meridiem);
            case '%R':
                return sprintf('%02d:%02d', $hour24, $minute);
            case '%S':
                return sprintf('%02d', $second);
            case '%t':
                return "\t";
            case '%T':
            case '%X':
                return sprintf('%02d:%02d:%02d', $hour24, $minute, $second);
            case '%u':
                return (string) $isoDay;
            case '%U':
                // Week number, Sunday as the first day of the week.
                return sprintf('%02d', intdiv($dayOfYear + 6 - ($isoDay % 7), 7));
            case '%V':
                return sprintf('%02d', (int) $date->format('W'));
            case '%w':
                return (string) ($isoDay % 7);
            case '%W':
                // Week number, Monday as the first day of the week.
                return sprintf('%02d', intdiv($dayOfYear + 6 - (($isoDay + 6) % 7), 7));
            case '%y':
                return sprintf('%02d', $year % 100);
            case '%Y':
                return sprintf('%04d', $year);
        }

        // Unreachable: format() validates the specifier before calling this.
        return '';
    }
}
