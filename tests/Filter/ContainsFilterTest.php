<?php

/** @noinspection PhpUnhandledExceptionInspection */

namespace Vrok\SymfonyAddons\Tests\Filter;

use ApiPlatform\Metadata\Exception\InvalidArgumentException;
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
