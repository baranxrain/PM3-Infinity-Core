<?php

declare(strict_types=1);

namespace Tests\Unit\Util;

use PHPUnit\Framework\TestCase;
use ProcessMaker\Util\ArrayUtil;

require_once PM_TEST_ROOT . '/workflow/engine/src/ProcessMaker/Util/ArrayUtil.php';

final class ArrayUtilBehaviorTest extends TestCase
{
    public function testBoolToIntValuesPreservesTheLegacyTopLevelContract(): void
    {
        $input = [false, true, null, 0, 1, 'false', ['nested' => true]];

        self::assertSame([0, 1, 0, 0, 1, 'false', ['nested' => true]], ArrayUtil::boolToIntValues($input));
    }

    public function testSortUsesMultipleColumnsAndDirections(): void
    {
        $rows = [
            ['id' => 'a', 'volume' => 67, 'edition' => 2],
            ['id' => 'b', 'volume' => 86, 'edition' => 6],
            ['id' => 'c', 'volume' => 86, 'edition' => 1],
            ['id' => 'd', 'volume' => 98, 'edition' => 2],
        ];

        $sorted = ArrayUtil::sort($rows, ['volume', 'edition'], [SORT_DESC, SORT_ASC]);

        self::assertSame(['d', 'c', 'b', 'a'], array_column($sorted, 'id'));
    }

    public function testSortKeepsTheLegacyInvalidDirectionResultAndMessage(): void
    {
        self::expectOutputRegex('/Argument \\(array\\)#2 and Argument \\(array\\)#3 lengths must be equals/');

        self::assertFalse(ArrayUtil::sort([['value' => 1]], ['value'], [SORT_ASC, SORT_DESC]));
    }

    public function testSortReturnsAnEmptyArrayWithoutSideEffects(): void
    {
        self::assertSame([], ArrayUtil::sort([], ['value']));
    }
}
