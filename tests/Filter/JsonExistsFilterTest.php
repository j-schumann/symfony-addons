<?php

/** @noinspection PhpUnhandledExceptionInspection */

namespace Vrok\SymfonyAddons\Tests\Filter;

use ApiPlatform\Metadata\Exception\InvalidArgumentException;
use Monolog\Level;
use Vrok\SymfonyAddons\Filter\JsonExistsFilter;
use Vrok\SymfonyAddons\PHPUnit\ApiPlatformTestCase;
use Vrok\SymfonyAddons\Tests\Helpers\FilterRequestTrait;

final class JsonExistsFilterTest extends ApiPlatformTestCase
{
    use FilterRequestTrait;

    public function testFilter(): void
    {
        $this->skipUnlessPostgres();

        self::assertCount(1, $this->search('?hasKey=ROLE_ADMIN'));
        self::assertCount(1, $this->search('?hasKey=ROLE_ADMIN_BLOG'));
        self::assertCount(0, $this->search('?hasKey=ROLE'));
    }

    public function testFilterMultipleValuesWithAndByDefault(): void
    {
        $this->skipUnlessPostgres();

        self::assertCount(1, $this->search('?hasKey[]=ROLE_ADMIN&hasKey[]=ROLE_EDITOR'));
        self::assertCount(0, $this->search('?hasKey[]=ROLE_ADMIN&hasKey[]=ROLE_ADMIN_BLOG'));

        // duplicates are ignored
        self::assertCount(1, $this->search('?hasKey[]=ROLE_ADMIN&hasKey[]=ROLE_ADMIN'));
    }

    public function testFilterMultipleValuesWithOr(): void
    {
        $this->skipUnlessPostgres();

        self::assertCount(2, $this->search('?hasAnyKey[]=ROLE_ADMIN&hasAnyKey[]=ROLE_ADMIN_BLOG'));
        self::assertCount(1, $this->search('?hasAnyKey[]=ROLE_ADMIN&hasAnyKey[]=ROLE_NONE'));
    }

    public function testFilterIgnoresNestedValues(): void
    {
        $result = $this->search(
            '?hasKey[0][]=ROLE_ADMIN',
            [['Invalid filter ignored', Level::Notice]]
        );

        self::assertCount(2, $result);
    }

    public function testParametersAreDocumented(): void
    {
        $names = $this->getOpenApiParameterNames();

        foreach (['hasAnyKey', 'hasAnyKey[]', 'hasKey', 'hasKey[]'] as $name) {
            self::assertContains($name, $names);
        }
    }

    public function testConstructFailsWithInvalidCombination(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageIsOrContains('Invalid "combination" parameter "xor"');

        new JsonExistsFilter(combination: 'xor');
    }
}
