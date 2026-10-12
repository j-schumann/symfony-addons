<?php

/** @noinspection PhpUnhandledExceptionInspection */

namespace Vrok\SymfonyAddons\Tests\Filter;

use ApiPlatform\Doctrine\Orm\Util\QueryNameGenerator;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\QueryParameter;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Vrok\SymfonyAddons\Filter\ContainsFilter;
use Vrok\SymfonyAddons\Tests\Fixtures\Entity\TestEntity;

final class ContainsFilterTest extends KernelTestCase
{
    public function testApply(): void
    {
        $qb = $this->applyFilter(new QueryParameter(key: 'contains', property: 'jsonColumn'), 'testVal');

        self::assertSame('testVal', $qb->getParameter('jsonColumn_p1')?->getValue());
        self::assertStringContainsString(
            'WHERE CONTAINS(o.jsonColumn, :jsonColumn_p1) = true',
            (string) $qb
        );
    }

    public function testApplyMultipleValues(): void
    {
        $qb = $this->applyFilter(
            new QueryParameter(key: 'contains', property: 'jsonColumn'),
            ['testVal', 'otherVal']
        );

        self::assertSame('testVal', $qb->getParameter('jsonColumn_p1')?->getValue());
        self::assertSame('otherVal', $qb->getParameter('jsonColumn_p2')?->getValue());
        self::assertStringContainsString(
            'WHERE CONTAINS(o.jsonColumn, :jsonColumn_p1) = true AND CONTAINS(o.jsonColumn, :jsonColumn_p2) = true',
            (string) $qb
        );
    }

    public function testApplyWithNestedProperty(): void
    {
        $qb = $this->applyFilter(
            new QueryParameter(key: 'childName', property: 'children.varcharColumn'),
            'testVal'
        );

        self::assertStringContainsString('LEFT JOIN o.children children_a1', (string) $qb);
        self::assertStringContainsString(
            'WHERE CONTAINS(children_a1.varcharColumn, :varcharColumn_p1) = true',
            (string) $qb
        );
    }

    public function testApplyIgnoresUnmappedProperty(): void
    {
        $qb = $this->applyFilter(new QueryParameter(key: 'unknown'), 'testVal');

        self::assertStringNotContainsString('WHERE', (string) $qb);
    }

    public function testApplyIgnoresContextWithoutParameter(): void
    {
        $qb = $this->createQueryBuilder();

        // the context of the removed #[ApiFilter] support
        new ContainsFilter($this->getDoctrine())->apply($qb, new QueryNameGenerator(), TestEntity::class, new Get(), [
            'filters' => ['jsonColumn' => 'testVal'],
        ]);

        self::assertStringNotContainsString('WHERE', (string) $qb);
    }

    private function getDoctrine(): ManagerRegistry
    {
        return self::getContainer()->get('doctrine');
    }

    private function createQueryBuilder(): QueryBuilder
    {
        return $this->getDoctrine()->getManager()->getRepository(TestEntity::class)
            ->createQueryBuilder('o');
    }

    private function applyFilter(QueryParameter $parameter, mixed $value): QueryBuilder
    {
        $parameter->setValue($value);
        $qb = $this->createQueryBuilder();

        new ContainsFilter($this->getDoctrine())->apply($qb, new QueryNameGenerator(), TestEntity::class, new Get(), [
            'parameter' => $parameter,
        ]);

        return $qb;
    }
}
