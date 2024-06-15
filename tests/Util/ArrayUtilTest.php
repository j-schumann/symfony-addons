<?php

declare(strict_types=1);

namespace Vrok\SymfonyAddons\Tests\Util;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Vrok\SymfonyAddons\Util\ArrayUtil;

/**
 * @group ArrayUtil
 */
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

    public function testMergeValuesWithEqualNestedArraysOnDifferentKeys()
    {
        $result = ArrayUtil::mergeValues(
            ['key1' => self::A],
            [0 => self::A]
        );

        foreach (['key1', 0] as $key) {
            self::assertArrayHasKey($key, $result);
            self::assertSame(self::A, $result[$key]);
        }

        $result2 = ArrayUtil::mergeValues(
            ['key1' => ['a' => self::A]],
            ['key1' => ['a' => self::A, 'b' => self::A]],
        );

        self::assertArrayHasKey('key1', $result2);
        self::assertArrayHasKey('a', $result2['key1']);
        self::assertSame(self::A, $result2['key1']['a']);
        self::assertArrayHasKey('b', $result2['key1']);
        self::assertSame(self::A, $result2['key1']['b']);
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

    #[DataProvider('provideNonDuplicates')]
    public function testHasDuplicatesReturnsFalseCorrectly($value): void
    {
        self::assertFalse(ArrayUtil::hasDuplicates($value));
    }

    #[DataProvider('provideDuplicates')]
    public function testHasDuplicatesDetectsDuplicatesCorrectly($value): void
    {
        self::assertTrue(ArrayUtil::hasDuplicates($value));
    }

    public function testHasDuplicatesExpectsArrayAsFirstParam(): void
    {
        $this->expectException(\TypeError::class);
        ArrayUtil::hasDuplicates('a');
    }

    public static function provideNonDuplicates(): array
    {
        return [
            [['a', 1, 'b']],
            [[new \DateTimeImmutable(), new \DateTimeImmutable()]],
            [[['a'], ['b']]],
            [[[1 => 'a'], [2 => 'a']]],
        ];
    }

    public static function provideDuplicates(): array
    {
        $dt = new \DateTimeImmutable();

        return [
            [['a', 1, 'a']],
            [[1, 'a', 1]],
            [[['a'], ['a']]],
            [[$dt, 'a', $dt]],

            // @todo ambiguous, with strict types this should *not* be detected
            // as duplicates but SORT_REGULAR reports it
            [['a', 1, '1']],
        ];
    }
}
