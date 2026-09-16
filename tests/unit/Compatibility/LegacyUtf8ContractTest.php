<?php

declare(strict_types=1);

namespace Tests\Unit\Compatibility;

use PHPUnit\Framework\TestCase;
use Tests\Support\LegacyUtf8Oracle;
use ProcessMaker\Util\LegacyUtf8;

require_once PM_TEST_ROOT . '/workflow/engine/src/ProcessMaker/Util/LegacyUtf8.php';

final class LegacyUtf8ContractTest extends TestCase
{
    public function testFixtureCoversEverySingleByteExactlyOnce(): void
    {
        $cases = self::fixture()['singleByteEncodeCases'];
        self::assertCount(256, $cases);

        $actualInputs = array_column($cases, 'inputHex');
        $expectedInputs = array_map(
            static fn (int $byte): string => sprintf('%02x', $byte),
            range(0, 255)
        );

        self::assertSame($expectedInputs, $actualInputs);
        self::assertCount(256, array_unique($actualInputs));
    }

    public function testHelperEncodeMatchesAllFrozenSingleByteCases(): void
    {
        foreach (self::fixture()['singleByteEncodeCases'] as $case) {
            self::assertSame(
                self::bytes($case['expectedHex']),
                LegacyUtf8::encode(self::bytes($case['inputHex'])),
                'Encode contract mismatch for byte 0x' . strtoupper($case['inputHex'])
            );
        }
    }

    public function testHelperDecodeMatchesFrozenCuratedCorpus(): void
    {
        foreach (self::fixture()['decodeCases'] as $case) {
            self::assertSame(
                self::bytes($case['expectedHex']),
                LegacyUtf8::decode(self::bytes($case['inputHex'])),
                'Decode contract mismatch for ' . $case['name']
            );
        }
    }

    public function testHelperAndFixtureMatchFrozenLegacyOracle(): void
    {
        self::assertGreaterThanOrEqual(80100, PHP_VERSION_ID);
        self::assertLessThan(80300, PHP_VERSION_ID);
        self::assertTrue(is_callable([LegacyUtf8Oracle::class, 'encode']));
        self::assertTrue(is_callable([LegacyUtf8Oracle::class, 'decode']));

        foreach (self::fixture()['singleByteEncodeCases'] as $case) {
            $input = self::bytes($case['inputHex']);
            $expected = self::bytes($case['expectedHex']);
            self::assertSame($expected, LegacyUtf8Oracle::encode($input), 'Frozen encode oracle mismatch for 0x' . $case['inputHex']);
            self::assertSame(LegacyUtf8Oracle::encode($input), LegacyUtf8::encode($input), 'Helper encode oracle mismatch for 0x' . $case['inputHex']);
        }

        foreach (self::fixture()['decodeCases'] as $case) {
            $input = self::bytes($case['inputHex']);
            $expected = self::bytes($case['expectedHex']);
            self::assertSame($expected, LegacyUtf8Oracle::decode($input), 'Frozen decode oracle mismatch for ' . $case['name']);
            self::assertSame(LegacyUtf8Oracle::decode($input), LegacyUtf8::decode($input), 'Helper decode oracle mismatch for ' . $case['name']);
        }
    }

    public function testEncodeDecodeRoundTripPreservesEveryIso88591Byte(): void
    {
        $allBytes = '';
        foreach (range(0, 255) as $byte) {
            $allBytes .= chr($byte);
        }

        self::assertSame($allBytes, LegacyUtf8::decode(LegacyUtf8::encode($allBytes)));
    }

    public function testConversionsLeaveMbstringSubstitutionStateUntouched(): void
    {
        $before = mb_substitute_character();

        LegacyUtf8::encode(self::bytes('00417fff80ff'));
        LegacyUtf8::decode(self::bytes('f09f9982e282acc241'));

        self::assertSame($before, mb_substitute_character());
    }

    private static function fixture(): array
    {
        $contents = file_get_contents(PM_TEST_ROOT . '/tests/fixtures/legacy-utf8-contract.json');
        self::assertNotFalse($contents);

        return json_decode($contents, true, 512, JSON_THROW_ON_ERROR);
    }

    private static function bytes(string $hex): string
    {
        if ($hex === '') {
            return '';
        }

        $bytes = hex2bin($hex);
        self::assertNotFalse($bytes);

        return $bytes;
    }
}
