<?php

declare(strict_types=1);

namespace Vrok\SymfonyAddons\PHPUnit;

use ApiPlatform\Api\IriConverterInterface;
use ApiPlatform\Symfony\Bundle\Test\ApiTestCase;
use ApiPlatform\Symfony\Bundle\Test\Client;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Contracts\HttpClient\ResponseInterface;

/**
 * Helper class that contains often used functionality to simplify testing
 * API endpoints.
 */
abstract class ApiPlatformTestCase extends ApiTestCase
{
    use AuthenticatedClientTrait;
    use MonologAssertsTrait;
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
        '@context'    => '/contexts/ConstraintViolationList',
        '@type'       => 'ConstraintViolationList',
        'hydra:title' => 'An error occurred',
        // 'hydra:description' varies
    ];

    protected static ?Client $httpClient = null;

    /**
     * The params *must* contain either 'iri' or 'uri', all other settings are
     * optional.
     *
     * uri:                the endpoint to call, e.g. '/tenants'
     * iri:                [classname, [field => value]],
     *                     e.g. [User::class, [email => 'test@test.de']]
     *                     tries to find an entity by the given conditions and
     *                     retrieves its IRI, it is then used as URI
     * prepare:            callback($containerInterface, &$params) that prepares the
     *                     environment, e.g. creating / deleting entities.
     *                     It is called after the kernel is booted & the database was
     *                     refreshed. Can be used to update the parameters, e.g. with
     *                     IDs/IRIs from the DB.
     * email:              if given, tries to find a User with that email and sends
     *                     the request authenticated as this user with lexikJWT
     * method:             HTTP method for the request, defaults to GET
     * requestOptions:     options for the HTTP client, e.g. query parameters or
     *                     basic auth
     * files:              array of files to upload
     * responseCode:       asserts that the received status code matches
     * contentType:        asserts that the received content type header matches
     * json:               asserts that the returned content is JSON and
     *                     contains the given array as subset
     * requiredKeys:       asserts the dataset contains the list of keys.
     *                     Used for elements where the value is not known in advance,
     *                     e.g. ID, slug, timestamps. Can be nested:
     *                     ['hydra:member'][0]['id', '@id']
     * forbiddenKeys:      like requiredKeys, but the dataset may not contain those
     * schemaClass:        asserts that the received response matches the JSON
     *                     schema for the given class
     * createdLogs:        array of ["log message", LogLevel] entries, asserts the
     *                     messages to be present in the monolog handlers after the
     *                     operation ran
     * emailCount:         asserts this number of emails to be sent via the
     *                     mailer after the operation was executed
     * messageCount:       asserts this number of messages to be dispatched
     *                     to the message bus
     * dispatchedMessages: array of message classes, asserts that at least one instance
     *                     of each given class has been dispatched to the message bus
     * skipRefresh:        if true the database will not be refreshed before
     *                     the operation, to allow calling testOperation()
     *                     multiple times after each other in one testcase
     */
    protected function testOperation(array $params): ResponseInterface
    {
        // in some cases we want two testOperations to be executed in one
        // testcase after each other, without refreshing the database. But
        // we cannot separate booting the kernel / refreshing from creating
        // the TestClient because of all the private methods and client properties
        // in ApiTestCase. So we have to keep our own reference to the client
        // to be able to re-use it, to keep the assertion methods working
        if (self::$httpClient && ($params['skipRefresh'] ?? false)) {
            $client = self::$httpClient;
        } else {
            $client = static::$httpClient = static::createClient();
        }

        if (isset($params['email'])) {
            $token = static::getJWT(static::getContainer(), ['email' => $params['email']]);
            $client->setDefaultOptions([
                'headers' => [
                    'Authorization' => sprintf('Bearer %s', $token),
                ],
            ]);
        }

        // called after createClient as this forces the kernel boot which in
        // turn refreshes the database
        if (isset($params['prepare'])) {
            $params['prepare'](static::getContainer(), $params);
        }

        if (isset($params['iri'])) {
            $params['uri'] = $this->findIriBy($params['iri'][0], $params['iri'][1]);
        }

        if (isset($params['createdLogs'])) {
            self::prepareLogger();
        }

        $params['method'] ??= 'GET';
        $params['requestOptions'] ??= [];

        if (isset($params['files'])) {
            $params['requestOptions']['extra']['files'] ??= [];
            $params['requestOptions']['headers']['content-type'] ??= 'multipart/form-data';

            foreach ($params['files'] as $key => $fileData) {
                $params['requestOptions']['extra']['files'][$key] =
                    static::prepareUploadedFile(
                        $fileData['path'],
                        $fileData['originalName'],
                        $fileData['mimeType'],
                    );
            }
        }

        if ('PATCH' === $params['method']) {
            $params['requestOptions']['headers']['content-type'] ??= 'application/merge-patch+json';
        }

        $response = $client->request(
            $params['method'],
            $params['uri'],
            $params['requestOptions'],
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

            self::assertDatasetHasKeys(
                $params['requiredKeys'] ?? [], $dataset);
            self::assertDatasetNotHasKeys(
                $params['forbiddenKeys'] ?? [], $dataset);
        }

        if (isset($params['schemaClass'])) {
            if (isset($params['iri']) || 'GET' !== $params['method']) {
                self::assertMatchesResourceItemJsonSchema($params['schemaClass']);
            } else {
                self::assertMatchesResourceCollectionJsonSchema($params['schemaClass']);
            }
        }

        if (isset($params['createdLogs'])) {
            foreach ($params['createdLogs'] as $createdLog) {
                self::assertLoggerHasMessage($createdLog[0], $createdLog[1]);
            }
        }

        if (isset($params['emailCount'])) {
            self::assertEmailCount($params['emailCount']);
        }

        if (isset($params['messageCount'])
            || isset($params['dispatchedMessages'])
        ) {
            $messenger = static::getContainer()->get('messenger.default_bus');
            $messages = $messenger->getDispatchedMessages();

            if (isset($params['messageCount'])) {
                $expected = $params['messageCount'];
                $found = count($messages);
                self::assertSame($expected, $found,
                    "Expected $expected messages to be dispatched, found $found");
            }

            if (isset($params['dispatchedMessages'])) {
                foreach ($params['dispatchedMessages'] as $message) {
                    $messageCallback = null;

                    if (is_array($message)
                        && 2 === count($message)
                        && is_string($message[0])
                        && is_callable($message[1])
                    ) {
                        $messageClass = $message[0];
                        $messageCallback = $message[1];
                    } elseif (is_string($message)) {
                        $messageClass = $message;
                    } else {
                        $error = 'Entries of "dispatchedMessages" must either be a string representing '
                            .'the FQN of the message class or an array with two elements: '
                            .'first the message class FQN and second a callable that will be called '
                            .'with the message object for inspection and the API response data';
                        throw new \InvalidArgumentException($error);
                    }

                    $filtered = array_filter(
                        $messages,
                        static fn ($ele) => is_a($ele['message'], $messageClass)
                    );
                    self::assertGreaterThan(0, count($filtered),
                        "The expected '$messageClass' was not dispatched");

                    if ($messageCallback) {
                        foreach ($filtered as $msg) {
                            $messageCallback($msg['message'], $response->toArray(false));
                        }
                    }
                }
            }
        }

        return $response;
    }

    /**
     * Creates a copy of the file given via $path and returns a UploadedFile
     * to be given to testOperation() / the kernelBrowser to unit test
     * file uploads.
     */
    public static function prepareUploadedFile(
        string $path,
        string $originalName,
        string $mimeType
    ): UploadedFile {
        // don't directly use the given file as the upload handler will
        // most probably move or delete the received file -> copy to temp file
        $filename = tempnam(sys_get_temp_dir(), __METHOD__);
        copy($path, $filename);

        return new UploadedFile(
            $filename,
            $originalName,
            $mimeType,
            null,
            true
        );
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
    public static function assertDatasetHasKeys(array $expected, array $array, string $parent = ''): void
    {
        foreach ($expected as $index => $value) {
            if (is_array($value)) {
                self::assertArrayHasKey($index, $array, "Dataset does not have key {$parent}[$index]!");
                self::assertIsArray($array[$index], "Key {$parent}[$index] is expected to be an array!");
                self::assertDatasetHasKeys($value, $array[$index], "{$parent}[$index]");
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
                self::assertIsArray($array[$index], "Key {$parent}[$index] is expected to be an array or null!");
                self::assertDatasetNotHasKeys($value, $array[$index], "{$parent}[$index]");
            } else {
                self::assertArrayNotHasKey($value, $array, "Dataset should not have key {$parent}[$value]!");
            }
        }
    }

    protected function getIriFromResource(object $item): string
    {
        /** @var IriConverterInterface $iriConverter */
        $iriConverter = static::getContainer()->get('api_platform.iri_converter');

        return $iriConverter->getIriFromResource($item);
    }
}
