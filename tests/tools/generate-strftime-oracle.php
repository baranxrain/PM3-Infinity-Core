<?php

declare(strict_types=1);

/**
 * U-2.3.1 tool: record the native strftime() behaviour of the ACCEPTANCE
 * runtime (Windows / Laragon PHP 8.1.x), which is the only oracle U-2.3.2 may
 * be designed against. strftime() is a thin wrapper over the platform C
 * library, so a fixture generated on Linux would be wrong for this project.
 *
 * The tool only writes tests/fixtures/legacy-strftime-oracle.json. It never
 * touches production code and no test asserts its values, because they are
 * runtime facts, not expectations.
 */

$root = dirname(__DIR__, 2);
$target = $root . '/tests/fixtures/legacy-strftime-oracle.json';

$timestamps = [
    'epoch' => 0,
    'winter_morning' => 1104537845,   // 2005-01-01 01:24:05 UTC (Saturday)
    'summer_noon' => 1119873600,      // 2005-06-27 12:00:00 UTC (Monday)
    'leap_day' => 1583020800,         // 2020-03-01 00:00:00 UTC (Sunday)
    'year_end' => 1609459199,         // 2020-12-31 23:59:59 UTC (Thursday)
    'iso_week_edge' => 1546214400,    // 2018-12-31 00:00:00 UTC (Monday)
];

$specifiers = [
    '%a', '%A', '%b', '%B', '%c', '%C', '%d', '%D', '%e', '%F', '%g', '%G',
    '%h', '%H', '%I', '%j', '%k', '%l', '%m', '%M', '%n', '%p', '%P', '%r',
    '%R', '%s', '%S', '%t', '%T', '%u', '%U', '%V', '%w', '%W', '%x', '%X',
    '%y', '%Y', '%z', '%Z', '%%',
];

$masks = [
    '%Y-%m-%d',
    '%d/%m/%Y',
    '%m/%d/%Y %H:%M',
    '%B %d, %Y',
    '%a %d %b %Y %H:%M:%S',
    '%H:%M:%S',
];

$previousTimezone = date_default_timezone_get();
date_default_timezone_set('UTC');

$capture = static function (string $format, int $timestamp): array {
    $errors = [];
    set_error_handler(static function (int $number, string $message) use (&$errors): bool {
        $errors[] = $message;

        return true;
    });
    $value = @strftime($format, $timestamp);
    restore_error_handler();

    return [
        'value' => $value === false ? null : $value,
        'supported' => $value !== false && $value !== '',
        'notices' => $errors,
    ];
};

$oracle = [
    '_note' => 'U-2.3.1 evidence only. Generated on the acceptance runtime; no test asserts these values.',
    'runtime' => [
        'phpVersion' => PHP_VERSION,
        'phpOs' => PHP_OS_FAMILY,
        'uname' => php_uname('s') . ' ' . php_uname('r') . ' ' . php_uname('m'),
        'zendThreadSafe' => ZEND_THREAD_SAFE,
        'timezoneUsed' => 'UTC',
        'lcTime' => setlocale(LC_TIME, '0'),
        'intlLoaded' => extension_loaded('intl'),
        'generatedAtUtc' => gmdate('c'),
    ],
    'timestamps' => $timestamps,
    'specifiers' => [],
    'masks' => [],
];

foreach ($specifiers as $specifier) {
    foreach ($timestamps as $name => $timestamp) {
        $oracle['specifiers'][$specifier][$name] = $capture($specifier, $timestamp);
    }
}

foreach ($masks as $mask) {
    foreach ($timestamps as $name => $timestamp) {
        $oracle['masks'][$mask][$name] = $capture($mask, $timestamp);
    }
}

date_default_timezone_set($previousTimezone);

$unsupported = [];
foreach ($oracle['specifiers'] as $specifier => $rows) {
    if (!$rows['epoch']['supported']) {
        $unsupported[] = $specifier;
    }
}
$oracle['unsupportedSpecifiers'] = $unsupported;

$encoded = json_encode($oracle, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
if ($encoded === false) {
    fwrite(STDERR, 'Unable to encode oracle.' . PHP_EOL);
    exit(1);
}

file_put_contents($target, $encoded . PHP_EOL);
echo '[OK] Wrote strftime oracle for PHP ' . PHP_VERSION . ' on ' . PHP_OS_FAMILY . '.' . PHP_EOL;
echo '[INFO] Specifiers unsupported on this runtime: ' . ($unsupported === [] ? 'none' : implode(' ', $unsupported)) . PHP_EOL;
