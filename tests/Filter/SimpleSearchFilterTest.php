<?php

/** @noinspection PhpUnhandledExceptionInspection */

namespace Vrok\SymfonyAddons\Tests\Filter;

use ApiPlatform\Doctrine\Orm\Util\QueryNameGenerator;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\QueryParameter;
use ApiPlatform\Test\ApiTestCase;
use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\ORM\QueryBuilder;
use Doctrine\ORM\Tools\SchemaTool;
use Doctrine\Persistence\ManagerRegistry;
use Monolog\Handler\TestHandler;
use Monolog\Logger;
use Vrok\SymfonyAddons\Filter\SimpleSearchFilter;
use Vrok\SymfonyAddons\Tests\Fixtures\Entity\Child;
use Vrok\SymfonyAddons\Tests\Fixtures\Entity\TestEntity;

final class SimpleSearchFilterTest extends ApiTestCase
{
    public function testApply(): void
    {
        $qb = $this->applyFilter(new SimpleSearchFilter($this->getDoctrine()), ['jsonColumn'], 'testVal');

        // lower-cased and wildcards added
        self::assertSame('%testval%', $qb->getParameter('pattern_p1')?->getValue());

        $dql = $this->isPostgres()
            ? "WHERE (LOWER(CAST(o.jsonColumn, 'text')) LIKE :pattern_p1)"
            : 'WHERE (LOWER(o.jsonColumn) LIKE :pattern_p1)';
        self::assertStringContainsString($dql, (string) $qb);
    }

    public function testApplyWithMultipleProperties(): void
    {
        $qb = $this->applyFilter(
            new SimpleSearchFilter($this->getDoctrine()),
            ['id', 'jsonColumn'],
            'testVal'
        );

        $dql = $this->isPostgres()
            ? "WHERE (LOWER(o.id) LIKE :pattern_p1 OR LOWER(CAST(o.jsonColumn, 'text')) LIKE :pattern_p1)"
            : 'WHERE (LOWER(o.id) LIKE :pattern_p1 OR LOWER(o.jsonColumn) LIKE :pattern_p1)';
        self::assertStringContainsString($dql, (string) $qb);
    }

    public function testApplyWithFilterProperties(): void
    {
        $filter = new SimpleSearchFilter($this->getDoctrine(), properties: ['varcharColumn' => null]);
        $qb = $this->applyFilter($filter, null, 'testVal');

        self::assertStringContainsString('WHERE (LOWER(o.varcharColumn) LIKE :pattern_p1)', (string) $qb);
    }

    public function testApplyIgnoresUnmappedProperty(): void
    {
        $qb = $this->applyFilter(new SimpleSearchFilter($this->getDoctrine()), ['unknown'], 'testVal');

        self::assertStringNotContainsString('WHERE', (string) $qb);
    }

    public function testApplyIgnoresArrayValue(): void
    {
        $logHandler = new TestHandler();
        $filter = new SimpleSearchFilter($this->getDoctrine(), new Logger('test', [$logHandler]));
        $qb = $this->applyFilter($filter, ['textColumn'], ['testVal']);

        self::assertStringNotContainsString('WHERE', (string) $qb);
        self::assertTrue($logHandler->hasNotice('Invalid filter ignored'));
    }

    public function testApplyIgnoresContextWithoutParameter(): void
    {
        $filter = new SimpleSearchFilter($this->getDoctrine(), properties: ['varcharColumn' => null]);
        $qb = $this->createQueryBuilder();

        // the context of the removed #[ApiFilter] support
        $filter->apply($qb, new QueryNameGenerator(), TestEntity::class, new Get(), [
            'filters' => ['pattern' => 'testVal'],
        ]);

        self::assertStringNotContainsString('WHERE', (string) $qb);
    }

    public function testSearchInTextColumn(): void
    {
        $this->setupSchema();

        /** @var ManagerRegistry $doctrine */
        $doctrine =  self::getContainer()->get('doctrine');
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

        $qb = $this->applyFilter(
            new SimpleSearchFilter($doctrine),
            ['textColumn', 'varcharColumn'],
            'eins TEXT'
        );

        $result = $qb->getQuery()->getResult();
        self::assertCount(1, $result);
        self::assertInstanceOf(TestEntity::class, $result[0]);
        self::assertSame('record EINS text', $result[0]->textColumn);
    }

    public function testSearchInVarcharColumn(): void
    {
        $this->setupSchema();

        /** @var ManagerRegistry $doctrine */
        $doctrine =  self::getContainer()->get('doctrine');
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

        $qb = $this->applyFilter(
            new SimpleSearchFilter($doctrine),
            ['textColumn', 'varcharColumn'],
            'zwei VAR'
        );

        $result = $qb->getQuery()->getResult();
        self::assertCount(1, $result);
        self::assertInstanceOf(TestEntity::class, $result[0]);
        self::assertSame('record ZWEI text', $result[0]->textColumn);
    }

    public function testSearchInJsonColumn(): void
    {
        $this->setupSchema();

        /** @var ManagerRegistry $doctrine */
        $doctrine =  self::getContainer()->get('doctrine');
        $em = $doctrine->getManager();

        $rec1 = new TestEntity();
        $rec1->jsonColumn = ['record EINS json'];

        $em->persist($rec1);

        $rec2 = new TestEntity();
        $rec2->jsonColumn = ['record ZWEI json'];

        $em->persist($rec2);
        $em->flush();

        $qb = $this->applyFilter(
            new SimpleSearchFilter($doctrine),
            ['jsonColumn'],
            'zwei JS'
        );

        $result = $qb->getQuery()->getResult();
        self::assertCount(1, $result);
        self::assertInstanceOf(TestEntity::class, $result[0]);
        self::assertSame(['record ZWEI json'], $result[0]->jsonColumn);
    }

    public function testSearchInAssociation(): void
    {
        $this->setupSchema();

        /** @var ManagerRegistry $doctrine */
        $doctrine =  self::getContainer()->get('doctrine');
        $em = $doctrine->getManager();

        $rec1 = new TestEntity();
        $rec1->textColumn = 'record EINS text';
        $rec1->varcharColumn = 'record EINS varchar';

        $em->persist($rec1);

        $child1 = new Child();
        $child1->varcharColumn = 'child EINS varchar';
        $child1->testEntity = $rec1;

        $rec1->children->add($child1);

        $rec2 = new TestEntity();
        $rec2->textColumn = 'record ZWEI text';
        $rec2->varcharColumn = 'record ZWEI varchar';

        $em->persist($rec2);

        $child2 = new Child();
        $child2->varcharColumn = 'child ZWEI varchar';
        $child2->testEntity = $rec2;

        $rec2->children->add($child2);

        $em->flush();
        $em->clear();

        $qb = $this->applyFilter(
            new SimpleSearchFilter($doctrine),
            ['children.varcharColumn'],
            'ILD zwei'
        );

        $result = $qb->getQuery()->getResult();
        self::assertCount(1, $result);
        self::assertInstanceOf(TestEntity::class, $result[0]);
        self::assertSame('record ZWEI text', $result[0]->textColumn);
    }

    private function getDoctrine(): ManagerRegistry
    {
        return self::getContainer()->get('doctrine');
    }

    private function isPostgres(): bool
    {
        return $this->getDoctrine()->getConnection()->getDatabasePlatform() instanceof PostgreSQLPlatform;
    }

    private function createQueryBuilder(): QueryBuilder
    {
        return $this->getDoctrine()->getManager()->getRepository(TestEntity::class)
            ->createQueryBuilder('o');
    }

    /**
     * @param list<string>|null $properties
     */
    private function applyFilter(SimpleSearchFilter $filter, ?array $properties, mixed $value): QueryBuilder
    {
        $parameter = new QueryParameter(key: 'pattern', properties: $properties);
        $parameter->setValue($value);

        $qb = $this->createQueryBuilder();

        $filter->apply($qb, new QueryNameGenerator(), TestEntity::class, new Get(), [
            'parameter' => $parameter,
        ]);

        return $qb;
    }

    private function setupSchema(): void
    {
        /** @var ManagerRegistry $doctrine */
        $doctrine =  self::getContainer()->get('doctrine');
        $em = $doctrine->getManager();

        $tool = new SchemaTool($em);
        $classes = [
            $em->getClassMetadata(TestEntity::class),
            $em->getClassMetadata(Child::class),
        ];
        $tool->dropSchema($classes);
        $tool->createSchema($classes);
    }
}
