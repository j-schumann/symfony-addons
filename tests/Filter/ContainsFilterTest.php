<?php

/** @noinspection PhpUnhandledExceptionInspection */

namespace Vrok\SymfonyAddons\Tests\Filter;

use Vrok\SymfonyAddons\PHPUnit\ApiPlatformTestCase;
use Vrok\SymfonyAddons\Tests\Helpers\FilterRequestTrait;

final class ContainsFilterTest extends ApiPlatformTestCase
{
    use FilterRequestTrait;

    public function testFilter(): void
    {
        $this->skipUnlessPostgres();

        self::assertCount(1, $this->search('?contains=5'));
        self::assertCount(0, $this->search('?contains=3'));
    }

    public function testFilterMultipleValues(): void
    {
        $this->skipUnlessPostgres();

        // records must contain all values
        self::assertCount(1, $this->search('?contains[]=1&contains[]=5'));
        self::assertCount(0, $this->search('?contains[]=2&contains[]=5'));
    }

    public function testFilterNestedProperty(): void
    {
        $this->skipUnlessPostgres();

        self::assertCount(1, $this->search('?childContains=%22CHILD_EINS%22'));
        self::assertCount(0, $this->search('?childContains=%22CHILD%22'));
    }

    public function testFilterIgnoresUnmappedProperty(): void
    {
        self::assertCount(2, $this->search('?containsUnmapped=5'));
    }

    public function testParametersAreDocumented(): void
    {
        $names = $this->getOpenApiParameterNames();

        foreach (['contains', 'contains[]'] as $name) {
            self::assertContains($name, $names);
        }
    }
}
