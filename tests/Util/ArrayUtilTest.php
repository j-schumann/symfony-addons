<?php

declare(strict_types=1);

namespace Vrok\SymfonyAddons\Tests\Util;

use PHPUnit\Framework\TestCase;
use Vrok\SymfonyAddons\Util\ArrayUtil;

class ArrayUtilTest extends TestCase
{
    protected const A = ['a', 'b', 's' => ['d', 'e']];
    protected const B = ['b', 'c', 's' => ['e', 'f']];
    protected const C = [1 => '1', 'k' => 'v', 'a' => ['g']];

    // We cannot / don't want to use assertSame or assertArraySubset
    // as the indexes change and we don't care about them (except for nested
    // arrays).
    // So we just test that our expected values exist and no more entries
    // than we want.

    public function testMergeValuesKeepsAllValues(): void
    {
        $result = ArrayUtil::mergeValues(self::A, self::B);

        self::assertCount(4, $result);

        foreach (['a', 'b', 'c'] as $value) {
            self::assertContains($value, $result);
        }

        self::assertArrayHasKey('s', $result);
        self::assertCount(3, $result['s']);

        foreach (['d', 'e', 'f'] as $value) {
            self::assertContains($value, $result['s']);
        }
    }

    public function testMergeValuesWith3Arrays(): void
    {
        $result = ArrayUtil::mergeValues(self::A, self::B, self::C);

        self::assertCount(7, $result);

        foreach (['a', 'b', 'c', '1', 'v'] as $value) {
            self::assertContains($value, $result);
        }

        self::assertArrayHasKey('s', $result);
        self::assertCount(3, $result['s']);

        foreach (['d', 'e', 'f'] as $value) {
            self::assertContains($value, $result['s']);
        }

        self::assertArrayHasKey('a', $result);
        self::assertCount(1, $result['a']);
        self::assertContains('g', $result['a']);
    }

    public function testMergeValuesExpectsArrayAsFirstParam(): void
    {
        $this->expectException(\TypeError::class);
        ArrayUtil::mergeValues('a', self::B, self::C);
    }

    public function testMergeValuesExpectsArrayAsSecondParam(): void
    {
        $this->expectException(\TypeError::class);
        ArrayUtil::mergeValues(self::B, 'a', self::C);
    }
}
