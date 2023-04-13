<?php

/** @noinspection PhpUnhandledExceptionInspection */

declare(strict_types=1);

use ApiPlatform\Doctrine\Orm\Util\QueryNameGenerator;
use Doctrine\ORM\QueryBuilder;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Vrok\SymfonyAddons\Filter\SimpleSearchFilter;
use Vrok\SymfonyAddons\Tests\Fixtures\TestEntity;

/**
 * @group SimpleSearchFilter
 */
class SimpleSearchFilterTest extends KernelTestCase
{
    public function testGetDescription(): void
    {
        $filter = new SimpleSearchFilter(
            static::getContainer()->get('doctrine'),
            null,
            ['id' => null, 'jsonColumn' => null],
            null
        );

        $this->assertEquals([
            'pattern' => [
                'property' => 'id, jsonColumn',
                'type'     => 'string',
                'required' => false,
                'openapi'  => [
                    'description' => 'Selects entities where each search term is found somewhere in at least one of the specified properties',
                ],
            ],
        ], $filter->getDescription(TestEntity::class));
    }

    public function testAcceptsSearchParameterName(): void
    {
        $filter = new SimpleSearchFilter(
            static::getContainer()->get('doctrine'),
            null,
            ['id' => null, 'jsonColumn' => null],
            null,
            'searchFor'
        );
        $this->assertEquals([
            'searchFor' => [
                'property' => 'id, jsonColumn',
                'type'     => 'string',
                'required' => false,
                'openapi'  => [
                    'description' => 'Selects entities where each search term is found somewhere in at least one of the specified properties',
                ],
            ],
        ], $filter->getDescription(TestEntity::class));
    }

    public function testApplyFilter(): void
    {
        $doctrine =  static::getContainer()->get('doctrine');
        $filter = new SimpleSearchFilter(
            $doctrine,
            null,
            ['jsonColumn' => null],
            null
        );
        $doctrine =  static::getContainer()->get('doctrine');
        $queryNameGen = new QueryNameGenerator();
        /** @var QueryBuilder $qb */
        $qb = $doctrine->getManager()->getRepository(TestEntity::class)
            ->createQueryBuilder('o');

        $filter->apply($qb, $queryNameGen, TestEntity::class, new \ApiPlatform\Metadata\Get(), [
            'filters' => [
                'pattern' => 'testVal',
            ],
        ]);

        $param = $qb->getParameter('pattern_p1');
        self::assertSame('%testval%', $param->getValue());

        $this->assertStringContainsString(
            "WHERE (LOWER(CAST(o.jsonColumn, 'text')) LIKE :pattern_p1)",
            (string) $qb
        );

        // this should not be necessary, the correct translation into SQL should
        // be tested where LOWER and CAST are defined:
        $this->assertStringContainsString(
            'WHERE (LOWER(CAST(t0_.jsonColumn as text)) LIKE ?)',
            $qb->getQuery()->getSQL()
        );
    }

    public function testApplyFilterWithMultipleFields(): void
    {
        $doctrine =  static::getContainer()->get('doctrine');
        $filter = new SimpleSearchFilter(
            $doctrine,
            null,
            ['id' => null, 'jsonColumn' => null],
            null
        );
        $doctrine =  static::getContainer()->get('doctrine');
        $queryNameGen = new QueryNameGenerator();
        /** @var QueryBuilder $qb */
        $qb = $doctrine->getManager()->getRepository(TestEntity::class)
            ->createQueryBuilder('o');

        $filter->apply($qb, $queryNameGen, TestEntity::class, new \ApiPlatform\Metadata\Get(), [
            'filters' => [
                'pattern' => 'testVal',
            ],
        ]);

        // lower-cased and wildcards added:
        $param = $qb->getParameter('pattern_p1');
        self::assertSame('%testval%', $param->getValue());

        $this->assertStringContainsString(
            "WHERE (LOWER(CAST(o.id, 'text')) LIKE :pattern_p1 OR LOWER(CAST(o.jsonColumn, 'text')) LIKE :pattern_p1)",
            (string) $qb
        );

        // this should not be necessary, the correct translation into SQL should
        // be tested where LOWER and CAST are defined:
        $this->assertStringContainsString(
            'WHERE (LOWER(CAST(t0_.id as text)) LIKE ? OR LOWER(CAST(t0_.jsonColumn as text)) LIKE ?)',
            $qb->getQuery()->getSQL()
        );
    }
}
