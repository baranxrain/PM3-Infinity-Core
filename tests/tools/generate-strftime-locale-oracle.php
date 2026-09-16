<?php

declare(strict_types=1);

/**
 * U-2.4.1 tool: record how the ACCEPTANCE runtime (Windows / Laragon PHP 8.1.x)
 * renders the locale-dependent date masks that Configurations::getSystemDate()
 * builds, for every locale name that method can pass to setlocale(LC_TIME, ...).
 *
 * strftime() is a thin wrapper over the platform C library and the locale names
 * differ between Windows ('ESN', 'PTB', 'EST') and glibc ('es_ES', 'pt_BR',
 * 'en_US'), so an oracle generated on Linux would be worthless for this
 * project. A later unit may only be designed against this captured evidence.
 *
 * The tool writes exactly one fixture, the file named in $target below, and
 * nothing else. The name is deliberately not repeated in this comment: the
 * preflight guard counts its occurrences in this source to prove the write
 * scope, so the single mention must be the write target itself. The tool never
 * touches production code, and no test asserts its rendered values, because
 * they are runtime facts rather than expectations.
 */

$root = dirname(__DIR__, 2);
require_once $root . '/tests/Support/LegacyUtf8Oracle.php';
$target = $root . '/tests/fixtures/legacy-strftime-locale-oracle.json';

// The user-selectable date formats, copied verbatim from
// Configurations::getDateFormats(). Written with single quotes here so the
// backslashes stay literal.
$dateFormats = [
    'ID_DATE_FORMAT_1' => 'Y-m-d H:i:s',
    'ID_DATE_FORMAT_2' => 'd/m/Y',
    'ID_DATE_FORMAT_3' => 'm/d/Y',
    'ID_DATE_FORMAT_4' => 'Y/d/m',
    'ID_DATE_FORMAT_5' => 'Y/m/d',
    'ID_DATE_FORMAT_6' => 'F j, Y, g:i a',
    'ID_DATE_FORMAT_7' => 'm.d.y',
    'ID_DATE_FORMAT_8' => 'j, n, Y',
    'ID_DATE_FORMAT_9' => 'D M j G:i:s T Y',
    'ID_DATE_FORMAT_15' => 'M d, Y',
    'ID_DATE_FORMAT_16' => 'm D, Y',
    'ID_DATE_FORMAT_10' => 'D d M, Y',
    'ID_DATE_FORMAT_11' => 'D M, Y',
    'ID_DATE_FORMAT_12' => 'd M, Y',
    'ID_DATE_FORMAT_13' => 'd m, Y',
    'ID_DATE_FORMAT_14' => 'd.m.Y',
    'ID_DATE_FORMAT_17' => 'd \d\e F \d\e Y',
];

// The mask translation table of Configurations::getSystemDate(). Duplicate
// keys in the production literal are resolved the way PHP resolves them: the
// last assignment wins, so 'G' is '%H' and 'g' is '%I'.
$maskTime = [
    'd' => '%d', 'D' => '%A', 'j' => '%d', 'l' => '%A', 'N' => '%u',
    'S' => '%d', 'w' => '%w', 'z' => '%j', 'W' => '%W', 'F' => '%B',
    'm' => '%m', 'M' => '%B', 'n' => '%m', 'o' => '%Y', 'Y' => '%Y',
    'y' => '%g', 'a' => '%p', 'A' => '%p', 'g' => '%I', 'G' => '%H',
    'h' => '%I', 'H' => '%H', 'i' => '%M', 's' => '%S',
];

$translate = static function (string $mask) use ($maskTime): string {
    $mask = trim($mask);
    if (strpos($mask, ' \d\e ') !== false) {
        $mask = str_replace(' \d\e ', ' [xx] ', $mask);
    }

    $out = '';
    for ($i = 0; $i < strlen($mask); ++$i) {
        $out .= ($mask[$i] !== ' ' && isset($maskTime[$mask[$i]])) ? $maskTime[$mask[$i]] : $mask[$i];
    }

    return $out;
};

// Locale names Configurations::getSystemDate() can reach. The Windows branch
// uses the three-letter names; the linux/darwin branch uses glibc names, and a
// PARTNER_FLAG install omits the '.utf8' suffix.
$locales = [
    'windows_en' => 'EST',
    'windows_es' => 'ESN',
    'windows_pt' => 'PTB',
    'windows_en_utf8' => 'EST.utf8',
    'windows_es_utf8' => 'ESN.utf8',
    'windows_pt_utf8' => 'PTB.utf8',
    'glibc_en' => 'en_US',
    'glibc_es' => 'es_ES',
    'glibc_pt' => 'pt_BR',
    'glibc_en_utf8' => 'en_US.utf8',
    'glibc_es_utf8' => 'es_ES.utf8',
    'glibc_pt_utf8' => 'pt_BR.utf8',
    'c_locale' => 'C',
];

$timestamps = [
    'winter_morning' => 1104537845, // 2005-01-01 01:24:05 UTC (Saturday)
    'summer_noon' => 1119873600,    // 2005-06-27 12:00:00 UTC (Monday)
    'year_end' => 1609459199,       // 2020-12-31 23:59:59 UTC (Thursday)
];

$previousTimezone = date_default_timezone_get();
$previousLcTime = setlocale(LC_TIME, '0');
date_default_timezone_set('UTC');

$capture = static function (string $format, int $timestamp): array {
    $notices = [];
    set_error_handler(static function (int $number, string $message) use (&$notices): bool {
        $notices[] = $message;

        return true;
    });
    $value = @strftime($format, $timestamp);
    restore_error_handler();

    if ($value === false) {
        return ['hex' => null, 'utf8' => null, 'isUtf8' => null, 'notices' => $notices];
    }

    // The rendered month and day names are non-ASCII in a single-byte Windows
    // codepage, which is NOT valid UTF-8, so the raw string is deliberately
    // never stored: json_encode() would have to mangle it. The bytes are kept
    // as hexadecimal, and next to them the ISO-8859-1 lift, which is both valid
    // UTF-8 and exactly what the PARTNER_FLAG branch of getSystemDate() emits.
    return [
        'hex' => bin2hex($value),
        'utf8' => \Tests\Support\LegacyUtf8Oracle::encode($value),
        'isUtf8' => (bool) preg_match('//u', $value),
        'notices' => $notices,
    ];
};

$oracle = [
    '_note' => 'U-2.4.1 evidence only. Captured on the acceptance runtime; no test asserts these rendered values.',
    '_scope' => 'Locale-dependent rendering of Configurations::getSystemDate(), the last two executable strftime() sites (Configurations.php:582 and :585).',
    'runtime' => [
        'phpVersion' => PHP_VERSION,
        'phpOs' => PHP_OS_FAMILY,
        'uname' => php_uname('s') . ' ' . php_uname('r') . ' ' . php_uname('m'),
        'zendThreadSafe' => ZEND_THREAD_SAFE,
        'timezoneUsed' => 'UTC',
        'lcTimeAtStart' => $previousLcTime === false ? null : $previousLcTime,
        'intlLoaded' => extension_loaded('intl'),
        'mbstringLoaded' => extension_loaded('mbstring'),
        'generatedAtUtc' => gmdate('c'),
    ],
    'timestamps' => $timestamps,
    'dateFormats' => $dateFormats,
    'translatedMasks' => [],
    'locales' => [],
];

foreach ($dateFormats as $id => $mask) {
    $oracle['translatedMasks'][$id] = ['dateFormat' => $mask, 'strftimeMask' => $translate($mask)];
}

foreach ($locales as $name => $locale) {
    $applied = setlocale(LC_TIME, $locale);
    $row = [
        'requested' => $locale,
        'applied' => $applied === false ? null : $applied,
        'accepted' => $applied !== false,
        'masks' => [],
        'monthNames' => [],
        'dayNames' => [],
    ];

    if ($applied !== false) {
        foreach ($oracle['translatedMasks'] as $id => $entry) {
            foreach ($timestamps as $stamp => $timestamp) {
                $row['masks'][$id][$stamp] = $capture($entry['strftimeMask'], $timestamp);
            }
        }
        foreach (range(1, 12) as $month) {
            $row['monthNames'][$month] = $capture('%B|%b', (int) mktime(12, 0, 0, $month, 15, 2020));
        }
        foreach (range(0, 6) as $offset) {
            // 2020-11-15 was a Sunday, so the offsets walk a full week.
            $row['dayNames'][$offset] = $capture('%A|%a', (int) mktime(12, 0, 0, 11, 15 + $offset, 2020));
        }
    }

    $oracle['locales'][$name] = $row;
}

setlocale(LC_TIME, $previousLcTime === false ? 'C' : $previousLcTime);
date_default_timezone_set($previousTimezone);

$accepted = [];
foreach ($oracle['locales'] as $name => $row) {
    if ($row['accepted']) {
        $accepted[] = $name;
    }
}
$oracle['acceptedLocales'] = $accepted;

$encoded = json_encode($oracle, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
if ($encoded === false) {
    fwrite(STDERR, 'Unable to encode locale oracle: ' . json_last_error_msg() . PHP_EOL);
    exit(1);
}

if (file_put_contents($target, $encoded . PHP_EOL) === false) {
    fwrite(STDERR, 'Unable to write ' . $target . PHP_EOL);
    exit(1);
}

echo '[OK] Wrote locale oracle for PHP ' . PHP_VERSION . ' on ' . PHP_OS_FAMILY . '.' . PHP_EOL;
echo '[INFO] Locales accepted by this runtime: ' . ($accepted === [] ? 'none' : implode(' ', $accepted)) . PHP_EOL;
echo '[INFO] Masks captured per accepted locale: ' . count($oracle['translatedMasks']) . PHP_EOL;
