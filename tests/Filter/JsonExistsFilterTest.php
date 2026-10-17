<?php

/** @noinspection PhpUnhandledExceptionInspection */

namespace Vrok\SymfonyAddons\Tests\Filter;

use ApiPlatform\Metadata\Exception\InvalidArgumentException;
use Monolog\Level;
use PHPUnit\Framework\Attributes\DataProvider;
use Vrok\SymfonyAddons\Filter\JsonExistsFilter;

final class JsonExistsFilterTest extends FilterTestCase
{
    // region Filtering
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

    public function testFilterWithNestedProperty(): void
    {
        $this->skipUnlessPostgres();

        self::assertCount(1, $this->search('?childHasKey=CHILD_ZWEI'));
        self::assertCount(0, $this->search('?childHasKey=CHILD'));
    }

    public function testFilterWithOrFilter(): void
    {
        $this->skipUnlessPostgres();

        self::assertCount(2, $this->search('?hasKey=ROLE_EDITOR&orHasKey=ROLE_ADMIN_BLOG'));
    }

    public function testFilterWithOtherParameter(): void
    {
        $this->skipUnlessPostgres();

        self::assertCount(0, $this->search('?pattern=zwei&hasKey=ROLE_EDITOR'));
    }
    // endregion

    // region Ignored values
    /**
     * @return array<string, array{string}>
     */
    public static function invalidValueProvider(): array
    {
        return [
            'invalid UTF-8'  => ['?hasKey=%FF'],
            'nested values'  => ['?hasKey[0][]=ROLE_ADMIN'],
            'null character' => ['?hasKey[]=ROLE_ADMIN&hasKey[]=%00'],
        ];
    }

    #[DataProvider('invalidValueProvider')]
    public function testFilterWithInvalidValue(string $query): void
    {
        $result = $this->search($query, [['Invalid filter ignored', Level::Notice]]);

        self::assertCount(2, $result);
    }
    // endregion

    // region Filter definition
    public function testParametersAreDocumented(): void
    {
        $parameters = $this->getOpenApiParameters();

        foreach (['hasAnyKey', 'hasAnyKey[]', 'hasKey', 'hasKey[]'] as $name) {
            self::assertArrayHasKey($name, $parameters);
        }
    }

    public function testConstructFailsWithInvalidCombination(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageIsOrContains('Invalid "combination" parameter "xor"');

        new JsonExistsFilter(combination: 'xor');
    }
    // endregion
}
