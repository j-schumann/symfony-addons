<?php

/** @noinspection PhpUnhandledExceptionInspection */

declare(strict_types=1);

namespace Vrok\SymfonyAddons\Tests\Filter;

use ApiPlatform\Doctrine\Orm\Util\QueryNameGenerator;
use ApiPlatform\Metadata\Get;
use Doctrine\ORM\QueryBuilder;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Vrok\SymfonyAddons\Filter\ContainsFilter;
use Vrok\SymfonyAddons\Tests\Fixtures\Entity\TestEntity;

#[Group('database')]
class ContainsFilterTest extends KernelTestCase
{
    public function testGetDescription(): void
    {
        $doctrine =  static::getContainer()->get('doctrine');
        $filter = new ContainsFilter($doctrine, null, ['jsonColumn' => null], null);

        $this->assertEquals([
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
        $doctrine =  static::getContainer()->get('doctrine');
        $filter = new ContainsFilter($doctrine, null, ['jsonColumn' => null], null);
        $doctrine =  static::getContainer()->get('doctrine');
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
        self::assertSame('testVal', $param->getValue());

        $this->assertStringContainsString(
            'WHERE CONTAINS(o.jsonColumn, :jsonColumn_p1) = true',
            (string) $qb
        );
    }

    public function testApplyFilterForArray(): void
    {
        $doctrine =  static::getContainer()->get('doctrine');
        $filter = new ContainsFilter($doctrine, null, ['jsonColumn' => null], null);
        $doctrine =  static::getContainer()->get('doctrine');
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
        self::assertSame('testVal', $param->getValue());
        $param = $qb->getParameter('jsonColumn_p2');
        self::assertSame('otherVal', $param->getValue());

        $this->assertStringContainsString(
            'WHERE CONTAINS(o.jsonColumn, :jsonColumn_p1) = true AND CONTAINS(o.jsonColumn, :jsonColumn_p2) = true',
            (string) $qb
        );
    }
}
