<?php

declare(strict_types=1);

namespace Vrok\SymfonyAddons\PHPUnit;

use ApiPlatform\Core\Bridge\Symfony\Bundle\Test\ApiTestCase;
use Symfony\Contracts\HttpClient\ResponseInterface;


// @todo remove whit support for ApiPlatform < 3.0
if (class_exists('ApiPlatform\Symfony\Bundle\Test\ApiTestCase')) {
    abstract class BaseTestCase extends \ApiPlatform\Symfony\Bundle\Test\ApiTestCase
    { }
}
else {
    abstract class BaseTestCase extends \ApiPlatform\Core\Bridge\Symfony\Bundle\Test\ApiTestCase
    { }
}

/**
 * Helper class that contains often used functionality to simplify testing
 * API endpoints.
 */
abstract class ApiPlatformTestCase extends BaseTestCase
{
    use AuthenticatedClientTrait;
    use RefreshDatabaseTrait;

    protected const UNAUTHENTICATED_RESPONSE = [
        'code'    => 401,
        'message' => 'JWT Token not found',
    ];

    protected const ERROR_RESPONSE = [
        '@context'    => '/contexts/Error',
        '@type'       => 'hydra:Error',
        'hydra:title' => 'An error occurred',
        // 'hydra:description' varies
    ];

    protected const UNAUTHORIZED_RESPONSE = self::ERROR_RESPONSE + [
        'hydra:description' => 'Access Denied.',
    ];

    protected const NOT_FOUND_RESPONSE = self::ERROR_RESPONSE + [
        'hydra:description' => 'Not Found',
    ];

    protected const ACCESS_BLOCKED_RESPONSE = self::ERROR_RESPONSE + [
        'hydra:description' => 'failure.accessBlocked',
    ];

    protected const CONSTRAINT_VIOLATION_RESPONSE = [
        '@context'          => '/contexts/ConstraintViolationList',
        '@type'             => 'ConstraintViolationList',
        'hydra:title'       => 'An error occurred',
        // 'hydra:description' varies
    ];

    /**
     * The params *must* contain either 'iri' or 'uri', all other settings are
     * optional.
     *
     * uri:            the endpoint to call, e.g. '/tenants'
     * iri:            [classname, [field => value]],
     *                 e.g. [User::class, [email => 'test@test.de']]
     *                 tries to find a User by the given conditions and
     *                 retrieves its IRI, it is then used as URI
     * basicAuth:      if given, sets the given credentials [username, password]
     *                 as HTTP basic authentication header
     * email:          if given, tries to find a User with that email and sends
     *                 the request authenticated as this user
     * method:         HTTP method for the request, defaults to GET
     * requestOptions: options for the HTTP client, e.g. query parameters
     * responseCode:   if set asserts that the received status code matches
     * contentType:    if set asserts that the received content type header matches
     * json:           if set assumes that the returned content is JSON and
     *                 contains the given array as subset
     * requiredKeys:   if set asserts the dataset contains the list of keys.
     *                 Used for elements where the value is not known in advance,
     *                 e.g. ID, slug, timestamps. Can be nested:
     *                 ['hydra:member'][0]['id', '@id']
     * forbiddenKeys:  like requiredKeys, but the dataset may not contain those
     * schemaClass:    if set asserts that the received response matches the JSON
     *                 schema for the given class
     * prepare:        callback($containerInterface, &$params) that prepares the
     *                 environment, e.g. creating / deleting entities.
     *                 It is called after the kernel is booted & the database was
     *                 refreshed. Can be used to update the parameters, e.g. with
     *                 IDs/IRIs from the DB.
     */
    protected function testOperation(array $params): ResponseInterface
    {
        $client = null;
        if (isset($params['basicAuth'])) {
            $client = static::createClient([], ['auth_basic' => $params['basicAuth']]);
        }
        elseif (isset($params['email'])) {
            $client = static::createAuthenticatedClient(['email' => $params['email']]);
        }
        else {
            $client = static::createClient();
        }

        // called after createClient as this forces the kernel boot which in
        // turn refreshes the database
        if (isset($params['prepare'])) {
            $params['prepare'](static::getContainer(), $params);
        }

        if (isset($params['iri'])) {
            $params['uri'] = $this->findIriBy($params['iri'][0], $params['iri'][1]);
        }

        $response = $client->request(
            $params['method'] ?? 'GET',
            $params['uri'],
            $params['requestOptions'] ?? [],
        );

        if (isset($params['responseCode'])) {
            self::assertResponseStatusCodeSame($params['responseCode']);
        }

        if (isset($params['contentType'])) {
            self::assertResponseHeaderSame('content-type', $params['contentType']);
        }

        if (isset($params['json'])) {
            self::assertJsonContains($params['json']);
        }

        if (isset($params['requiredKeys'])
            || isset($params['forbiddenKeys'])
        ) {
            $dataset = $response->toArray(false);
            self::assertArrayHasNestedKeys(
                $params['requiredKeys'] ?? [], $dataset);
            self::assertDatasetNotHasKeys(
                $params['forbiddenKeys'] ?? [], $dataset);
        }

        if (isset($params['schemaClass'])) {
            if (isset($params['iri'])) {
                self::assertMatchesResourceItemJsonSchema($params['schemaClass']);
            } else {
                self::assertMatchesResourceCollectionJsonSchema($params['schemaClass']);
            }
        }

        return $response;
    }

    public static function tearDownAfterClass(): void
    {
        self::fixtureCleanup();
    }

    /**
     * Asserts that the given dataset $array does contain the list of $expected
     * keys. The keys may be nested.
     *
     * @param array  $expected list of keys to check:
     *                         ['hydra:member'][0]['id', '@id', 'slug']
     * @param array  $array    the dataset to verify
     * @param string $parent   auto-set when called recursively
     */
    public static function assertArrayHasNestedKeys(array $expected, array $array, string $parent = ''): void
    {
        foreach ($expected as $index => $value) {
            if (is_array($value)) {
                self::assertArrayHasKey($index, $array, "Dataset does not have key {$parent}[$index]!");
                self::assertIsArray($array[$index], "Key {$parent}[$index] is expected to be an array!");
                self::assertArrayHasNestedKeys($value, $array[$index], "{$parent}[$index]");
            } else {
                self::assertArrayHasKey($value, $array, "Dataset does not have key {$parent}[$value]!");
            }
        }
    }

    /**
     * Asserts that the given dataset $array does *not* contain the list of
     * $expected keys. The keys may be nested.
     *
     * @param array  $expected list of keys to check:
     *                         ['hydra:member'][0]['internal', 'hidden', 'private']
     * @param array  $array    the dataset to verify
     * @param string $parent   auto-set when called recursively
     */
    public static function assertDatasetNotHasKeys(array $expected, array $array, string $parent = ''): void
    {
        foreach ($expected as $index => $value) {
            if (is_array($value)) {
                // the parent key does not exist / is null -> silently skip the child keys
                if (!isset($array[$index])) {
                    continue;
                }
                self::assertIsArray($array[$index], "Key {$parent}[$index] is expected to be an array!");
                self::assertDatasetNotHasKeys($value, $array[$index], "{$parent}[$index]");
            } else {
                self::assertArrayNotHasKey($value, $array, "Dataset should not have key {$parent}[$value]!");
            }
        }
    }
}
