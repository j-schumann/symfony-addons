<?php

namespace Vrok\SymfonyAddons\Tests\Doctrine;

use ApiPlatform\Doctrine\Orm\Util\QueryNameGenerator;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\QueryBuilder;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Vrok\SymfonyAddons\Doctrine\QueryBuilderHelper;
use Vrok\SymfonyAddons\Tests\Fixtures\Entity\TestEntity;

final class QueryBuilderHelperTest extends KernelTestCase
{
    private QueryBuilder $queryBuilder;
    private QueryBuilderHelper $helper;

    protected function setUp(): void
    {
        /** @var EntityManagerInterface $em */
        $em = self::getContainer()->get('doctrine.orm.entity_manager');
        $this->queryBuilder = $em->createQueryBuilder()->select('o')->from(TestEntity::class, 'o');
        $this->helper = new QueryBuilderHelper($this->queryBuilder, new QueryNameGenerator());
    }

    public function testGetRootAlias(): void
    {
        self::assertSame('o', $this->helper->getRootAlias());
    }

    public function testAndWhereFieldEquals(): void
    {
        $this->helper->andWhereFieldEquals('varcharColumn', 'a');

        self::assertSame(
            'SELECT o FROM '.TestEntity::class.' o WHERE o.varcharColumn = :varcharColumn_p1',
            $this->queryBuilder->getDQL()
        );
        self::assertSame('a', $this->queryBuilder->getParameter('varcharColumn_p1')?->getValue());
    }

    public function testAndWhereFieldNotEquals(): void
    {
        $this->helper->andWhereFieldNotEquals('varcharColumn', 'a', 'x');

        self::assertSame(
            'SELECT o FROM '.TestEntity::class.' o WHERE x.varcharColumn != :varcharColumn_p1',
            $this->queryBuilder->getDQL()
        );
    }

    public function testAndWhereFieldIn(): void
    {
        $this->helper->andWhereFieldIn('varcharColumn', ['a', 'b']);

        self::assertSame(
            'SELECT o FROM '.TestEntity::class.' o WHERE o.varcharColumn IN (:varcharColumn_p1)',
            $this->queryBuilder->getDQL()
        );
        self::assertSame(['a', 'b'], $this->queryBuilder->getParameter('varcharColumn_p1')?->getValue());
    }

    public function testGetJoinAlias(): void
    {
        self::assertNull($this->helper->getJoinAlias('children'));

        $alias = $this->helper->getJoinAlias('children', true);
        self::assertSame('children_a1', $alias);

        // the existing join is reused
        self::assertSame($alias, $this->helper->getJoinAlias('children', true));
        self::assertSame(
            'SELECT o FROM '.TestEntity::class.' o LEFT JOIN o.children children_a1',
            $this->queryBuilder->getDQL()
        );
    }

    public function testGetJoinAliasOfJoinedEntity(): void
    {
        $childAlias = $this->helper->addJoin('children');

        self::assertNull($this->helper->getJoinAlias('testEntity', false, $childAlias));

        $alias = $this->helper->getJoinAlias('testEntity', true, $childAlias);
        self::assertSame($alias, $this->helper->getJoinAlias('testEntity', true, $childAlias));
        self::assertSame(
            'SELECT o FROM '.TestEntity::class.' o LEFT JOIN o.children children_a1'
            .' LEFT JOIN children_a1.testEntity testEntity_a2',
            $this->queryBuilder->getDQL()
        );
    }
}
