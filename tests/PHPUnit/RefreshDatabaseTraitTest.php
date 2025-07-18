<?php

declare(strict_types=1);

namespace Vrok\SymfonyAddons\Tests\PHPUnit;

use PHPUnit\Framework\Attributes\Group;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Vrok\SymfonyAddons\PHPUnit\RefreshDatabaseTrait;
use Vrok\SymfonyAddons\Tests\Fixtures\Entity\TestEntity;
use Zalas\PHPUnit\Globals\Attribute\Env;

class RefreshDatabaseTraitTest extends KernelTestCase
{
    use RefreshDatabaseTrait;

    #[Env('DB_CLEANUP_METHOD', 'purge')]
    public function testCleanupWithPurge(): void
    {
        static::bootKernel();

        $em = static::getContainer()->get('doctrine')->getManager();
        self::assertSame(0, $em->getRepository(TestEntity::class)->count());

        $record = new TestEntity();
        $em->persist($record);
        $em->flush();
        self::assertSame(1, $em->getRepository(TestEntity::class)->count());

        static::bootKernel();
        $em = static::getContainer()->get('doctrine')->getManager();
        self::assertSame(0, $em->getRepository(TestEntity::class)->count());
    }

    #[Env('DB_CLEANUP_METHOD', 'dropSchema')]
    #[Group('database')]
    public function testCleanupWithDropSchema(): void
    {
        static::bootKernel();

        $em = static::getContainer()->get('doctrine')->getManager();
        self::assertSame(0, $em->getRepository(TestEntity::class)->count());

        $record = new TestEntity();
        $em->persist($record);
        $em->flush();
        self::assertSame(1, $em->getRepository(TestEntity::class)->count());

        static::bootKernel();
        $em = static::getContainer()->get('doctrine')->getManager();
        self::assertSame(0, $em->getRepository(TestEntity::class)->count());
    }

    #[Env('DB_CLEANUP_METHOD', 'dropDatabase')]
    #[Group('database')]
    public function testCleanupWithDropDatabase(): void
    {
        static::bootKernel();

        $em = static::getContainer()->get('doctrine')->getManager();
        self::assertSame(0, $em->getRepository(TestEntity::class)->count());

        $record = new TestEntity();
        $em->persist($record);
        $em->flush();
        self::assertSame(1, $em->getRepository(TestEntity::class)->count());

        static::bootKernel();
        $em = static::getContainer()->get('doctrine')->getManager();
        self::assertSame(0, $em->getRepository(TestEntity::class)->count());
    }
}
