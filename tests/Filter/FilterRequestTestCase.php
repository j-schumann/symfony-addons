<?php

/** @noinspection PhpUnhandledExceptionInspection */

namespace Vrok\SymfonyAddons\Tests\Filter;

use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Vrok\SymfonyAddons\PHPUnit\BaseApiTestCase;

/**
 * Base for tests that use the filters through the API Platform request
 * handling, to verify the wiring of the filters, not only their queries.
 */
abstract class FilterRequestTestCase extends BaseApiTestCase
{
    protected static ?bool $alwaysBootKernel = false;

    /**
     * @return list<class-string> the entities to (re-)create the tables for
     */
    abstract protected function getEntityClasses(): array;

    /**
     * Persists the records the tests search for.
     */
    abstract protected function createRecords(EntityManagerInterface $em): void;

    protected function getKernelEnvironment(): string
    {
        return 'test';
    }

    protected function setUp(): void
    {
        parent::setUp();

        self::bootKernel(['environment' => $this->getKernelEnvironment()]);
        $em = $this->getEntityManager();

        $tool = new SchemaTool($em);
        $classes = array_map($em->getClassMetadata(...), $this->getEntityClasses());
        $tool->dropSchema($classes);
        $tool->createSchema($classes);

        $this->createRecords($em);
        $em->flush();
        $em->clear();
    }

    protected function getEntityManager(): EntityManagerInterface
    {
        return self::getContainer()->get('doctrine')->getManager();
    }

    protected function skipUnlessPostgres(): void
    {
        $platform = $this->getEntityManager()->getConnection()->getDatabasePlatform();
        if (!$platform instanceof PostgreSQLPlatform) {
            self::markTestSkipped('The filter uses Postgres-only JSON operators.');
        }
    }

    /**
     * Requests the collection and returns the found records.
     */
    protected function requestCollection(string $uri): array
    {
        $response = self::createClient()->request('GET', $uri, [
            'headers' => ['Accept' => 'application/json'],
        ]);
        self::assertResponseIsSuccessful();

        return $response->toArray();
    }

    /**
     * Returns the names of the query parameters documented in the OpenAPI
     * specification for the GET operation of the given path.
     *
     * @return list<string>
     */
    protected function getOpenApiParameterNames(string $path): array
    {
        $response = self::createClient()->request('GET', '/docs', [
            'headers' => ['Accept' => 'application/vnd.openapi+json'],
        ]);
        self::assertResponseIsSuccessful();

        $parameters = $response->toArray()['paths'][$path]['get']['parameters'] ?? [];

        return array_column($parameters, 'name');
    }
}
