<?php

/** @noinspection PhpUnhandledExceptionInspection */

namespace Vrok\SymfonyAddons\Tests\Filter;

use ApiPlatform\Metadata\QueryParameter;
use ApiPlatform\OpenApi\Model\Parameter;
use Monolog\Level;
use PHPUnit\Framework\Attributes\DataProvider;
use Vrok\SymfonyAddons\Filter\SimpleSearchFilter;
use Vrok\SymfonyAddons\Tests\Fixtures\Entity\TestEntity;
use Vrok\SymfonyAddons\Tests\Fixtures\TestFixtures;

final class SimpleSearchFilterTest extends FilterTestCase
{
    // region Searching
    public function testSearch(): void
    {
        self::assertCount(2, $this->search('?pattern=RECORD'));
        self::assertCount(1, $this->search('?pattern=zwei%20text'));
        self::assertCount(1, $this->search('?pattern=eins%20varchar'));
        self::assertCount(0, $this->search('?pattern=drei'));
    }

    public function testSearchWithUppercaseUmlaut(): void
    {
        $this->setTextOfRecordZwei('record ZWEI Übung');

        self::assertCount(1, $this->search('?pattern=%C3%9CBUNG'));
    }

    public function testSearchWithWildcardCharacters(): void
    {
        self::assertCount(0, $this->search('?pattern=_'));
        self::assertCount(0, $this->search('?pattern=%25'));
        self::assertCount(0, $this->search('?pattern=!'));
        self::assertCount(1, $this->search('?jsonSearch=admin_blog'));

        $this->setTextOfRecordZwei('record ZWEI 50% ! [x]');

        self::assertCount(1, $this->search('?pattern=%25'));
        self::assertCount(1, $this->search('?pattern=!'));
        self::assertCount(0, $this->search('?pattern=_'));

        // character class on SQL Server
        self::assertCount(1, $this->search('?pattern=[x]'));
        self::assertCount(0, $this->search('?pattern=[a-z]'));
    }

    public function testSearchWithDashInParameterKey(): void
    {
        self::assertCount(1, $this->search('?search-term=eins'));
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

    public function testSearchWithOtherParameter(): void
    {
        self::assertCount(0, $this->search('?pattern=eins&childName=zwei'));
    }

    public function testSearchWithOrFilter(): void
    {
        self::assertCount(2, $this->search('?pattern=eins&orPattern=zwei'));
    }
    // endregion

    // region Ignored values
    public function testSearchWithUnmappedProperty(): void
    {
        self::assertCount(1, $this->search('?searchUnmapped=eins'));
    }

    public function testSearchWithOnlyUnmappedProperties(): void
    {
        self::assertCount(2, $this->search('?onlyUnmapped=eins'));
    }

    /**
     * @return array<string, array{string}>
     */
    public static function invalidValueProvider(): array
    {
        return [
            'array'          => ['?pattern[]=eins'],
            'invalid UTF-8'  => ['?pattern=%FF'],
            'null character' => ['?pattern=%00'],
        ];
    }

    #[DataProvider('invalidValueProvider')]
    public function testSearchWithInvalidValue(string $query): void
    {
        $result = $this->search($query, [['Invalid filter ignored', Level::Notice]]);

        self::assertCount(2, $result);
    }
    // endregion

    // region OpenAPI documentation
    public function testParametersAreDocumented(): void
    {
        $parameters = $this->getOpenApiParameters();

        foreach (['childName', 'pattern'] as $name) {
            self::assertArrayHasKey($name, $parameters);
        }

        self::assertSame(
            'Search in the text and varchar column',
            $parameters['pattern']['description']
        );
    }

    public function testGetOpenApiParameters(): void
    {
        $parameter = new SimpleSearchFilter()->getOpenApiParameters(new QueryParameter(key: 'q'));

        self::assertInstanceOf(Parameter::class, $parameter);
        self::assertSame('q', $parameter->getName());
        self::assertSame('query', $parameter->getIn());
    }
    // endregion

    private function setTextOfRecordZwei(string $text): void
    {
        self::getContainer()->get('doctrine')->getManager()
            ->createQuery(\sprintf(
                'UPDATE %s e SET e.textColumn = :text WHERE e.varcharColumn = :varchar',
                TestEntity::class,
            ))
            ->execute([
                'text'    => $text,
                'varchar' => TestFixtures::RECORDS['zwei']['varcharColumn'],
            ]);
    }
}
