<?php

/** @noinspection PhpUnhandledExceptionInspection */

namespace Vrok\SymfonyAddons\Tests\Filter;

use ApiPlatform\Doctrine\Orm\Util\QueryNameGenerator;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\QueryParameter;
use Doctrine\ORM\Query\Parameter;
use Doctrine\ORM\QueryBuilder;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Vrok\SymfonyAddons\Filter\ContainsFilter;
use Vrok\SymfonyAddons\Tests\Fixtures\Entity\TestEntity;

#[Group('database')]
final class ContainsFilterTest extends KernelTestCase
{
    public function testGetDescription(): void
    {
        $doctrine =  self::getContainer()->get('doctrine');
        $filter = new ContainsFilter($doctrine, null, ['jsonColumn' => null]);

        self::assertEquals([
            'jsonColumn'   => [
                'property' => 'jsonColumn',
                'type'     => 'mixed',
                'required' => false,
            ],
            'jsonColumn[]' => [
                'property' => 'jsonColumn',
                'type'     => 'mixed',
                'required' => false,
            ],
        ], $filter->getDescription(TestEntity::class));
    }

    public function testApplyFilter(): void
    {
        $doctrine =  self::getContainer()->get('doctrine');
        $filter = new ContainsFilter($doctrine, null, ['jsonColumn' => null]);
        $doctrine =  self::getContainer()->get('doctrine');
        $queryNameGen = new QueryNameGenerator();

        /** @var QueryBuilder $qb */
        $qb = $doctrine->getManager()->getRepository(TestEntity::class)
            ->createQueryBuilder('o');

        $filter->apply($qb, $queryNameGen, TestEntity::class, new Get(), [
            'filters' => [
                'jsonColumn' => 'testVal',
            ],
        ]);

        $param = $qb->getParameter('jsonColumn_p1');
        self::assertInstanceOf(Parameter::class, $param);
        self::assertSame('testVal', $param->getValue());

        self::assertStringContainsString(
            'WHERE CONTAINS(o.jsonColumn, :jsonColumn_p1) = true',
            (string) $qb
        );
    }

    public function testApplyFilterForArray(): void
    {
        $doctrine =  self::getContainer()->get('doctrine');
        $filter = new ContainsFilter($doctrine, null, ['jsonColumn' => null]);
        $doctrine =  self::getContainer()->get('doctrine');
        $queryNameGen = new QueryNameGenerator();

        /** @var QueryBuilder $qb */
        $qb = $doctrine->getManager()->getRepository(TestEntity::class)
            ->createQueryBuilder('o');

        $filter->apply($qb, $queryNameGen, TestEntity::class, new Get(), [
            'filters' => [
                'jsonColumn' => ['testVal', 'otherVal'],
            ],
        ]);

        $param = $qb->getParameter('jsonColumn_p1');
        self::assertInstanceOf(Parameter::class, $param);
        self::assertSame('testVal', $param->getValue());
        $param = $qb->getParameter('jsonColumn_p2');
        self::assertInstanceOf(Parameter::class, $param);
        self::assertSame('otherVal', $param->getValue());

        self::assertStringContainsString(
            'WHERE CONTAINS(o.jsonColumn, :jsonColumn_p1) = true AND CONTAINS(o.jsonColumn, :jsonColumn_p2) = true',
            (string) $qb
        );
    }

    public function testApplyQueryParameter(): void
    {
        $doctrine = self::getContainer()->get('doctrine');
        $filter = new ContainsFilter($doctrine);

        /** @var QueryBuilder $qb */
        $qb = $doctrine->getManager()->getRepository(TestEntity::class)
            ->createQueryBuilder('o');

        $parameter = new QueryParameter(key: 'contains', property: 'jsonColumn');
        $parameter->setValue(['testVal', 'otherVal']);

        $filter->apply($qb, new QueryNameGenerator(), TestEntity::class, new Get(), [
            'parameter' => $parameter,
            'filters'   => ['jsonColumn' => ['testVal', 'otherVal']],
        ]);

        self::assertSame('testVal', $qb->getParameter('jsonColumn_p1')?->getValue());
        self::assertSame('otherVal', $qb->getParameter('jsonColumn_p2')?->getValue());
        self::assertStringContainsString(
            'WHERE CONTAINS(o.jsonColumn, :jsonColumn_p1) = true AND CONTAINS(o.jsonColumn, :jsonColumn_p2) = true',
            (string) $qb
        );
    }

    public function testApplyQueryParameterWithNestedProperty(): void
    {
        $doctrine = self::getContainer()->get('doctrine');

        // nested properties need not be enabled in the filter, the parameter
        // names them explicitly
        $filter = new ContainsFilter($doctrine);

        /** @var QueryBuilder $qb */
        $qb = $doctrine->getManager()->getRepository(TestEntity::class)
            ->createQueryBuilder('o');

        $parameter = new QueryParameter(key: 'childName', property: 'children.varcharColumn');
        $parameter->setValue('testVal');

        $filter->apply($qb, new QueryNameGenerator(), TestEntity::class, new Get(), [
            'parameter' => $parameter,
        ]);

        self::assertStringContainsString('LEFT JOIN o.children children_a1', (string) $qb);
        self::assertStringContainsString(
            'WHERE CONTAINS(children_a1.varcharColumn, :varcharColumn_p1) = true',
            (string) $qb
        );
    }

    public function testApplyQueryParameterIgnoresUnmappedProperty(): void
    {
        $doctrine = self::getContainer()->get('doctrine');
        $filter = new ContainsFilter($doctrine);

        /** @var QueryBuilder $qb */
        $qb = $doctrine->getManager()->getRepository(TestEntity::class)
            ->createQueryBuilder('o');

        $parameter = new QueryParameter(key: 'unknown');
        $parameter->setValue('testVal');

        $filter->apply($qb, new QueryNameGenerator(), TestEntity::class, new Get(), [
            'parameter' => $parameter,
        ]);

        self::assertStringNotContainsString('WHERE', (string) $qb);
    }
}
