<?php

/** @noinspection PhpUnhandledExceptionInspection */

declare(strict_types=1);

namespace Vrok\SymfonyAddons\Tests\Filter;

use ApiPlatform\Doctrine\Orm\Util\QueryNameGenerator;
use ApiPlatform\Metadata\Get;
use Doctrine\ORM\QueryBuilder;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Vrok\SymfonyAddons\Filter\JsonExistsFilter;
use Vrok\SymfonyAddons\Tests\Fixtures\Entity\TestEntity;

#[Group('database')]
class JsonExistsFilterTest extends KernelTestCase
{
    public function testGetDescription(): void
    {
        $doctrine =  static::getContainer()->get('doctrine');
        $filter = new JsonExistsFilter($doctrine, null, ['jsonColumn' => null], null);

        self::assertEquals([
            'jsonColumn'   => [
                'property' => 'jsonColumn',
                'type'     => 'string',
                'required' => false,
            ],
            'jsonColumn[]' => [
                'property' => 'jsonColumn',
                'type'     => 'string',
                'required' => false,
            ],
        ], $filter->getDescription(TestEntity::class));
    }

    public function testApplyFilter(): void
    {
        $doctrine =  static::getContainer()->get('doctrine');
        $filter = new JsonExistsFilter($doctrine, null, ['jsonColumn' => null], null);
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

        self::assertStringContainsString('WHERE JSON_CONTAINS_TEXT(o.jsonColumn, :jsonColumn_p1) = true', (string) $qb);
    }
}
