<?php

namespace Vrok\SymfonyAddons\Tests\Helpers;

use Doctrine\DBAL\Platforms\PostgreSQLPlatform;

/**
 * Requests the collection of the TestEntity, whose #[ApiResource] configures the filters as
 * #[QueryParameter], see the TestFixtures for the records.
 */
trait FilterRequestTrait
{
    /**
     * Returns the found records.
     */
    private function search(string $query, array $createdLogs = []): array
    {
        return $this->testOperation(
            uri: '/test_entities'.$query,
            requestOptions: ['headers' => ['Accept' => 'application/json']],
            responseCode: 200,
            createdLogs: $createdLogs,
        )->toArray();
    }

    /**
     * Returns the query parameters documented in the OpenAPI specification for the collection of
     * the TestEntity, indexed by name.
     *
     * @return array<string, array<string, mixed>>
     */
    private function getOpenApiParameters(): array
    {
        $response = $this->testOperation(
            uri: '/docs',
            requestOptions: ['headers' => ['Accept' => 'application/vnd.openapi+json']],
            responseCode: 200,
        );

        $parameters = $response->toArray()['paths']['/test_entities']['get']['parameters'] ?? [];

        return array_column($parameters, null, 'name');
    }

    private function skipUnlessPostgres(): void
    {
        $platform = self::getContainer()->get('doctrine')->getConnection()->getDatabasePlatform();
        if (!$platform instanceof PostgreSQLPlatform) {
            self::markTestSkipped('The filter uses Postgres-only JSON operators.');
        }
    }
}
