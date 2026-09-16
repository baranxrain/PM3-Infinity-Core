<?php

namespace Tests\Unit\Compatibility;

use PHPUnit\Framework\TestCase;
use ProcessMaker\Util\LegacyStrftime;
use ReflectionClass;
use Tests\Support\CompatibilityLedger;
use Tests\Support\PhpSourceScanner;

require_once PM_TEST_ROOT . '/workflow/engine/src/ProcessMaker/Util/LegacyStrftime.php';

/**
 * U-2.3.2a: the LegacyStrftime helper must reproduce, byte for byte, the
 * native strftime() output recorded on the acceptance runtime, and must not
 * be wired into any call site yet.
 */
class LegacyStrftimeContractTest extends TestCase
{
    private const HELPER = 'workflow/engine/src/ProcessMaker/Util/LegacyStrftime.php';

    /** @var array<string, mixed> */
    private static array $contract;

    public static function setUpBeforeClass(): void
    {
        self::$contract = self::loadContract();
    }

    /** @return array<string, mixed> */
    private static function loadContract(): array
    {
        $path = PM_TEST_ROOT . '/tests/fixtures/legacy-strftime-contract.json';
        $raw = file_get_contents($path);
        if ($raw === false) {
            throw new \RuntimeException('Missing contract fixture: ' . $path);
        }

        return json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
    }

    public function testHelperExistsAndIsAFinalStaticUtility(): void
    {
        $this->assertFileExists(PM_TEST_ROOT . '/' . self::HELPER);
        $this->assertTrue(class_exists(LegacyStrftime::class), 'Helper must declare ProcessMaker\\Util\\LegacyStrftime at its psr-0 path.');

        $class = new ReflectionClass(LegacyStrftime::class);
        $this->assertTrue($class->isFinal(), 'The helper must be final.');

        foreach ($class->getMethods() as $method) {
            if ($method->isConstructor()) {
                $this->assertTrue($method->isPrivate(), 'The constructor must stay private.');
                continue;
            }

            $this->assertTrue($method->isStatic(), $method->getName() . '() must be static.');
        }
    }

    public function testHelperDoesNotDelegateToTheDeprecatedFunction(): void
    {
        $code = PhpSourceScanner::codeOnlyFile(PM_TEST_ROOT . '/' . self::HELPER);

        $this->assertSame(0, PhpSourceScanner::matchCount($code, '(?<![\w$>-])(?:strftime|gmstrftime)\s*\('), 'The helper must not call the deprecated function.');
        $this->assertSame(0, PhpSourceScanner::matchCount($code, '(?<![\w$>-])(?:setlocale|IntlDateFormatter)'), 'The helper must be locale independent.');
    }

    /**
     * @dataProvider specifierCases
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('specifierCases')]
    public function testSpecifierMatchesTheRecordedNativeOutput(string $specifier, int $timestamp, string $expected): void
    {
        $previous = date_default_timezone_get();

        try {
            date_default_timezone_set('UTC');
            $this->assertSame($expected, LegacyStrftime::format($specifier, $timestamp), 'Specifier ' . $specifier . ' must match the oracle.');
        } finally {
            date_default_timezone_set($previous);
        }
    }

    /** @return iterable<string, array{string, int, string}> */
    public static function specifierCases(): iterable
    {
        $contract = self::loadContract();

        foreach ($contract['specifiers'] as $specifier => $rows) {
            foreach ($rows as $stamp => $expected) {
                yield $specifier . ' @ ' . $stamp => [$specifier, $contract['timestamps'][$stamp], $expected];
            }
        }
    }

    /**
     * @dataProvider maskCases
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('maskCases')]
    public function testRealMaskMatchesTheRecordedNativeOutput(string $mask, int $timestamp, string $expected): void
    {
        $previous = date_default_timezone_get();

        try {
            date_default_timezone_set('UTC');
            $this->assertSame($expected, LegacyStrftime::format($mask, $timestamp), 'Mask ' . $mask . ' must match the oracle.');
        } finally {
            date_default_timezone_set($previous);
        }
    }

    /** @return iterable<string, array{string, int, string}> */
    public static function maskCases(): iterable
    {
        $contract = self::loadContract();

        foreach ($contract['masks'] as $mask => $rows) {
            foreach ($rows as $stamp => $expected) {
                yield $mask . ' @ ' . $stamp => [$mask, $contract['timestamps'][$stamp], $expected];
            }
        }
    }

    public function testRefusedSpecifiersReturnFalseInsteadOfAGuess(): void
    {
        $refused = self::$contract['refusedSpecifiers'];
        $this->assertSame(['%k', '%l', '%P', '%s', '%z', '%Z'], $refused);
        $this->assertSame($refused, LegacyStrftime::refusedSpecifiers());

        foreach ($refused as $specifier) {
            $this->assertFalse(LegacyStrftime::format($specifier, 0), $specifier . ' must be refused.');
            $this->assertFalse(LegacyStrftime::format('%Y-%m-%d ' . $specifier, 0), 'A mask containing ' . $specifier . ' must be refused as a whole.');
            $this->assertFalse(LegacyStrftime::supports($specifier));
        }
    }

    public function testTheFourNativeFalseSpecifiersComeFromTheOracleNotFromAGuess(): void
    {
        $this->assertSame(['%k', '%l', '%P', '%s'], self::$contract['nativeFalseSpecifiers'], 'Native strftime() returned false for exactly these on the acceptance runtime.');

        foreach (self::$contract['nativeFalseSpecifiers'] as $specifier) {
            $this->assertContains($specifier, LegacyStrftime::refusedSpecifiers());
        }
    }

    public function testUnknownSpecifiersAreRefusedAndSupportedListIsExhaustive(): void
    {
        foreach (['%Q', '%o', '%J', '%1'] as $unknown) {
            $this->assertFalse(LegacyStrftime::format($unknown, 0), $unknown . ' is unknown and must be refused.');
        }

        $supported = LegacyStrftime::supportedSpecifiers();
        $proven = array_keys(self::$contract['specifiers']);
        sort($supported);
        sort($proven);
        $this->assertSame($proven, $supported, 'The advertised specifier list must be exactly what the oracle proves.');

        foreach ($proven as $specifier) {
            $this->assertTrue(LegacyStrftime::supports($specifier), $specifier . ' is proven by the oracle and must render.');
        }
    }

    public function testHelperFollowsThePhpDefaultTimezoneLikeTheNativeFunction(): void
    {
        $timestamp = 1119873600; // 2005-06-27 12:00:00 UTC
        $previous = date_default_timezone_get();

        try {
            date_default_timezone_set('UTC');
            $this->assertSame('12', LegacyStrftime::format('%H', $timestamp));
            $this->assertSame('2005-06-27', LegacyStrftime::format('%Y-%m-%d', $timestamp));

            date_default_timezone_set('Asia/Tokyo');
            $this->assertSame('21', LegacyStrftime::format('%H', $timestamp));
            $this->assertSame('2005-06-27', LegacyStrftime::format('%Y-%m-%d', $timestamp));

            date_default_timezone_set('America/Los_Angeles');
            $this->assertSame('05', LegacyStrftime::format('%H', $timestamp));
            $this->assertSame(date('H', $timestamp), LegacyStrftime::format('%H', $timestamp));
        } finally {
            date_default_timezone_set($previous);
        }
    }

    public function testOutputIsLocaleIndependent(): void
    {
        $timestamp = self::$contract['timestamps']['summer_noon'];
        $expected = self::$contract['specifiers']['%A']['summer_noon'];
        $previousLocale = setlocale(LC_TIME, '0');
        $previousZone = date_default_timezone_get();

        try {
            date_default_timezone_set('UTC');

            foreach (['de_DE', 'de_DE.UTF-8', 'German_Germany.1252', 'fa_IR', 'C'] as $candidate) {
                setlocale(LC_TIME, $candidate);
                $this->assertSame($expected, LegacyStrftime::format('%A', $timestamp), 'Output must not depend on LC_TIME (' . $candidate . ').');
            }
        } finally {
            date_default_timezone_set($previousZone);
            if (is_string($previousLocale)) {
                setlocale(LC_TIME, $previousLocale);
            }
        }
    }

    public function testContractIsPinnedToTheAcceptedOracleFile(): void
    {
        $oracle = PM_TEST_ROOT . '/tests/fixtures/legacy-strftime-oracle.json';
        $this->assertFileExists($oracle);
        $this->assertSame(self::$contract['oracle']['sha256'], hash_file('sha256', $oracle), 'The contract must describe the committed oracle file.');

        $runtime = self::$contract['oracle']['runtime'];
        $this->assertSame('8.1.10', $runtime['phpVersion']);
        $this->assertSame('Windows', $runtime['phpOs']);
        $this->assertSame('C', $runtime['lcTime']);
    }

    public function testTheMigratedCallSitesConsumeTheHelper(): void
    {
        $budget = json_decode((string) file_get_contents(PM_TEST_ROOT . '/tests/fixtures/deprecation-budget.json'), true, 512, JSON_THROW_ON_ERROR);
        $counts = CompatibilityLedger::counts($budget['scope'], ['strftime' => '(?<![\w$>-])(?:strftime|gmstrftime)\s*\(']);

        $this->assertSame(140, $counts['strftime']['textual'], 'U-2.3.2 lowered the textual strftime ledger to 140.');
        $this->assertSame(0, $counts['strftime']['code'], 'U-2.4.2 migrated the last 2 sites, so nothing may call the deprecated function.');

        $this->assertSame(140, $budget['budgets']['strftime']);
        $this->assertSame(0, $budget['codeBudgets']['strftime']);

        $consumers = CompatibilityLedger::counts($budget['scope'], ['helper' => '(?<![\w$>-])LegacyStrftime::'])['helper'];
        $this->assertSame(137, $consumers['code'], 'The 136 migrated getters plus LegacyLocaleDate may consume LegacyStrftime.');
        $this->assertSame(self::$contract['helper']['consumersExpected'], $consumers['code'] - 1, 'The accepted contract counts the 136 getters; U-2.4.2 adds one delegation from LegacyLocaleDate.');
    }

    public function testInventoryReflectsTheMigration(): void
    {
        $inventory = json_decode((string) file_get_contents(PM_TEST_ROOT . '/tests/fixtures/strftime-inventory.json'), true, 512, JSON_THROW_ON_ERROR);

        $this->assertSame(0, $inventory['summary']['executable']);
        $this->assertSame(0, $inventory['summary']['executableFiles']);
        $this->assertSame(0, $inventory['categories']['generated_propel_getter']['count']);
        $this->assertSame(0, $inventory['categories']['handwritten_locale_date']['count']);
        $this->assertSame(0, $inventory['summary']['gmstrftimeTextual']);
    }
}
