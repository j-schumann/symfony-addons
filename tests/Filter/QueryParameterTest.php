<?php

/** @noinspection PhpUnhandledExceptionInspection */

namespace Vrok\SymfonyAddons\Tests\Filter;

use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\Group;
use Vrok\SymfonyAddons\Tests\Fixtures\Entity\Child;
use Vrok\SymfonyAddons\Tests\Fixtures\Entity\TestEntity;

/**
 * Uses the filters via #[QueryParameter] through the API Platform request
 * handling, see the second #[ApiResource] of the TestEntity.
 */
#[Group('database')]
final class QueryParameterTest extends FilterRequestTestCase
{
    protected function getEntityClasses(): array
    {
        return [TestEntity::class, Child::class];
    }

    protected function createRecords(EntityManagerInterface $em): void
    {
        $rec1 = new TestEntity();
        $rec1->textColumn = 'record EINS text';
        $rec1->varcharColumn = 'record EINS varchar';
        $rec1->jsonColumn = [1, 5, 'ROLE_ADMIN'];
        $child1 = new Child();
        $child1->varcharColumn = 'child EINS';
        $child1->testEntity = $rec1;
        $em->persist($child1);
        $em->persist($rec1);

        $rec2 = new TestEntity();
        $rec2->textColumn = 'record ZWEI text';
        $rec2->varcharColumn = 'record ZWEI varchar';
        $rec2->jsonColumn = [2, 'ROLE_ADMIN_BLOG'];
        $child2 = new Child();
        $child2->varcharColumn = 'child ZWEI';
        $child2->testEntity = $rec2;
        $em->persist($child2);
        $em->persist($rec2);
    }

    public function testSimpleSearchWithParameterProperties(): void
    {
        self::assertCount(2, $this->search('?pattern=RECORD'));
        self::assertCount(1, $this->search('?pattern=zwei%20text'));
        self::assertCount(1, $this->search('?pattern=eins%20varchar'));
        self::assertCount(0, $this->search('?pattern=drei'));
    }

    public function testSimpleSearchWithFilterProperties(): void
    {
        self::assertCount(1, $this->search('?search=eins%20varchar'));

        // textColumn is not searched
        self::assertCount(0, $this->search('?search=eins%20text'));
    }

    public function testSimpleSearchWithNestedProperty(): void
    {
        self::assertCount(2, $this->search('?childName=child'));
        self::assertCount(1, $this->search('?childName=zwei'));
    }

    public function testSimpleSearchIgnoresArrayValue(): void
    {
        self::assertCount(2, $this->search('?pattern[]=eins'));
    }

    public function testContainsFilter(): void
    {
        $this->skipUnlessPostgres();

        self::assertCount(1, $this->search('?contains=5'));
        self::assertCount(1, $this->search('?contains[]=1&contains[]=5'));
        self::assertCount(0, $this->search('?contains[]=2&contains[]=5'));
        self::assertCount(0, $this->search('?contains=3'));
    }

    public function testJsonExistsFilter(): void
    {
        $this->skipUnlessPostgres();

        self::assertCount(1, $this->search('?hasKey=ROLE_ADMIN'));
        self::assertCount(1, $this->search('?hasKey=ROLE_ADMIN_BLOG'));
        self::assertCount(0, $this->search('?hasKey=ROLE'));
    }

    public function testParametersAreDocumented(): void
    {
        $names = $this->getOpenApiParameterNames('/search_test_entities');

        foreach (['pattern', 'search', 'childName', 'contains', 'contains[]', 'hasKey'] as $name) {
            self::assertContains($name, $names);
        }
    }

    private function search(string $query): array
    {
        return $this->requestCollection('/search_test_entities'.$query);
    }
}
