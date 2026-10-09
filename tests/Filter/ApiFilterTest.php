<?php

/** @noinspection PhpUnhandledExceptionInspection */

namespace Vrok\SymfonyAddons\Tests\Filter;

use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\IgnoreDeprecations;
use Vrok\SymfonyAddons\Tests\Fixtures\ApiFilter\ApiFilterEntity;

/**
 * Uses the filters via the legacy #[ApiFilter] attribute through the API
 * Platform request handling, see the ApiFilterEntity.
 *
 * API Platform >= 4.4 deprecates the attribute when it builds the container
 * and the metadata of the ApiFilterEntity, this is expected.
 *
 * @todo remove when support for #[ApiFilter] is dropped (API Platform 6)
 */
#[Group('database')]
#[IgnoreDeprecations('with the "#\[ApiFilter\]" attribute is deprecated|through "Operation::\$filters" is deprecated')]
final class ApiFilterTest extends FilterRequestTestCase
{
    protected function getKernelEnvironment(): string
    {
        return 'test_api_filter';
    }

    protected function getEntityClasses(): array
    {
        return [ApiFilterEntity::class];
    }

    protected function createRecords(EntityManagerInterface $em): void
    {
        $parent = new ApiFilterEntity();
        $parent->varcharColumn = 'parent';
        $em->persist($parent);

        $rec1 = new ApiFilterEntity();
        $rec1->textColumn = 'record EINS text';
        $rec1->varcharColumn = 'record EINS varchar';
        $rec1->numbers = [1, 5];
        $rec1->roles = ['ROLE_ADMIN'];
        $rec1->parent = $parent;
        $em->persist($rec1);

        $rec2 = new ApiFilterEntity();
        $rec2->textColumn = 'record ZWEI text';
        $rec2->varcharColumn = 'record ZWEI varchar';
        $rec2->numbers = [2];
        $rec2->roles = ['ROLE_ADMIN_BLOG'];
        $em->persist($rec2);
    }

    public function testSimpleSearchFilter(): void
    {
        self::assertCount(2, $this->search('?pattern=RECORD'));
        self::assertCount(1, $this->search('?pattern=zwei%20text'));
        self::assertCount(1, $this->search('?pattern=eins%20varchar'));
        self::assertCount(0, $this->search('?pattern=drei'));
    }

    public function testSimpleSearchWithNestedProperty(): void
    {
        // the parent itself and the record that references it
        self::assertCount(2, $this->search('?pattern=parent'));
    }

    public function testSimpleSearchIgnoresArrayValue(): void
    {
        // all records incl. the parent
        self::assertCount(3, $this->search('?pattern[]=eins'));
    }

    public function testContainsFilter(): void
    {
        $this->skipUnlessPostgres();

        self::assertCount(1, $this->search('?numbers=5'));
        self::assertCount(1, $this->search('?numbers[]=1&numbers[]=5'));
        self::assertCount(0, $this->search('?numbers[]=2&numbers[]=5'));
        self::assertCount(0, $this->search('?numbers=3'));
    }

    public function testJsonExistsFilter(): void
    {
        $this->skipUnlessPostgres();

        self::assertCount(1, $this->search('?roles=ROLE_ADMIN'));
        self::assertCount(1, $this->search('?roles=ROLE_ADMIN_BLOG'));
        self::assertCount(0, $this->search('?roles=ROLE'));
    }

    public function testParametersAreDocumented(): void
    {
        $names = $this->getOpenApiParameterNames('/api_filter_entities');

        foreach (['pattern', 'numbers', 'numbers[]', 'roles'] as $name) {
            self::assertContains($name, $names);
        }

        // JsonExistsFilter supports only a single value
        self::assertNotContains('roles[]', $names);
    }

    private function search(string $query): array
    {
        return $this->requestCollection('/api_filter_entities'.$query);
    }
}
