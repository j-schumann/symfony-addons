<?php

/** @noinspection PhpUnhandledExceptionInspection */

namespace Vrok\SymfonyAddons\Tests\Filter;

use Monolog\Level;

final class SimpleSearchFilterTest extends FilterTestCase
{
    public function testSearch(): void
    {
        self::assertCount(2, $this->search('?pattern=RECORD'));
        self::assertCount(1, $this->search('?pattern=zwei%20text'));
        self::assertCount(1, $this->search('?pattern=eins%20varchar'));
        self::assertCount(0, $this->search('?pattern=drei'));
    }

    public function testSearchWithNestedProperty(): void
    {
        self::assertCount(2, $this->search('?childName=child'));
        self::assertCount(1, $this->search('?childName=zwei'));
    }

    public function testSearchInJsonColumn(): void
    {
        self::assertCount(2, $this->search('?jsonSearch=role_admin'));
        self::assertCount(1, $this->search('?jsonSearch=ADMIN_BLOG'));
    }

    public function testSearchInNestedJsonColumn(): void
    {
        self::assertCount(2, $this->search('?childJsonSearch=child_'));
        self::assertCount(1, $this->search('?childJsonSearch=CHILD_EINS'));
    }

    public function testSearchSkipsUnmappedProperty(): void
    {
        self::assertCount(1, $this->search('?searchUnmapped=eins'));
    }

    public function testSearchIgnoresArrayValue(): void
    {
        $result = $this->search(
            '?pattern[]=eins',
            [['Invalid filter ignored', Level::Notice]]
        );

        self::assertCount(2, $result);
    }

    public function testParametersAreDocumented(): void
    {
        $parameters = $this->getOpenApiParameters();

        foreach (['childName', 'pattern'] as $name) {
            self::assertArrayHasKey($name, $parameters);
        }

        self::assertSame('Search in the text and varchar column', $parameters['pattern']['description']);
    }
}
