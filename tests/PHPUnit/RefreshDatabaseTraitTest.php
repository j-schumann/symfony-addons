<?php

declare(strict_types=1);

namespace Vrok\SymfonyAddons\Tests\PHPUnit;

use PHPUnit\Framework\Attributes\Group;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Vrok\SymfonyAddons\PHPUnit\RefreshDatabaseTrait;
use Vrok\SymfonyAddons\Tests\Fixtures\Entity\TestEntity;
use Zalas\PHPUnit\Globals\Attribute\Env;

final class RefreshDatabaseTraitTest extends KernelTestCase
{
    use RefreshDatabaseTrait;

    /**
     * This test is currently *not* in the "database" group as it would fail on
     * MySQL/MariaDB with "1701 Cannot truncate a table referenced in a foreign
     * key constraint". We would have to set up the MariaDbTestDriver from the
     * DoctrineAddons conditionally, and this would also only work for MariaDB.
     */
    #[Env('DB_CLEANUP_METHOD', 'purge')]
    public function testCleanupWithPurge(): void
    {
        self::bootKernel();

        $em = self::getContainer()->get('doctrine')->getManager();
        self::assertSame(0, $em->getRepository(TestEntity::class)->count());

        $record = new TestEntity();
        $em->persist($record);
        $em->flush();
        self::assertSame(1, $em->getRepository(TestEntity::class)->count());

        self::bootKernel();
        $em = self::getContainer()->get('doctrine')->getManager();
        self::assertSame(0, $em->getRepository(TestEntity::class)->count());
    }

    #[Env('DB_CLEANUP_METHOD', 'dropSchema')]
    #[Group('database')]
    public function testCleanupWithDropSchema(): void
    {
        self::bootKernel();

        $em = self::getContainer()->get('doctrine')->getManager();
        self::assertSame(0, $em->getRepository(TestEntity::class)->count());

        $record = new TestEntity();
        $em->persist($record);
        $em->flush();
        self::assertSame(1, $em->getRepository(TestEntity::class)->count());

        self::bootKernel();
        $em = self::getContainer()->get('doctrine')->getManager();
        self::assertSame(0, $em->getRepository(TestEntity::class)->count());
    }

    #[Env('DB_CLEANUP_METHOD', 'dropDatabase')]
    #[Group('database')]
    public function testCleanupWithDropDatabase(): void
    {
        self::bootKernel();

        $em = self::getContainer()->get('doctrine')->getManager();
        self::assertSame(0, $em->getRepository(TestEntity::class)->count());

        $record = new TestEntity();
        $em->persist($record);
        $em->flush();
        self::assertSame(1, $em->getRepository(TestEntity::class)->count());

        self::bootKernel();
        $em = self::getContainer()->get('doctrine')->getManager();
        self::assertSame(0, $em->getRepository(TestEntity::class)->count());
    }
}
