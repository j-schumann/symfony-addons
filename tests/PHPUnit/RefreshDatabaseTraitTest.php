<?php

namespace Vrok\SymfonyAddons\Tests\PHPUnit;

use Doctrine\DBAL\Platforms\MariaDBPlatform;
use Doctrine\DBAL\Platforms\MySQLPlatform;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Vrok\SymfonyAddons\PHPUnit\RefreshDatabaseTrait;
use Vrok\SymfonyAddons\Tests\Fixtures\Entity\Child;
use Vrok\SymfonyAddons\Tests\Fixtures\Entity\Inheritance\AssignedIdEntity;
use Vrok\SymfonyAddons\Tests\Fixtures\Entity\Inheritance\CompositeKeyEntity;
use Vrok\SymfonyAddons\Tests\Fixtures\Entity\Inheritance\JoinedChildA;
use Vrok\SymfonyAddons\Tests\Fixtures\Entity\Inheritance\JoinedChildB;
use Vrok\SymfonyAddons\Tests\Fixtures\Entity\Inheritance\ReservedWordEntity;
use Vrok\SymfonyAddons\Tests\Fixtures\Entity\Inheritance\SelfReferencingEntity;
use Vrok\SymfonyAddons\Tests\Fixtures\Entity\Inheritance\SingleTableChildA;
use Vrok\SymfonyAddons\Tests\Fixtures\Entity\Inheritance\SingleTableChildB;
use Vrok\SymfonyAddons\Tests\Fixtures\Entity\Inheritance\SuperclassChildA;
use Vrok\SymfonyAddons\Tests\Fixtures\Entity\Inheritance\SuperclassChildB;
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

    #[Env('DB_CLEANUP_METHOD', 'purge')]
    #[Env('DB_PURGE_MODE', 'truncate')]
    public function testCleanupWithPurgeAndTruncate(): void
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

    #[Env('DB_CLEANUP_METHOD', 'unknownMethod')]
    public function testUnknownCleanupMethodFails(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("Unknown DB_CLEANUP_METHOD 'unknownMethod', allowed values are: purge, dropSchema, dropDatabase");

        self::bootKernel();
    }

    #[Env('DB_CLEANUP_METHOD', 'purge')]
    #[Env('DB_PURGE_MODE', 'unknownMode')]
    public function testUnknownPurgeModeFails(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("Unknown DB_PURGE_MODE 'unknownMode', allowed values are: delete, truncate");

        self::bootKernel();
    }

    /**
     * Exactly one entry per table that really has an identity column: mapped superclasses and
     * embeddables own no table, the children of a SINGLE_TABLE hierarchy share the table of their
     * root, the children of a JOINED hierarchy have their own table but no identity column in it,
     * and assigned / composite identifiers have no identity column at all.
     */
    #[Env('DB_CLEANUP_METHOD', 'purge')]
    public function testIdentityTablesAreDerivedFromTheMapping(): void
    {
        self::bootKernel();

        $em = self::getContainer()->get('doctrine')->getManager();
        $platform = $em->getConnection()->getDatabasePlatform();
        $tables = self::getIdentityTables($em, $platform);

        $expected = [
            'Child',
            'JoinedRoot',
            'SelfReferencingEntity',
            'SingleTableRoot',
            'SuperclassChildA',
            'SuperclassChildB',
            'TestEntity',

            // the quote character differs per platform, so does the sort order
            $platform->quoteSingleIdentifier('order'),
        ];
        sort($expected);

        $quotedNames = array_keys($tables);
        sort($quotedNames);
        self::assertSame($expected, $quotedNames);

        // the unquoted names are required to address the sequence / the identity column of a table
        self::assertSame(
            ['name' => 'order', 'column' => 'id'],
            $tables[$platform->quoteSingleIdentifier('order')]
        );
    }

    /**
     * All tables have to be emptied, including those that own no identity and are thus not part of
     * the list above.
     */
    #[Env('DB_CLEANUP_METHOD', 'purge')]
    public function testPurgeEmptiesAllMappedTables(): void
    {
        self::bootKernel();

        $em = self::getContainer()->get('doctrine')->getManager();
        $this->createOneOfEach($em);

        foreach ($this->countableClasses() as $class) {
            self::assertSame(1, $em->getRepository($class)->count(), $class);
        }

        self::bootKernel();
        $em = self::getContainer()->get('doctrine')->getManager();

        foreach ($this->countableClasses() as $class) {
            self::assertSame(0, $em->getRepository($class)->count(), $class);
        }
    }

    /**
     * The identity of every table has to be reset by the purge, on every platform and for every
     * class of an inheritance hierarchy. A JOINED child is the interesting case: it has its own
     * table, but the counter it uses sits in the table of the root.
     */
    #[Env('DB_CLEANUP_METHOD', 'purge')]
    public function testPurgeResetsIdentities(): void
    {
        self::bootKernel();
        $em = self::getContainer()->get('doctrine')->getManager();

        // increase the counters first, a purge of tables that were never inserted into would be
        // trivial to pass
        $first = $this->generatedIds($this->createOneOfEach($em));
        self::assertNotSame([], $first);

        self::bootKernel();
        $em = self::getContainer()->get('doctrine')->getManager();

        $second = $this->generatedIds($this->createOneOfEach($em));

        self::assertSame($first, $second);
    }

    /**
     * The same with the purge falling back to TRUNCATE: on MySQL/MariaDB the identities are then
     * reset by the database itself, the result must not differ.
     */
    #[Env('DB_CLEANUP_METHOD', 'purge')]
    #[Env('DB_PURGE_MODE', 'truncate')]
    public function testPurgeWithTruncateResetsIdentities(): void
    {
        self::bootKernel();

        $em = self::getContainer()->get('doctrine')->getManager();
        $platform = $em->getConnection()->getDatabasePlatform();
        if (!$platform instanceof MySQLPlatform && !$platform instanceof MariaDBPlatform) {
            self::markTestSkipped('Only MySQL/MariaDB can fall back to TRUNCATE');
        }

        $first = $this->generatedIds($this->createOneOfEach($em));
        self::assertNotSame([], $first);

        self::bootKernel();
        $em = self::getContainer()->get('doctrine')->getManager();

        $second = $this->generatedIds($this->createOneOfEach($em));

        self::assertSame($first, $second);
    }

    /**
     * The foreign key checks are disabled for the purge, they have to be enabled again afterward,
     * else the test itself would run without them.
     */
    #[Env('DB_CLEANUP_METHOD', 'purge')]
    public function testPurgeRestoresForeignKeyChecks(): void
    {
        self::bootKernel();

        $connection = self::getContainer()->get('doctrine')->getManager()->getConnection();
        $platform = $connection->getDatabasePlatform();
        if (!$platform instanceof MySQLPlatform && !$platform instanceof MariaDBPlatform) {
            self::markTestSkipped('Only MySQL/MariaDB disable the foreign key checks');
        }

        self::assertSame(1, (int) $connection->fetchOne('SELECT @@FOREIGN_KEY_CHECKS'));
    }

    /**
     * @return string[] entity classes of which exactly one record is created by
     *                  createOneOfEach(), each of them backed by its own table
     */
    private function countableClasses(): array
    {
        return [
            TestEntity::class,
            Child::class,
            SuperclassChildA::class,
            SuperclassChildB::class,
            SingleTableChildA::class,
            SingleTableChildB::class,
            JoinedChildA::class,
            JoinedChildB::class,
            AssignedIdEntity::class,
            CompositeKeyEntity::class,
            ReservedWordEntity::class,
            SelfReferencingEntity::class,
        ];
    }

    /**
     * Creates and flushes one record of every mapped entity.
     *
     * @return array<string, object> the created records, keyed by a stable label
     */
    private function createOneOfEach(EntityManagerInterface $em): array
    {
        $testEntity = new TestEntity();

        $child = new Child();
        $child->testEntity = $testEntity;

        $assigned = new AssignedIdEntity();
        $assigned->id = '3f2504e0-4f89-11d3-9a0c-0305e82c3301';

        $composite = new CompositeKeyEntity();
        $composite->keyPartOne = 'one';
        $composite->keyPartTwo = 'two';

        // a record referencing itself can only be purged with the foreign key checks disabled, no
        // table order helps here
        $selfReferencing = new SelfReferencingEntity();
        $selfReferencing->parent = $selfReferencing;

        $records = [
            'testEntity'        => $testEntity,
            'child'             => $child,
            'superclassChildA'  => new SuperclassChildA(),
            'superclassChildB'  => new SuperclassChildB(),
            'singleTableChildA' => new SingleTableChildA(),
            'singleTableChildB' => new SingleTableChildB(),
            'joinedChildA'      => new JoinedChildA(),
            'joinedChildB'      => new JoinedChildB(),
            'reservedWord'      => new ReservedWordEntity(),
            'assignedId'        => $assigned,
            'compositeKey'      => $composite,
            'selfReferencing'   => $selfReferencing,
        ];

        foreach ($records as $record) {
            $em->persist($record);
        }

        $em->flush();

        return $records;
    }

    /**
     * Returns the list of (autoincrement) IDs for the given list of entities.
     *
     * @param array<string, object> $records
     *
     * @return array<string, int>
     */
    private function generatedIds(array $records): array
    {
        $ids = [];
        foreach ($records as $label => $record) {
            if (property_exists($record, 'id') && \is_int($record->id)) {
                $ids[$label] = $record->id;
            }
        }

        return $ids;
    }
}
