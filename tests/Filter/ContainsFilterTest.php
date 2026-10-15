<?php

/** @noinspection PhpUnhandledExceptionInspection */

namespace Vrok\SymfonyAddons\Tests\Filter;

use ApiPlatform\Metadata\Exception\InvalidArgumentException;
use Monolog\Level;
use PHPUnit\Framework\Attributes\DataProvider;
use Vrok\SymfonyAddons\Filter\ContainsFilter;

final class ContainsFilterTest extends FilterTestCase
{
    public function testFilter(): void
    {
        $this->skipUnlessPostgres();

        self::assertCount(1, $this->search('?contains=5'));
        self::assertCount(0, $this->search('?contains=3'));
    }

    public function testFilterWithString(): void
    {
        $this->skipUnlessPostgres();

        self::assertCount(1, $this->search('?contains=ROLE_ADMIN'));
        self::assertCount(1, $this->search('?contains=%22ROLE_ADMIN%22'));
        self::assertCount(0, $this->search('?contains=ROLE'));
        self::assertCount(0, $this->search('?contains='));
    }

    public function testFilterMultipleValuesWithAndByDefault(): void
    {
        $this->skipUnlessPostgres();

        self::assertCount(1, $this->search('?contains[]=1&contains[]=5'));
        self::assertCount(0, $this->search('?contains[]=2&contains[]=5'));
    }

    public function testFilterMultipleValuesWithOr(): void
    {
        $this->skipUnlessPostgres();

        self::assertCount(2, $this->search('?containsAny[]=2&containsAny[]=5'));
        self::assertCount(1, $this->search('?containsAny[]=ROLE_ADMIN&containsAny[]=3'));
        self::assertCount(0, $this->search('?containsAny[]=3&containsAny[]=4'));
    }

    public function testFilterNestedProperty(): void
    {
        $this->skipUnlessPostgres();

        self::assertCount(1, $this->search('?childContains=CHILD_EINS'));
        self::assertCount(0, $this->search('?childContains=CHILD'));
    }

    public function testFilterWithOrFilter(): void
    {
        $this->skipUnlessPostgres();

        self::assertCount(2, $this->search('?contains=5&orContains=2'));
    }

    public function testFilterWithEscapedNullCharacter(): void
    {
        $this->skipUnlessPostgres();

        self::assertCount(0, $this->search('?contains=%22%5C%5Cu0000%22'));
    }

    public function testFilterWithValidExponent(): void
    {
        $this->skipUnlessPostgres();

        self::assertCount(0, $this->search('?contains=%229e12345%22'));
        self::assertCount(0, $this->search('?contains=1e400'));
    }

    /**
     * @return array<string, array{string}>
     */
    public static function invalidValueProvider(): array
    {
        return [
            'invalid UTF-8'        => ['?contains=%FF'],
            'null character'       => ['?contains=%00'],
            'escaped null in JSON' => ['?contains=%22%5Cu0000%22'],
            'number too large'     => ['?contains=1e131072'],
            'number too small'     => ['?contains=1e-16384'],
            'nested number'        => ['?contains=%5B1e-1000000%5D'],
            'one invalid value'    => ['?contains[]=5&contains[]=%FF'],
        ];
    }

    #[DataProvider('invalidValueProvider')]
    public function testFilterIgnoresInvalidValue(string $query): void
    {
        $result = $this->search($query, [['Invalid filter ignored', Level::Notice]]);

        self::assertCount(2, $result);
    }

    public function testFilterIgnoresUnmappedProperty(): void
    {
        self::assertCount(2, $this->search('?containsUnmapped=5'));
    }

    public function testParametersAreDocumented(): void
    {
        $parameters = $this->getOpenApiParameters();

        foreach (['contains', 'contains[]', 'containsAny', 'containsAny[]'] as $name) {
            self::assertArrayHasKey($name, $parameters);
        }
    }

    public function testConstructFailsWithInvalidCombination(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageIsOrContains('Invalid "combination" parameter "xor"');

        new ContainsFilter(combination: 'xor');
    }
}
