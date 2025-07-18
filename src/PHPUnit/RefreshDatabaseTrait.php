<?php

declare(strict_types=1);

namespace Vrok\SymfonyAddons\PHPUnit;

use Doctrine\Common\DataFixtures\Executor\ORMExecutor;
use Doctrine\Common\DataFixtures\Purger\ORMPurger;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\DBAL\Schema\SQLiteSchemaManager;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpKernel\KernelInterface;

/**
 * Idea from DoctrineTestBundle & hautelook/AliceBundle:
 * We want to force the test DB to have the current schema and load all test
 * fixtures (group=test) so the DB is in a known state for each test (meaning
 * each time the kernel is booted).
 *
 * static::$fixtureGroups can be customized in setUpBeforeClass()
 *
 * The cleanup method for the database can be overwritten by setting the ENV
 * DB_CLEANUP_METHOD (e.g. in phpunit.xml.dist).
 *
 * "purge" will update the DB schema once and afterward only purges
 *  all tables, may require Vrok\DoctrineAddons\DBAL\Platforms\{Mariadb|PostgreSQL}TestPlatform
 *  to disable foreign keys / cascade purge or reset identities before running.
 *
 * "dropSchema" will drop all tables (and indices) and recreate them before each
 * test, use this for databases that do not support disabling foreign keys like
 * MS SqlServer. From experience, is also much faster than purge, at least on
 * Postgres.
 *
 * "dropDatabase" will drop the entire database and recreate it before each test
 * run, this should be even faster than "dropSchema".
 */
trait RefreshDatabaseTrait
{
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
     * Called on each test that calls bootKernel() or uses createClient().
     */
    protected static function bootKernel(array $options = []): KernelInterface
    {
        static::ensureKernelTestCase();

        $kernel = parent::bootKernel($options);
        $container = static::getContainer();
        $entityManager = $container->get('doctrine')->getManager();
        $executor = static::getExecutor($entityManager);

        switch ($_ENV['DB_CLEANUP_METHOD']) {
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
            default:
                // only required on the first test: make sure the db exists and
                // the schema is up to date
                if (!static::$setupComplete) {
                    static::recreateDatabase($entityManager);
                    static::updateSchema($entityManager);
                    static::$setupComplete = true;
                }

                // Purge even when no fixtures are defined, e.g. for tests that
                // require an empty database, like import tests.
                // Fix for PHP8: purge separately from inserting the fixtures,
                // as execute() would wrap the TRUNCATE in a transaction which
                // is auto-committed by MySQL when DDL queries are executed which
                // throws an exception in the entityManager ("There is no active
                // transaction", @see https://github.com/doctrine/migrations/issues/1104)
                // because he does not check if a transaction is still open
                // before calling commit().
                $executor->purge();

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
     * (Drops and re-) creates the (test) database if it does not exist.
     * This code tries to duplicate the behavior of the doctrine:database:drop
     * / doctrine:schema:create commands in the DoctrineBundle.
     *
     * @param bool $drop If true, the method will delete an existing database
     *                   before recreating it. If false, the database will only
     *                   be created if it doesn't exist.
     */
    protected static function recreateDatabase(
        EntityManagerInterface $em,
        bool $drop = false,
    ): void {
        $connection = $em->getConnection();
        $params = $connection->getParams();
        if (isset($params['primary'])) {
            $params = $params['primary'];
        }

        // this name will already contain the dbname_suffix (and the TEST_TOKEN)
        // if any is configured
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

        // SQLite does not support checking for existing / dropping / creating
        // databases via Doctrine -> special handling here
        if ($schemaManager instanceof SQLiteSchemaManager) {
            if ($drop && file_exists($dbName)) {
                unlink($dbName);
            }

            // the database file will be automatically created on first use,
            // no need to create it here
            return;
        }

        // only check this with the new connection, as it would fail with the
        // old connection when the database indeed does not exist
        $dbExists = \in_array($dbName, $schemaManager->listDatabases());

        if ($drop && $dbExists) {
            // close the current connection in the em, it would be invalid
            // anyway after the drop
            $connection->close();

            // For Postgres, closing the old connection is not
            // enough to prevent: 'ERROR: database "db_test" is being accessed by other users'
            if ($tempConnection->getDatabasePlatform() instanceof PostgreSQLPlatform) {
                $tempConnection->executeStatement(
                    'SELECT pg_terminate_backend(pid) FROM pg_stat_activity WHERE datname = ? AND pid <> pg_backend_pid()',
                    [$dbName]
                );
            }

            $schemaManager->dropDatabase($dbName);
            $dbExists = false;
        }

        // Create the database only if it doesn't already exist. Skip for SQLite
        // as it creates database files automatically and this call would throw
        // an exception.
        if (!$dbExists && !$schemaManager instanceof SQLiteSchemaManager) {
            $schemaManager->createDatabase($dbName);
        }

        $tempConnection->close();
    }

    /**
     * Brings the db schema to the newest version.
     *
     * @param bool $drop If true, the method will drop the current schema, e.g.
     *                   to reset all data, as dropping & recreating the schema
     *                   will often be faster than truncating all tables.
     */
    protected static function updateSchema(
        EntityManagerInterface $em,
        bool $drop = false,
    ): void {
        $metadatas = $em->getMetadataFactory()->getAllMetadata();
        if (empty($metadatas)) {
            return;
        }

        $schemaTool = new SchemaTool($em);

        if ($drop) {
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
            // the fixture loader returns all possible fixtures if called
            // with an empty array -> catch here
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

        // don't use a static Executor, it contains the EM which could be closed
        // through (expected) exceptions and would not work
        return new ORMExecutor($em, $purger);
    }

    protected static function fixtureCleanup(): void
    {
        static::$fixtures = null;
    }
}
