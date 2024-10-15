<?php

/** @noinspection PhpUnhandledExceptionInspection */

declare(strict_types=1);

namespace Vrok\SymfonyAddons\Tests\Filter;

use ApiPlatform\Doctrine\Orm\Util\QueryNameGenerator;
use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\ORM\QueryBuilder;
use Doctrine\ORM\Tools\SchemaTool;
use Doctrine\Persistence\ManagerRegistry;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Vrok\SymfonyAddons\Filter\SimpleSearchFilter;
use Vrok\SymfonyAddons\Tests\Fixtures\Entity\TestEntity;

#[Group('database')]
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

        self::assertEquals([
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
        self::assertEquals([
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

        $platform = $doctrine->getManager()->getConnection()->getDatabasePlatform();

        $dql = $platform instanceof PostgreSQLPlatform
            ? "LOWER(CAST(o.jsonColumn, 'text')) LIKE :pattern_p1)"
            : 'WHERE (LOWER(o.jsonColumn) LIKE :pattern_p1)';
        self::assertStringContainsString($dql, (string) $qb);
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

        $platform = $doctrine->getManager()->getConnection()->getDatabasePlatform();

        $dql = $platform instanceof PostgreSQLPlatform
            ? "WHERE (LOWER(o.id) LIKE :pattern_p1 OR LOWER(CAST(o.jsonColumn, 'text')) LIKE :pattern_p1)"
            : 'WHERE (LOWER(o.id) LIKE :pattern_p1 OR LOWER(o.jsonColumn) LIKE :pattern_p1)';
        self::assertStringContainsString($dql, (string) $qb);
    }

    public function testSearchInTextColumn(): void
    {
        $this->setupSchema();

        /** @var ManagerRegistry $doctrine */
        $doctrine =  static::getContainer()->get('doctrine');
        $em = $doctrine->getManager();

        $rec1 = new TestEntity();
        $rec1->textColumn = 'record EINS text';
        $rec1->varcharColumn = 'record EINS varchar';
        $em->persist($rec1);

        $rec2 = new TestEntity();
        $rec2->textColumn = 'record ZWEI text';
        $rec2->varcharColumn = 'record ZWEI varchar';
        $em->persist($rec2);
        $em->flush();

        $filter = new SimpleSearchFilter(
            $doctrine,
            null,
            ['textColumn' => null, 'varcharColumn' => null],
            null
        );
        $doctrine =  static::getContainer()->get('doctrine');
        $queryNameGen = new QueryNameGenerator();
        /** @var QueryBuilder $qb */
        $qb = $doctrine->getManager()->getRepository(TestEntity::class)
            ->createQueryBuilder('o');

        $filter->apply($qb, $queryNameGen, TestEntity::class, new \ApiPlatform\Metadata\Get(), [
            'filters' => [
                'pattern' => 'eins TEXT',
            ],
        ]);

        $result = $qb->getQuery()->getResult();
        self::assertCount(1, $result);
        self::assertInstanceOf(TestEntity::class, $result[0]);
        self::assertSame('record EINS text', $result[0]->textColumn);
    }

    public function testSearchInVarcharColumn(): void
    {
        $this->setupSchema();

        /** @var ManagerRegistry $doctrine */
        $doctrine =  static::getContainer()->get('doctrine');
        $em = $doctrine->getManager();

        $rec1 = new TestEntity();
        $rec1->textColumn = 'record EINS text';
        $rec1->varcharColumn = 'record EINS varchar';
        $em->persist($rec1);

        $rec2 = new TestEntity();
        $rec2->textColumn = 'record ZWEI text';
        $rec2->varcharColumn = 'record ZWEI varchar';
        $em->persist($rec2);
        $em->flush();

        $filter = new SimpleSearchFilter(
            $doctrine,
            null,
            ['textColumn' => null, 'varcharColumn' => null],
            null
        );
        $doctrine =  static::getContainer()->get('doctrine');
        $queryNameGen = new QueryNameGenerator();
        /** @var QueryBuilder $qb */
        $qb = $doctrine->getManager()->getRepository(TestEntity::class)
            ->createQueryBuilder('o');

        $filter->apply($qb, $queryNameGen, TestEntity::class, new \ApiPlatform\Metadata\Get(), [
            'filters' => [
                'pattern' => 'zwei VAR',
            ],
        ]);

        $result = $qb->getQuery()->getResult();
        self::assertCount(1, $result);
        self::assertInstanceOf(TestEntity::class, $result[0]);
        self::assertSame('record ZWEI text', $result[0]->textColumn);
    }

    public function testSearchInJsonColumn(): void
    {
        $this->setupSchema();

        /** @var ManagerRegistry $doctrine */
        $doctrine =  static::getContainer()->get('doctrine');
        $em = $doctrine->getManager();

        $rec1 = new TestEntity();
        $rec1->jsonColumn = ['record EINS json'];
        $em->persist($rec1);

        $rec2 = new TestEntity();
        $rec2->jsonColumn = ['record ZWEI json'];
        $em->persist($rec2);
        $em->flush();

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
                'pattern' => 'zwei JS',
            ],
        ]);

        $result = $qb->getQuery()->getResult();
        self::assertCount(1, $result);
        self::assertInstanceOf(TestEntity::class, $result[0]);
        self::assertSame(['record ZWEI json'], $result[0]->jsonColumn);
    }

    protected function setupSchema(): void
    {
        /** @var ManagerRegistry $doctrine */
        $doctrine =  static::getContainer()->get('doctrine');
        $em = $doctrine->getManager();

        $tool = new SchemaTool($em);
        $classes = [
            $em->getClassMetadata(TestEntity::class),
        ];
        $tool->dropSchema($classes);
        $tool->createSchema($classes);
    }
}
