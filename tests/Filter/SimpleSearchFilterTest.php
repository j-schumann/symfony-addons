<?php

/** @noinspection PhpUnhandledExceptionInspection */

namespace Vrok\SymfonyAddons\Tests\Filter;

use Monolog\Level;
use Vrok\SymfonyAddons\PHPUnit\ApiPlatformTestCase;
use Vrok\SymfonyAddons\Tests\Helpers\FilterRequestTrait;

final class SimpleSearchFilterTest extends ApiPlatformTestCase
{
    use FilterRequestTrait;

    public function testSearch(): void
    {
        self::assertCount(2, $this->search('?pattern=RECORD'));
        self::assertCount(1, $this->search('?pattern=zwei%20text'));
        self::assertCount(1, $this->search('?pattern=eins%20varchar'));
        self::assertCount(0, $this->search('?pattern=drei'));
    }

    public function testSearchWithFilterProperties(): void
    {
        self::assertCount(1, $this->search('?search=eins%20varchar'));

        // textColumn is not searched
        self::assertCount(0, $this->search('?search=eins%20text'));
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
        $names = $this->getOpenApiParameterNames();

        foreach (['childName', 'pattern', 'search'] as $name) {
            self::assertContains($name, $names);
        }
    }
}
