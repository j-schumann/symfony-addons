<?php

declare(strict_types=1);

namespace Vrok\SymfonyAddons\PHPUnit;

use ApiPlatform\Symfony\Bundle\Test\ApiTestCase;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpKernel\Debug\TraceableEventDispatcher;
use Symfony\Contracts\HttpClient\ResponseInterface;

/**
 * Helper class that contains often used functionality to simplify testing
 * API endpoints.
 */
abstract class ApiPlatformTestCase extends ApiTestCase
{
    use MonologAssertsTrait;
    use RefreshDatabaseTrait;

    protected static ?bool $alwaysBootKernel = false;

    /**
     * Used when getting a JWT for the authentication in testOperation().
     * Set in your test classes accordingly.
     */
    protected static string $userClass = '\App\Entity\User';

    // region JSON responses, for ApiPlatform >= 3.2
    protected const UNAUTHENTICATED_RESPONSE = [
        'code'    => 401,
        'message' => 'JWT Token not found',
    ];

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
        // 'type'   => 'https://tools.ietf.org/html/rfc2616#section-10', // varies
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
        '@id'         => '/errors/403',
        '@type'       => 'hydra:Error',
        // 'hydra:description' => '', // varies
        'hydra:title' => 'An error occurred',
    ] + self::PROBLEM_403;
    public const HYDRA_PROBLEM_ACCESS_DENIED = [
        '@id'               => '/errors/403',
        '@type'             => 'hydra:Error',
        'hydra:description' => 'Access Denied.',
        'hydra:title'       => 'An error occurred',
    ] + self::PROBLEM_ACCESS_DENIED;
    public const HYDRA_PROBLEM_404 = [
        '@id'         => '/errors/404',
        '@type'       => 'hydra:Error',
        // 'hydra:description' => 'This route does not aim to be called.', // varies
        'hydra:title' => 'An error occurred',
        'type'        => '/errors/404',
    ] + self::PROBLEM_404;
    public const HYDRA_PROBLEM_NOT_FOUND = [
        '@id'               => '/errors/404',
        '@type'             => 'hydra:Error',
        'hydra:description' => 'Not Found',
        'hydra:title'       => 'An error occurred',
    ] + self::PROBLEM_NOT_FOUND;
    public const HYDRA_PROBLEM_405 = [
        '@id'         => '/errors/405',
        '@type'       => 'hydra:Error',
        // 'hydra:description' => 'No route found for "GET [...]": Method Not Allowed (Allow: POST)', // varies
        'hydra:title' => 'An error occurred',
        'type'        => '/errors/405',
    ] + self::PROBLEM_405;
    public const HYDRA_PROBLEM_422 = [
        // '@id' => '/validation_errors/9ff3fdc4-b214-49db-8718-39c315e33d45', // varies
        '@type'       => 'ConstraintViolationList',
        // 'hydra:description' => 'description: validate.general.tooShort', // varies
        'hydra:title' => 'An error occurred',
    ] + self::PROBLEM_422;
    public const HYDRA_PROBLEM_500 = [
        // '@id'               => '/errors/500', // varies
        '@type'       => 'hydra:Error',
        // 'hydra:description' => 'platform.noDefaultRatePlan', // varies
        'hydra:title' => 'An error occurred',
    ] + self::PROBLEM_500;
    // endregion

    public const SUPPORTED_OPERATION_PARAMS = [
        'contentType',
        'createdLogs',
        'dispatchedEvents',
        'dispatchedMessages',
        'email',
        'emailCount',
        'files',
        'forbiddenKeys',
        'iri',
        'json',
        'messageCount',
        'method',
        'postFormAuth',
        'prepare',
        'requestOptions',
        'requiredKeys',
        'responseCode',
        'schemaClass',
        'uri',
    ];

    /**
     * The params *must* contain either 'iri' or 'uri', all other settings are
     * optional.
     *
     * @param string   $uri                the endpoint to call, e.g. '/tenants'
     * @param string   $iri                [classname, [field => value]],
     *                                     e.g. [User::class, [email => 'test@test.de']]
     *                                     tries to find an entity by the given conditions and
     *                                     retrieves its IRI, it is then used as URI
     * @param callable $prepare            callback($containerInterface, &$params) that prepares the
     *                                     environment, e.g. creating / deleting entities.
     *                                     It is called after the kernel is booted & the database was
     *                                     refreshed. Can be used to update the parameters, e.g. with
     *                                     IDs/IRIs from the DB.
     * @param string   $email              if given, tries to find a User with that email and sends
     *                                     the request authenticated as this user with lexikJWT
     * @param string   $postFormAuth       if given together with 'email', sends the JWT as
     *                                     'application/x-www-form-urlencoded' request in the
     *                                     given field name
     * @param string   $method             HTTP method for the request, defaults to GET
     * @param array    $requestOptions     options for the HTTP client, e.g. query parameters or
     *                                     basic auth
     * @param array    $files              array of files to upload
     * @param ?int     $responseCode       asserts that the received status code matches
     * @param string   $contentType        asserts that the received content type header matches
     * @param array    $json               asserts that the returned content is JSON and
     *                                     contains the given array as subset
     * @param array    $requiredKeys       asserts the dataset contains the list of keys.
     *                                     Used for elements where the value is not known in advance,
     *                                     e.g. ID, slug, timestamps. Can be nested:
     *                                     ['hydra:member'][0]['id', '@id']
     * @param array    $forbiddenKeys      like requiredKeys, but the dataset may not contain those
     * @param string   $schemaClass        asserts that the received response matches the JSON
     *                                     schema for the given class
     * @param array    $createdLogs        array of ["log message", LogLevel] entries, asserts the
     *                                     messages to be present in the monolog handlers after the
     *                                     operation ran
     * @param ?int     $emailCount         asserts this number of emails to be sent via the
     *                                     mailer after the operation was executed
     * @param ?int     $messageCount       asserts this number of messages to be dispatched
     *                                     to the message bus
     * @param array    $dispatchedMessages array of message classes, asserts that at least one instance
     *                                     of each given class has been dispatched to the message bus.
     *                                     Instead of class names the elements can be an array of
     *                                     [classname, callable]. This callback will be called
     *                                     (for each matching message) with the message as first
     *                                     parameter and the returned JSON as second parameter.
     * @param array    $dispatchedEvents   array of event names, asserts that at least one
     *                                     instance of each given event has been dispatched
     */
    protected function testOperation(
        string $uri = '',
        array $iri = [],
        ?callable $prepare = null,
        string $email = '',
        string $postFormAuth = '',
        string $method = 'GET',
        array $requestOptions = [],
        array $files = [],
        ?int $responseCode = null,
        string $contentType = '',
        array $json = [],
        array $requiredKeys = [],
        array $forbiddenKeys = [],
        string $schemaClass = '',
        array $createdLogs = [],
        ?int $emailCount = null,
        ?int $messageCount = null,
        array $dispatchedMessages = [],
        array $dispatchedEvents = [],
    ): ResponseInterface {
        // Save all arguments as an associative array, not only the provided
        // values as an indexed array like func_get_args() would.
        $params = get_defined_vars();

        $client = static::createClient();

        if ('' !== $email) {
            $token = $this->getJWT(['email' => $email]);

            if ('' !== $postFormAuth) {
                $client->setDefaultOptions([
                    'headers' => [
                        'content-type' => 'application/x-www-form-urlencoded',
                    ],
                    'extra'   => [
                        'parameters' => [
                            $postFormAuth => $token,
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

        // Called after createClient(), as this forces the kernel boot, which in
        // turn refreshes the database.
        if ($prepare) {
            $prepare(static::getContainer(), $params);
            extract($params);
        }

        if ([] !== $iri) {
            if ('' !== $uri) {
                throw new \LogicException('Setting both $iri and $uri is not allowed because it serves no purpose.');
            }

            $resolved = $this->findIriBy($iri[0], $iri[1]);
            if (!$resolved) {
                throw new \RuntimeException('IRI could not be resolved with the given parameters!');
            }

            $uri = $resolved;
        }

        if ([] !== $createdLogs) {
            self::prepareLogger();
        }

        if ([] !== $files) {
            $requestOptions['extra']['files'] ??= [];
            $requestOptions['headers']['content-type'] ??= 'multipart/form-data';

            foreach ($files as $key => $fileData) {
                $requestOptions['extra']['files'][$key] =
                    static::prepareUploadedFile(
                        $fileData['path'],
                        $fileData['originalName'],
                        $fileData['mimeType'],
                    );
            }
        }

        if ('PATCH' === $method) {
            $requestOptions['headers']['content-type'] ??= 'application/merge-patch+json';
        }

        $response = $client->request(
            $method,
            $uri,
            $requestOptions,
        );

        if (null !== $responseCode) {
            self::assertResponseStatusCodeSame($responseCode);
        }

        if ('' !== $contentType) {
            self::assertResponseHeaderSame('content-type', $contentType);
        }

        if ([] !== $json) {
            self::assertJsonContains($json);
        }

        if ($requiredKeys || $forbiddenKeys) {
            $dataset = $response->toArray(false);

            self::assertDatasetHasKeys($requiredKeys, $dataset);
            self::assertDatasetNotHasKeys($forbiddenKeys, $dataset);
        }

        if ('' !== $schemaClass) {
            if ($iri || 'GET' !== $method) {
                self::assertMatchesResourceItemJsonSchema($schemaClass);
            } else {
                self::assertMatchesResourceCollectionJsonSchema($schemaClass);
            }
        }

        if ([] !== $createdLogs) {
            foreach ($createdLogs as $createdLog) {
                self::assertLoggerHasMessage($createdLog[0], $createdLog[1]);
            }
        }

        if (null !== $emailCount) {
            self::assertEmailCount($emailCount);
        }

        if ([] !== $dispatchedEvents) {
            /** @var TraceableEventDispatcher $dispatcher */
            $dispatcher = static::getContainer()
                ->get(EventDispatcherInterface::class);

            foreach ($dispatchedEvents as $eventName) {
                $found = false;
                foreach ($dispatcher->getCalledListeners() as $calledListener) {
                    if ($calledListener['event'] === $eventName) {
                        $found = true;
                        break;
                    }
                }

                self::assertTrue(
                    $found,
                    "Expected event '$eventName' was not dispatched"
                );
            }
        }

        if (null !== $messageCount || $dispatchedMessages) {
            $messenger = static::getContainer()->get('messenger.default_bus');
            $messages = $messenger->getDispatchedMessages();

            if (null !== $messageCount) {
                $found = count($messages);
                self::assertSame(
                    $messageCount,
                    $found,
                    "Expected $messageCount messages to be dispatched, found $found"
                );
            }

            if ([] !== $dispatchedMessages) {
                foreach ($dispatchedMessages as $message) {
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
                    self::assertGreaterThan(
                        0,
                        count($filtered),
                        "The expected '$messageClass' was not dispatched"
                    );

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
        string $mimeType,
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
    public static function assertDatasetHasKeys(
        array $expected,
        array $array,
        string $parent = '',
    ): void {
        foreach ($expected as $index => $value) {
            if (is_array($value)) {
                self::assertArrayHasKey(
                    $index,
                    $array,
                    "Dataset does not have key {$parent}[$index]!"
                );
                self::assertIsArray(
                    $array[$index],
                    "Key {$parent}[$index] is expected to be an array!"
                );
                self::assertDatasetHasKeys(
                    $value,
                    $array[$index],
                    "{$parent}[$index]"
                );
            } else {
                self::assertArrayHasKey(
                    $value,
                    $array,
                    "Dataset does not have key {$parent}[$value]!"
                );
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
    public static function assertDatasetNotHasKeys(
        array $expected,
        array $array,
        string $parent = '',
    ): void {
        foreach ($expected as $index => $value) {
            if (is_array($value)) {
                // the parent key does not exist / is null -> silently skip the child keys
                if (!isset($array[$index])) {
                    continue;
                }
                self::assertIsArray(
                    $array[$index],
                    "Key {$parent}[$index] is expected to be an array or null!"
                );
                self::assertDatasetNotHasKeys(
                    $value,
                    $array[$index],
                    "{$parent}[$index]"
                );
            } else {
                self::assertArrayNotHasKey(
                    $value,
                    $array,
                    "Dataset should not have key {$parent}[$value]!"
                );
            }
        }
    }

    /**
     * Generates a JWT for the user given by its identifying property, e.g. email.
     */
    protected function getJWT(array $findUserBy): string
    {
        $em = static::getContainer()->get('doctrine.orm.entity_manager');
        $user = $em->getRepository(static::$userClass)->findOneBy($findUserBy);
        if (!$user) {
            throw new \RuntimeException('User specified for JWT authentication was not found, please check your test database/fixtures!');
        }

        $jwtManager = static::getContainer()->get('lexik_jwt_authentication.jwt_manager');

        return $jwtManager->create($user);
    }
}
