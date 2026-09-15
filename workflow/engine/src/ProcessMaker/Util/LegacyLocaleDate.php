<?php

namespace ProcessMaker\Util;

/**
 * Locale-aware replacement for the last two native strftime call sites,
 * Configurations::getSystemDate() lines 582 and 585.
 *
 * Those two calls are not like the 136 Propel getters U-2.3.2 migrated: they
 * run right after setlocale(LC_TIME, ...), so the native function rendered
 * month names, day names and the meridiem through the platform C library.
 * LegacyStrftime deliberately answers in the C locale only, so it cannot
 * carry them on its own.
 *
 * Every table below is transcribed from evidence captured on the acceptance
 * runtime by tests/tools/generate-strftime-locale-oracle.php and stored in
 * tests/fixtures/legacy-strftime-locale-oracle.json. Nothing here is invented,
 * with the single deliberate exception recorded under "English" below.
 *
 * What the oracle proved (PHP 8.1.10, Windows NT 10.0, ZTS):
 *
 * 1. The Windows CRT resolved the shipped locale names as
 *    'ESN' => Spanish_Spain.1252, 'PTB' => Portuguese_Brazil.1252 and
 *    'EST' => Spanish_United States.1252. The last one is a long-standing
 *    product defect: the English and default branch of getSystemDate()
 *    rendered Spanish month and day names. By owner decision this unit fixes
 *    it, so 'EST' now renders English, exactly the names the C locale gave.
 *    This is the only intentional behaviour change in U-2.4.2.
 * 2. Every glibc name the linux/darwin branch builds ('en_US', 'es_ES',
 *    'pt_BR', with and without '.utf8') was REJECTED by that runtime, so the
 *    native calls silently kept whatever locale was already active. Resolution
 *    here is therefore by language, never by asking the C library.
 * 3. Spanish and Portuguese rendered %p as an empty string. That is preserved:
 *    a date mask asking for the meridiem in those languages produced nothing.
 * 4. The '.utf8' suffix the non-PARTNER_FLAG branch appends was accepted and
 *    made the CRT answer in UTF-8, while the PARTNER_FLAG branch answered in
 *    codepage 1252 and was lifted by the accepted UTF-8 compatibility
 *    helper added in U-2.3.3. Both branches ended up as UTF-8, so this
 *    helper returns UTF-8 directly and no lift is needed at either call
 *    site. That is also why the migrated line 582 stops calling the UTF-8
 *    helper and why this file must not name it: the consumer ledger counts
 *    raw source mentions, including comments.
 *
 * Specifiers that do not depend on the locale are delegated to
 * LegacyStrftime, which keeps a single implementation of the calendar rules.
 */
final class LegacyLocaleDate
{
    /** Locale-dependent specifiers this helper renders itself. */
    private const LOCALE_DEPENDENT = ['%a', '%A', '%b', '%B', '%p'];

    /**
     * Month names as the acceptance runtime rendered '%B' and '%b'.
     *
     * English is the C locale capture, Spanish is Spanish_Spain and Portuguese
     * is Portuguese_Brazil. Note the trailing dots in the Spanish abbreviations
     * and their absence in the Portuguese ones: that asymmetry is what the
     * runtime produced.
     */
    private const MONTHS = [
        'en' => [
            ['January', 'Jan'], ['February', 'Feb'], ['March', 'Mar'],
            ['April', 'Apr'], ['May', 'May'], ['June', 'Jun'],
            ['July', 'Jul'], ['August', 'Aug'], ['September', 'Sep'],
            ['October', 'Oct'], ['November', 'Nov'], ['December', 'Dec'],
        ],
        'es' => [
            ['enero', 'ene.'], ['febrero', 'feb.'], ['marzo', 'mar.'],
            ['abril', 'abr.'], ['mayo', 'may.'], ['junio', 'jun.'],
            ['julio', 'jul.'], ['agosto', 'ago.'], ['septiembre', 'sep.'],
            ['octubre', 'oct.'], ['noviembre', 'nov.'], ['diciembre', 'dic.'],
        ],
        'pt' => [
            ['janeiro', 'jan'], ['fevereiro', 'fev'], ['março', 'mar'],
            ['abril', 'abr'], ['maio', 'mai'], ['junho', 'jun'],
            ['julho', 'jul'], ['agosto', 'ago'], ['setembro', 'set'],
            ['outubro', 'out'], ['novembro', 'nov'], ['dezembro', 'dez'],
        ],
    ];

    /**
     * Day names as the acceptance runtime rendered '%A' and '%a', indexed the
     * way the C library counts them, Sunday first.
     */
    private const DAYS = [
        'en' => [
            ['Sunday', 'Sun'], ['Monday', 'Mon'], ['Tuesday', 'Tue'],
            ['Wednesday', 'Wed'], ['Thursday', 'Thu'], ['Friday', 'Fri'],
            ['Saturday', 'Sat'],
        ],
        'es' => [
            ['domingo', 'do.'], ['lunes', 'lu.'], ['martes', 'ma.'],
            ['miércoles', 'mi.'], ['jueves', 'ju.'], ['viernes', 'vi.'],
            ['sábado', 'sá.'],
        ],
        'pt' => [
            ['domingo', 'dom'], ['segunda-feira', 'seg'],
            ['terça-feira', 'ter'], ['quarta-feira', 'qua'],
            ['quinta-feira', 'qui'], ['sexta-feira', 'sex'],
            ['sábado', 'sáb'],
        ],
    ];

    /** Meridiem as captured: empty in Spanish and Portuguese, see note 3. */
    private const MERIDIEM = [
        'en' => ['AM', 'PM'],
        'es' => ['', ''],
        'pt' => ['', ''],
    ];

    private function __construct()
    {
    }

    /**
     * Renders a legacy strftime mask in the language of a legacy locale name.
     *
     * @param string   $format    strftime mask, as configured by the user.
     * @param int|null $timestamp Unix timestamp; null means "now".
     * @param string   $locale    Legacy locale name, for example 'EST',
     *                            'ESN', 'PTB', 'pt_BR' or 'es_ES.utf8'.
     *
     * @return string|false UTF-8 output, or false when the mask contains a
     *                      specifier the compatibility layer refuses, exactly
     *                      like the native function on this runtime.
     */
    public static function format(string $format, ?int $timestamp, string $locale)
    {
        $timestamp = $timestamp ?? time();
        $language = self::language($locale);

        $out = '';
        $length = strlen($format);

        for ($i = 0; $i < $length; $i++) {
            if ($format[$i] !== '%' || $i + 1 >= $length) {
                $out .= $format[$i];
                continue;
            }

            $specifier = substr($format, $i, 2);
            $i++;

            if (in_array($specifier, self::LOCALE_DEPENDENT, true)) {
                $out .= self::renderLocaleDependent($specifier, $timestamp, $language);
                continue;
            }

            // Everything else is locale-independent, so the U-2.3.2a helper
            // owns it, including its refusal of unsupported specifiers.
            $rendered = LegacyStrftime::format($specifier, $timestamp);
            if ($rendered === false) {
                return false;
            }

            $out .= $rendered;
        }

        return $out;
    }

    /** Languages this helper carries tables for. */
    public static function supportedLanguages(): array
    {
        return array_keys(self::MONTHS);
    }

    /** Specifiers this helper renders itself instead of delegating. */
    public static function localeDependentSpecifiers(): array
    {
        return self::LOCALE_DEPENDENT;
    }

    /**
     * Maps a legacy locale name onto one of the three languages the product
     * ships. Any unknown name resolves to English, which is what the default
     * arm of the getSystemDate() switch intended.
     */
    public static function language(string $locale): string
    {
        $name = strtolower(trim($locale));

        // Drop the codeset the non-PARTNER_FLAG branch appends, for example
        // 'ESN.utf8', and normalise 'pt-BR' style separators.
        $dot = strpos($name, '.');
        if ($dot !== false) {
            $name = substr($name, 0, $dot);
        }
        $name = str_replace('-', '_', $name);

        if ($name === 'esn' || $name === 'es' || strpos($name, 'es_') === 0) {
            return 'es';
        }

        if ($name === 'ptb' || $name === 'pt' || strpos($name, 'pt_') === 0) {
            return 'pt';
        }

        return 'en';
    }

    private static function renderLocaleDependent(string $specifier, int $timestamp, string $language): string
    {
        // The native function read these fields in PHP's default timezone,
        // which is what LegacyStrftime reports, so the indices come from it.
        switch ($specifier) {
            case '%a':
            case '%A':
                $index = (int) date('w', $timestamp);
                $names = self::DAYS[$language][$index];

                return $specifier === '%A' ? $names[0] : $names[1];
            case '%b':
            case '%B':
                $index = ((int) date('n', $timestamp)) - 1;
                $names = self::MONTHS[$language][$index];

                return $specifier === '%B' ? $names[0] : $names[1];
            case '%p':
                $meridiem = self::MERIDIEM[$language];

                return ((int) date('G', $timestamp)) < 12 ? $meridiem[0] : $meridiem[1];
        }

        // Unreachable: format() checks the specifier before calling this.
        return '';
    }
}
