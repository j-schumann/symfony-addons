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

    // currently (2023-11-11) returned with ApiPlatform 3.1 + 3.2
    protected const UNAUTHENTICATED_RESPONSE = [
        'code'    => 401,
        'message' => 'JWT Token not found',
    ];

    // region Hydra results returned by ApiPlatform <= 3.1
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
    // endregion

    // region constants for ApiPlatform >= 3.2
    // this should be returned for RFC 7807 compliant errors
    public const PROBLEM_CONTENT_TYPE = 'application/problem+json; charset=utf-8';

    public const PROBLEM_400 = [
        // 'detail' => 'The key "username" must be provided.', // varies
        'status' => 400,
        'title'  => 'An error occurred',
        'type'   => '/errors/400',
    ];
    public const PROBLEM_403 = [
        // 'detail' => 'Filter "locked" is forbidden for non-admins!', // varies
        'status' => 403,
        'title'  => 'An error occurred',
        'type'   => '/errors/403',
    ];
    public const PROBLEM_ACCESS_DENIED = [
        'detail' => 'Access Denied.',
        'status' => 403,
        'title'  => 'An error occurred',
        'type'   => '/errors/403',
    ];
    public const PROBLEM_404 = [
        // 'detail' => 'No route found for "GET http://localhost/proposals"', // varies
        'status' => 404,
        'title'  => 'An error occurred',
        'type'   => 'https://tools.ietf.org/html/rfc2616#section-10',
    ];
    public const PROBLEM_NOT_FOUND = [
        'detail' => 'Not Found',
        'status' => 404,
        'title'  => 'An error occurred',
        'type'   => '/errors/404',
    ];
    public const PROBLEM_405 = [
        // 'detail' => 'No route found for "PATCH [...]": Method Not Allowed (Allow: GET)', // varies
        'status' => 405,
        'title'  => 'An error occurred',
        // 'type'   => 'https://tools.ietf.org/html/rfc2616#section-10', // varies
    ];
    public const PROBLEM_422 = [
        // 'detail' => 'description: validate.general.tooShort', // varies
        'status'     => 422,
        'title'      => 'An error occurred',
        // 'type' => '/validation_errors/9ff3fdc4-b214-49db-8718-39c315e33d45', // varies
        'violations' => [
            // varying list of violations:
            // [
            //    'propertyPath' => 'description',
            //    'message' => 'validate.general.tooShort',
            //    'code' => '9ff3fdc4-b214-49db-8718-39c315e33d45',
            // ],
        ],
    ];
    public const PROBLEM_500 = [
        // 'detail' => 'platform.noDefaultRatePlan', // varies
        'status' => 500,
        'title'  => 'An error occurred',
        'type'   => '/errors/500',
    ];

    public const HYDRA_PROBLEM_400 = [
        '@id'         => '/errors/400',
        '@type'       => 'hydra:Error',
        // 'hydra:description' => '"name" is required', // varies
        'hydra:title' => 'An error occurred',
    ] + self::PROBLEM_400;
    public const HYDRA_PROBLEM_403 = [
        '@id'               => '/errors/403',
        '@type'             => 'hydra:Error',
        'hydra:description' => '@todo',
        'hydra:title'       => 'An error occurred',
    ] + self::PROBLEM_403;
    public const HYDRA_PROBLEM_ACCESS_DENIED = [
        '@id'               => '/errors/403',
        '@type'             => 'hydra:Error',
        'hydra:description' => 'Access Denied.',
        'hydra:title'       => 'An error occurred',
    ] + self::PROBLEM_ACCESS_DENIED;
    public const HYDRA_PROBLEM_404 = [
        '@id'               => '/errors/404',
        '@type'             => 'hydra:Error',
        // 'hydra:description' => 'This route does not aim to be called.', // varies
        'hydra:title'       => 'An error occurred',
        'type'              => '/errors/404',
    ] + self::PROBLEM_404;
    public const HYDRA_PROBLEM_NOT_FOUND = [
        '@id'               => '/errors/404',
        '@type'             => 'hydra:Error',
        'hydra:description' => 'Not Found',
        'hydra:title'       => 'An error occurred',
        ] + self::PROBLEM_NOT_FOUND;
    public const HYDRA_PROBLEM_405 = [
        '@id'               => '/errors/405',
        '@type'             => 'hydra:Error',
        // 'hydra:description' => 'No route found for "GET [...]": Method Not Allowed (Allow: POST)', // varies
        'hydra:title'       => 'An error occurred',
        'type'              => '/errors/405',
    ] + self::PROBLEM_405;
    public const HYDRA_PROBLEM_422 = [
        // '@id' => '/validation_errors/9ff3fdc4-b214-49db-8718-39c315e33d45', // varies
        '@type'       => 'ConstraintViolationList',
        // 'hydra:description' => 'description: validate.general.tooShort', // varies
        'hydra:title' => 'An error occurred',
    ] + self::PROBLEM_422;
    public const HYDRA_PROBLEM_500 = [
        // '@id'               => '/errors/500', // varies
        '@type'             => 'hydra:Error',
        // 'hydra:description' => 'platform.noDefaultRatePlan', // varies
        'hydra:title'       => 'An error occurred',
    ] + self::PROBLEM_500;
    // endregion

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
     * postFormAuth:       if given together with 'email', sends the JWT as
     *                     'application/x-www-form-urlencoded' request in the
     *                     given field name
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

            if ($params['postFormAuth'] ?? false) {
                $client->setDefaultOptions([
                    'headers' => [
                        'content-type' => 'application/x-www-form-urlencoded',
                    ],
                    'extra'   => [
                        'parameters' => [
                            $params['postFormAuth'] => $token,
                        ],
                    ],
                ]);
            } else {
                $client->setDefaultOptions([
                    'headers' => [
                        'Authorization' => sprintf('Bearer %s', $token),
                    ],
                ]);
            }
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

    // @todo exists in the parent ApiTestCase since ?. Cannot overwrite a
    // non-static method with a static one -> we would need a new name
    protected function getIriFromResource(object $resource): string
    {
        /** @var IriConverterInterface $iriConverter */
        $iriConverter = static::getContainer()->get('api_platform.iri_converter');

        return $iriConverter->getIriFromResource($resource);
    }
}
