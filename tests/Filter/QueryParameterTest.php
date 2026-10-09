<?php

/** @noinspection PhpUnhandledExceptionInspection */

namespace Vrok\SymfonyAddons\Tests\Filter;

use Doctrine\ORM\Tools\SchemaTool;
use Doctrine\Persistence\ManagerRegistry;
use PHPUnit\Framework\Attributes\Group;
use Vrok\SymfonyAddons\PHPUnit\BaseApiTestCase;
use Vrok\SymfonyAddons\Tests\Fixtures\Entity\Child;
use Vrok\SymfonyAddons\Tests\Fixtures\Entity\TestEntity;

/**
 * Uses the filters via #[QueryParameter] through the API Platform request
 * handling, see the second #[ApiResource] of the TestEntity.
 */
#[Group('database')]
final class QueryParameterTest extends BaseApiTestCase
{
    protected static ?bool $alwaysBootKernel = false;

    protected function setUp(): void
    {
        parent::setUp();

        /** @var ManagerRegistry $doctrine */
        $doctrine = self::getContainer()->get('doctrine');
        $em = $doctrine->getManager();

        $tool = new SchemaTool($em);
        $classes = [
            $em->getClassMetadata(TestEntity::class),
            $em->getClassMetadata(Child::class),
        ];
        $tool->dropSchema($classes);
        $tool->createSchema($classes);

        $rec1 = new TestEntity();
        $rec1->textColumn = 'record EINS text';
        $rec1->varcharColumn = 'record EINS varchar';
        $em->persist($rec1);

        $rec2 = new TestEntity();
        $rec2->textColumn = 'record ZWEI text';
        $rec2->varcharColumn = 'record ZWEI varchar';
        $em->persist($rec2);

        $em->flush();
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

    public function testSimpleSearchIgnoresArrayValue(): void
    {
        self::assertCount(2, $this->search('?pattern[]=eins'));
    }

    private function search(string $query): array
    {
        $response = self::createClient()->request(
            'GET',
            '/search_test_entities'.$query,
            ['headers' => ['Accept' => 'application/json']],
        );
        self::assertResponseIsSuccessful();

        return $response->toArray();
    }
}
