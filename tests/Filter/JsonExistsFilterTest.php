<?php

/** @noinspection PhpUnhandledExceptionInspection */

namespace Vrok\SymfonyAddons\Tests\Filter;

use ApiPlatform\Doctrine\Orm\Util\QueryNameGenerator;
use ApiPlatform\Metadata\Exception\InvalidArgumentException;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\QueryParameter;
use Doctrine\ORM\Query\Parameter;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;
use Monolog\Handler\TestHandler;
use Monolog\Logger;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Vrok\SymfonyAddons\Filter\JsonExistsFilter;
use Vrok\SymfonyAddons\Tests\Fixtures\Entity\TestEntity;

#[Group('database')]
final class JsonExistsFilterTest extends KernelTestCase
{
    public function testGetDescription(): void
    {
        $doctrine =  self::getContainer()->get('doctrine');
        $filter = new JsonExistsFilter($doctrine, null, ['jsonColumn' => null]);

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
        $doctrine =  self::getContainer()->get('doctrine');
        $filter = new JsonExistsFilter($doctrine, null, ['jsonColumn' => null]);
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

        self::assertStringContainsString('WHERE JSON_CONTAINS_TEXT(o.jsonColumn, :jsonColumn_p1) = true', (string) $qb);
    }

    public function testApplyQueryParameter(): void
    {
        $doctrine = self::getContainer()->get('doctrine');
        $filter = new JsonExistsFilter($doctrine);

        /** @var QueryBuilder $qb */
        $qb = $doctrine->getManager()->getRepository(TestEntity::class)
            ->createQueryBuilder('o');

        $parameter = new QueryParameter(key: 'hasKey', property: 'jsonColumn');
        $parameter->setValue('testVal');

        $filter->apply($qb, new QueryNameGenerator(), TestEntity::class, new Get(), [
            'parameter' => $parameter,
            'filters'   => ['jsonColumn' => 'testVal'],
        ]);

        self::assertSame('testVal', $qb->getParameter('jsonColumn_p1')?->getValue());
        self::assertStringContainsString('WHERE JSON_CONTAINS_TEXT(o.jsonColumn, :jsonColumn_p1) = true', (string) $qb);
    }

    public function testApplyMultipleValuesWithOr(): void
    {
        $filter = new JsonExistsFilter($this->getDoctrine(), combination: JsonExistsFilter::OR);
        $qb = $this->applyFilter($filter, ['ROLE_A', 'ROLE_B', 'ROLE_A']);

        // duplicates are removed
        self::assertSame(['ROLE_A', 'ROLE_B'], $qb->getParameter('jsonColumn_p1')?->getValue());
        self::assertStringContainsString('WHERE JSON_CONTAINS_ANY_TEXT(o.jsonColumn, :jsonColumn_p1) = true', (string) $qb);
    }

    public function testApplyMultipleValuesWithAndByDefault(): void
    {
        $qb = $this->applyFilter(new JsonExistsFilter($this->getDoctrine()), ['ROLE_A', 'ROLE_B']);

        self::assertSame(['ROLE_A', 'ROLE_B'], $qb->getParameter('jsonColumn_p1')?->getValue());
        self::assertStringContainsString('WHERE JSON_CONTAINS_ALL_TEXT(o.jsonColumn, :jsonColumn_p1) = true', (string) $qb);
    }

    public function testApplySingleValueArray(): void
    {
        $qb = $this->applyFilter(new JsonExistsFilter($this->getDoctrine()), ['ROLE_A']);

        self::assertSame('ROLE_A', $qb->getParameter('jsonColumn_p1')?->getValue());
        self::assertStringContainsString('WHERE JSON_CONTAINS_TEXT(o.jsonColumn, :jsonColumn_p1) = true', (string) $qb);
    }

    public function testApplyIgnoresInvalidValues(): void
    {
        foreach ([[], [['nested']], null] as $value) {
            $logHandler = new TestHandler();
            $filter = new JsonExistsFilter($this->getDoctrine(), new Logger('test', [$logHandler]));
            $qb = $this->applyFilter($filter, $value);

            self::assertStringNotContainsString('WHERE', (string) $qb);
            self::assertTrue($logHandler->hasNotice('Invalid filter ignored'));
        }
    }

    public function testRejectsInvalidCombination(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid combination "xor"');

        new JsonExistsFilter(combination: 'xor');
    }

    private function getDoctrine(): ManagerRegistry
    {
        return self::getContainer()->get('doctrine');
    }

    private function applyFilter(JsonExistsFilter $filter, mixed $value): QueryBuilder
    {
        /** @var QueryBuilder $qb */
        $qb = $this->getDoctrine()->getManager()->getRepository(TestEntity::class)
            ->createQueryBuilder('o');

        $filter->apply($qb, new QueryNameGenerator(), TestEntity::class, new Get(), [
            'filters' => ['jsonColumn' => $value],
        ]);

        return $qb;
    }
}
