<?php

namespace Vrok\SymfonyAddons\PHPUnit;

use Doctrine\Common\DataFixtures\Executor\ORMExecutor;
use Doctrine\Common\DataFixtures\Purger\ORMPurger;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Platforms\MariaDBPlatform;
use Doctrine\DBAL\Platforms\MySQLPlatform;
use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\DBAL\Platforms\SQLitePlatform;
use Doctrine\DBAL\Platforms\SQLServerPlatform;
use Doctrine\DBAL\Schema\SQLiteSchemaManager;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpKernel\KernelInterface;

/**
 * Idea from DoctrineTestBundle & hautelook/AliceBundle: We want to force the test DB to have the
 * current schema and load all test fixtures (group=test) so the DB is in a known state for each
 * test (meaning each time the kernel is booted).
 *
 * static::$fixtureGroups can be customized in setUpBeforeClass()
 *
 * The cleanup method for the database can be overwritten by setting the ENV DB_CLEANUP_METHOD (e.g.
 * in phpunit.xml.dist).
 *
 * "purge" will update the DB schema once and afterward only purges all tables, may require
 * Vrok\DoctrineAddons\DBAL\Platforms\PostgreSQLTestPlatform to cascade the purge. On MySQL/MariaDB
 * and SQLServer the tables are emptied with DELETE instead of TRUNCATE, see DB_PURGE_MODE. Emptying
 * a table does not reset its identity generator on every platform, so this is done separately on
 * all of them, see resetIdentities()
 *
 * "dropSchema" will drop all tables (and indices) and recreate them before each test, use this when
 * a test requires a genuinely fresh schema.
 *
 * "dropDatabase" will drop the entire database and recreate it before each test.
 *
 * Per booted kernel "purge" only empties the tables, where both others drop and recreate the whole
 * schema, so it is the one to use unless a test really needs a fresh schema. See the README for
 * measurements and for when the difference matters.
 *
 * With the cleanup method "purge", the ENV DB_PURGE_MODE selects how the tables are emptied on
 * MySQL/MariaDB. The default is "delete": on InnoDB, TRUNCATE is a DDL operation that drops and
 * recreates the tablespace file, which is paid once per table and per test and dominates the
 * runtime of a database-heavy test suite, while DELETE is DML and magnitudes cheaper. Set it to
 * "truncate" to restore the previous behavior, e.g. for tests that insert very large datasets
 * before the cleanup, as DELETE is O(rows) where TRUNCATE is O(1). The setting has no effect on
 * other platforms: SQLServer cannot TRUNCATE tables that are referenced by a foreign key,
 * PostgreSQL and SQLite have no expensive TRUNCATE to avoid.
 */
trait RefreshDatabaseTrait
{
    /**
     * @var string[] allowed values for the ENV DB_CLEANUP_METHOD
     */
    private const array CLEANUP_METHODS = ['purge', 'dropSchema', 'dropDatabase'];

    /**
     * @var string[] allowed values for the ENV DB_PURGE_MODE
     */
    private const array PURGE_MODES = ['delete', 'truncate'];

    /**
     * @var array fixture group(s) to apply
     */
    protected static array $fixtureGroups = ['test'];

    /**
     * @var array|null fixture cache
     */
    protected static ?array $fixtures = null;

    /**
     * @var bool Flag whether the db setup is done (db exists, schema is up to
     *           date)
     */
    protected static bool $setupComplete = false;

    /**
     * @var array|null cache for the tables that have an auto-increment column
     */
    private static ?array $identityTables = null;

    /**
     * Called on each test that calls bootKernel() or uses createClient().
     */
    protected static function bootKernel(array $options = []): KernelInterface
    {
        static::ensureKernelTestCase();

        $kernel = parent::bootKernel($options);
        $container = static::getContainer();
        $entityManager = $container->get('doctrine')->getManager();
        $executor = static::getExecutor($entityManager);

        $cleanupMethod = $_ENV['DB_CLEANUP_METHOD'] ?? 'purge';
        if (!\is_string($cleanupMethod) || !\in_array($cleanupMethod, self::CLEANUP_METHODS, true)) {
            $given = \is_string($cleanupMethod) ? $cleanupMethod : get_debug_type($cleanupMethod);
            throw new \InvalidArgumentException("Unknown DB_CLEANUP_METHOD \"$given\", allowed values are \"".implode('", "', self::CLEANUP_METHODS).'".');
        }

        switch ($cleanupMethod) {
            case 'dropDatabase':
                static::recreateDatabase($entityManager, true);
                static::updateSchema($entityManager);
                break;

            case 'dropSchema':
                if (!static::$setupComplete) {
                    static::recreateDatabase($entityManager);
                    static::$setupComplete = true;
                }

                static::updateSchema($entityManager, true);
                break;

            case 'purge':
                // only required on the first test: make sure the db exists and the schema is up to
                // date
                if (!static::$setupComplete) {
                    static::recreateDatabase($entityManager);
                    static::updateSchema($entityManager);
                    static::$setupComplete = true;
                }

                $connection = $executor->getObjectManager()->getConnection();
                $platform = $connection->getDatabasePlatform();
                $isMysql = $platform instanceof MySQLPlatform || $platform instanceof MariaDBPlatform;

                // In MySQL/MariaDB we need to disable foreign key checks, as the automatic table
                // ordering does not help us when we have self-referencing tables.
                if ($isMysql) {
                    $connection->executeStatement('SET FOREIGN_KEY_CHECKS = 0');
                }

                // In SQLServer, TRUNCATE does not work with foreign keys, also using "EXEC
                // sp_MSforeachtable 'ALTER TABLE ? NOCHECK CONSTRAINT ALL'" does not help here. In
                // MySQL/MariaDB, TRUNCATE is a DDL operation: InnoDB drops and recreates the
                // tablespace file, for each table and each test, which is far more expensive than
                // emptying the tables with DELETE (@see DB_PURGE_MODE above). So we switch to
                // simple delete on both platforms, but this requires us to manually reset
                // auto-increments afterward.
                $purgeMode = $_ENV['DB_PURGE_MODE'] ?? 'delete';
                if (!\is_string($purgeMode) || !\in_array($purgeMode, self::PURGE_MODES, true)) {
                    $given = \is_string($purgeMode) ? $purgeMode : get_debug_type($purgeMode);
                    throw new \InvalidArgumentException("Unknown DB_PURGE_MODE \"$given\", allowed values are \"".implode('", "', self::PURGE_MODES).'".');
                }

                $purgeWithDelete = $platform instanceof SQLServerPlatform
                    || ($isMysql && 'truncate' !== $purgeMode);

                if ($purgeWithDelete) {
                    $executor->getPurger()->setPurgeMode(ORMPurger::PURGE_MODE_DELETE);
                }

                // Purge even when no fixtures are defined, e.g., for tests that require an empty
                // database, like import tests. Fix for PHP8: purge separately from inserting the
                // fixtures, as execute() would wrap the TRUNCATE in a transaction which MySQL
                // auto-commits when DDL queries are executed, which throws an exception in the
                // entityManager ("There is no active
                // transaction", @see https://github.com/doctrine/migrations/issues/1104)
                // because he does not check if a transaction is still open before calling commit().
                $executor->purge();

                // Emptying the tables does not necessarily reset their identity generators, but
                // tests may rely on the generated IDs (e.g. when asserting on IRIs like /items/1),
                // so we reset them ourselves. Only a TRUNCATE on MySQL/MariaDB does it for us. This
                // has to happen here, after the purge and before the fixtures are loaded: ALTER
                // TABLE is DDL and triggers MySQLs implicit commit, it must not run within a
                // transaction.
                if (!$isMysql || $purgeWithDelete) {
                    static::resetIdentities($entityManager, $platform);
                }

                // Restore the default: the checks were only disabled for the purge, the fixtures
                // and the test itself should run with the same settings as the application, e.g.
                // for tests that assert that a foreign key violation occurs.
                if ($isMysql) {
                    $connection->executeStatement('SET FOREIGN_KEY_CHECKS = 1');
                }

                break;
        }

        // now load any fixtures configured for "test" (or overwritten groups)
        $fixtures = static::getFixtures($container);
        if ([] !== $fixtures) {
            $executor->execute($fixtures, true);
        }

        return $kernel;
    }

    protected static function ensureKernelTestCase(): void
    {
        if (!is_a(static::class, KernelTestCase::class, true)) {
            throw new \LogicException(\sprintf('The test class must extend "%s" to use "%s".', KernelTestCase::class, static::class));
        }
    }

    /**
     * (Drops and re-) creates the (test) database if it does not exist. This code tries to
     * duplicate the behavior of the doctrine:database:drop / doctrine:schema:create commands in the
     * DoctrineBundle.
     *
     * @param bool $drop if true, the method deletes an existing database before recreating
     *                   it, else the database is only created when it does not exist
     */
    protected static function recreateDatabase(
        EntityManagerInterface $em,
        bool $drop = false,
    ): void {
        $connection = $em->getConnection();
        $params = $params['primary'] ?? $connection->getParams();

        // this name will already contain the dbname_suffix (and the TEST_TOKEN) if any is
        // configured
        $dbName = $params['path'] ?? $params['dbname'] ?? false;
        if (!$dbName) {
            throw new \RuntimeException("Connection does not contain a 'dbname' or 'path' parameter, don't know how to proceed, aborting.");
        }

        unset($params['dbname'], $params['path']);
        if ($connection->getDatabasePlatform() instanceof PostgreSQLPlatform) {
            $params['dbname'] = $params['default_dbname'] ?? 'postgres';
        }

        $tempConnection = DriverManager::getConnection($params, $connection->getConfiguration());
        $schemaManager = $tempConnection->createSchemaManager();

        // SQLite does not support checking for existing / dropping / creating databases via
        // Doctrine -> special handling here
        if ($schemaManager instanceof SQLiteSchemaManager) {
            if ($drop && file_exists($dbName)) {
                unlink($dbName);
            }

            // the database file will be automatically created on first use, no need to create it
            // here
            return;
        }

        // @todo when DBAL 5.0 comes out: switch to this method, to replace the
        // deprecated/removed listDatabases method $dbExists = self::databaseExists($em, $dbName);
        $dbExists = \in_array($dbName, $schemaManager->listDatabases(), true);

        if ($drop && $dbExists) {
            // close the current connection in the em, it would be invalid anyway after the drop
            $connection->close();

            // For Postgres, closing the old connection is not enough to prevent: 'ERROR: database
            // "db_test" is being accessed by other users'
            if ($tempConnection->getDatabasePlatform() instanceof PostgreSQLPlatform) {
                $tempConnection->executeStatement(
                    'SELECT pg_terminate_backend(pid) FROM pg_stat_activity WHERE datname = ? AND pid <> pg_backend_pid()',
                    [$dbName]
                );
            }

            if ($tempConnection->getDatabasePlatform() instanceof SQLServerPlatform) {
                $tempConnection->executeStatement(
                    "USE master; ALTER DATABASE $dbName SET SINGLE_USER WITH ROLLBACK IMMEDIATE"
                );
            }

            $schemaManager->dropDatabase($dbName);

            $dbExists = false;
        }

        // Create the database only if it doesn't already exist. Skip for SQLite as it creates
        // database files automatically and this call would throw an exception.
        if (!$dbExists && !$schemaManager instanceof SQLiteSchemaManager) {
            $schemaManager->createDatabase($dbName);
        }

        $tempConnection->close();
    }

    /**
     * Brings the db schema to the newest version.
     *
     * @param bool $drop if true, the method drops the current schema first, e.g. to reset
     *                   all data
     */
    protected static function updateSchema(
        EntityManagerInterface $em,
        bool $drop = false,
    ): void {
        $metadatas = $em->getMetadataFactory()->getAllMetadata();
        if ([] === $metadatas) {
            return;
        }

        $schemaTool = new SchemaTool($em);

        if ($drop) {
            // The method name is misleading; it only drops the elements within the database, not
            // the db itself...
            $schemaTool->dropDatabase();
        }

        $schemaTool->updateSchema($metadatas);
    }

    /**
     * Use a static fixture cache as we need them before each test.
     */
    protected static function getFixtures(ContainerInterface $container): array
    {
        if ([] === static::$fixtureGroups) {
            // the fixture loader returns all possible fixtures if called with an empty array ->
            // catch here
            return [];
        }

        if (\is_array(static::$fixtures)) {
            return static::$fixtures;
        }

        $fixturesLoader = $container->get('doctrine.fixtures.loader');
        static::$fixtures = $fixturesLoader->getFixtures(static::$fixtureGroups);

        return static::$fixtures;
    }

    /**
     * Returns a new executor instance, we need it before each test execution.
     */
    protected static function getExecutor(EntityManagerInterface $em): ORMExecutor
    {
        $purger = new ORMPurger($em);
        $purger->setPurgeMode(ORMPurger::PURGE_MODE_TRUNCATE);

        // don't use a static Executor, it contains the EM which could be closed through (expected)
        // exceptions and would not work
        return new ORMExecutor($em, $purger);
    }

    /**
     * Resets the identity generators of all tables that have one, so the records created in the
     * tests always receive the same IDs.
     *
     * Must be called after the purge and before the fixtures are loaded.
     */
    protected static function resetIdentities(
        EntityManagerInterface $em,
        AbstractPlatform $platform,
    ): void {
        $identityTables = static::getIdentityTables($em, $platform);
        if ([] === $identityTables) {
            return;
        }

        $connection = $em->getConnection();

        if ($platform instanceof MySQLPlatform || $platform instanceof MariaDBPlatform) {
            foreach (array_keys($identityTables) as $table) {
                // the value is clamped to MAX(id) + 1, so this is a no-op for a table that was
                // excluded from the purge and still has rows
                $connection->executeStatement("ALTER TABLE $table AUTO_INCREMENT = 1");
            }

            return;
        }

        if ($platform instanceof PostgreSQLPlatform) {
            // TRUNCATE keeps the sequences, only "TRUNCATE ... RESTART IDENTITY" would reset them,
            // @see Vrok\DoctrineAddons\DBAL\Platforms\PostgreSQLTestPlatform
            foreach ($identityTables as $table => $meta) {
                // is_called = false lets the next record use the value itself. The table name is
                // passed quoted, as pg_get_serial_sequence treats its first argument as a
                // (potentially qualified) name and would lowercase an unquoted one.
                $connection->executeQuery(
                    'SELECT setval(pg_get_serial_sequence(?, ?), 1, false)',
                    [$table, $meta['column']]
                );
            }

            return;
        }

        if ($platform instanceof SQLServerPlatform) {
            foreach (array_keys($identityTables) as $table) {
                // DBCC CHECKIDENT makes the *next* record use RESEED + 1, but only for a table that
                // was inserted into before: for a table that was never used, the first record uses
                // the reseed value itself, which would be 0. So we only reseed tables that have a
                // current identity value.
                $connection->executeStatement(
                    'DECLARE @table NVARCHAR(776) = ?;
                     IF EXISTS (
                         SELECT 1 FROM sys.identity_columns
                         WHERE object_id = OBJECT_ID(@table) AND last_value IS NOT NULL
                     )
                         DBCC CHECKIDENT (@table, RESEED, 0) WITH NO_INFOMSGS',
                    [$table]
                );
            }

            return;
        }

        if ($platform instanceof SQLitePlatform) {
            // SQLite stores the counters of all AUTOINCREMENT columns in this table, it only exists
            // when at least one such column is used
            $sequenceTableExists = (bool) $connection->fetchOne(
                "SELECT COUNT(*) FROM sqlite_master WHERE type = 'table' AND name = 'sqlite_sequence'"
            );
            if (!$sequenceTableExists) {
                return;
            }

            foreach ($identityTables as $meta) {
                // the counters are stored with the unquoted table name
                $connection->executeStatement(
                    'DELETE FROM sqlite_sequence WHERE name = ?',
                    [$meta['name']]
                );
            }
        }
    }

    /**
     * Returns all tables that have an identity / auto-increment column, in the order in which the
     * mapping defines them.
     *
     * The list is derived from the ORM metadata and not from the database: The trait creates the
     * schema itself, from that same metadata (@see updateSchema()), and the SchemaTool marks a
     * column as auto-increment exactly when the entity uses the IDENTITY generator for a
     * single-field identifier, so both are in sync by construction. We apply the same filters the
     * ORMPurger uses to build its list of tables.
     *
     * Tables that are excluded from the purge (via the ORMPurgers $excluded or a schema assets
     * filter) are not filtered out here, they are only reset together with all others.
     *
     * The result is cached, but as this is a trait, the cache is per test class using it and not
     * per process.
     *
     * @return array<string, array{name: string, column: string}>
     */
    protected static function getIdentityTables(
        EntityManagerInterface $em,
        AbstractPlatform $platform,
    ): array {
        if (null !== self::$identityTables) {
            return self::$identityTables;
        }

        $quoteStrategy = $em->getConfiguration()->getQuoteStrategy();
        $tables = [];

        foreach ($em->getMetadataFactory()->getAllMetadata() as $meta) {
            if ($meta->isMappedSuperclass
                || (isset($meta->isEmbeddedClass) && $meta->isEmbeddedClass)
            ) {
                continue;
            }

            // Only the root class of a hierarchy owns the identity column: With SINGLE_TABLE the
            // children share the table of the root, with JOINED they have their own table, but its
            // primary key is a foreign key to the root and not auto-increment. The children inherit
            // generatorType = IDENTITY in both cases,
            // @see ClassMetadataFactory::inheritIdGeneratorMapping()
            if ($meta->name !== $meta->rootEntityName) {
                continue;
            }

            // the conditions under which the SchemaTool sets autoincrement=true
            $identifierFields = $meta->getIdentifierFieldNames();
            if (!$meta->isIdGeneratorIdentity() || 1 !== \count($identifierFields)) {
                continue;
            }

            // multiple entities can be mapped to the same table -> deduplicate
            $tables[$quoteStrategy->getTableName($meta, $platform)] = [
                'name'   => $meta->getTableName(),
                'column' => $meta->getColumnName($identifierFields[0]),
            ];
        }

        return self::$identityTables = $tables;
    }

    /**
     * @todo this method only works w/ DBAL >= 4.4, as the createMetadataProvider
     * is first implemented there. But also schemaManager->listDatabases is only deprecated since
     * 4.4, so we stay with that, until we actually want to support 5.0 w/o listDatabases.
     *
     * Returns the list of databases the current connection sees. Must be called with the "old"
     * entityManager, that has the database name set in its connection, or we will receive "A
     * database is required for the method: Doctrine\DBAL\Platforms\MySQL\MySQLMetadataProvider" on
     * MariaDB and MySQL.
     */
    private static function databaseExists(EntityManagerInterface $em, string $dbName): bool
    {
        // We cannot use schemaManager->introspectDatabaseNames, as this would require the
        // $tempConnection to have a DB name set for MySQL/MariaDB. But we can't set one, as it
        // would fail when the database indeed does not exist, that's why we created the
        // $tempConnection in the first place. All was working well when
        // schemaManager->listDatabases was not yet deprecated...
        $connection = $em->getConnection();

        // So instead, we create the provider manually with the old connection, in the hope that
        // this works even with the EM closed from previous test run exceptions...
        $metaProvider = $connection->getDatabasePlatform()
            ->createMetadataProvider($connection);

        $dbNames = array_map(
            static fn ($n) => $n->getDatabaseName(),
            iterator_to_array($metaProvider->getAllDatabaseNames())
        );

        return \in_array($dbName, $dbNames, true);
    }

    protected static function fixtureCleanup(): void
    {
        static::$fixtures = null;
    }
}
