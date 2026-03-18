<?php

declare(strict_types=1);

namespace Vrok\SymfonyAddons\Tests\PHPUnit;

use PHPUnit\Framework\Attributes\Group;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Vrok\SymfonyAddons\PHPUnit\RefreshDatabaseTrait;
use Vrok\SymfonyAddons\Tests\Fixtures\Entity\TestEntity;
use Zalas\PHPUnit\Globals\Attribute\Env;

#[Group('database')]
final class RefreshDatabaseTraitTest extends KernelTestCase
{
    use RefreshDatabaseTrait;

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
