<?php

namespace Vrok\SymfonyAddons\PHPUnit;

use ApiPlatform\Test\ApiTestCase;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\BrowserKit\Cookie;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpKernel\Debug\TraceableEventDispatcher;
use Symfony\Component\Messenger\TraceableMessageBus;
use Symfony\Contracts\HttpClient\ResponseInterface;

/**
 * Helper class that contains often used functionality to simplify testing API endpoints.
 */
abstract class ApiPlatformTestCase extends ApiTestCase
{
    use MonologAssertsTrait;
    use RefreshDatabaseTrait;

    /**
     * Used when getting a JWT for the authentication in testOperation(). Set in your test classes
     * accordingly.
     */
    protected static string $userClass = '\App\Entity\User';

    // region JSON responses
    protected const UNAUTHENTICATED_RESPONSE = [
        'code'    => 401,
        'message' => 'JWT Token not found',
    ];

    /**
     * Media type of RFC 7807 compliant error responses.
     */
    public const PROBLEM_MEDIA_TYPE = 'application/problem+json';

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
    // endregion

    /**
     * The params *must* contain either 'iri' or 'uri', all other settings are
     * optional.
     *
     * @param string                $uri                the endpoint to call, e.g. '/tenants'
     * @param array<int, mixed>     $iri                [classname, [field => value]],
     *                                                  e.g. [User::class, [email => 'test@test.de']]
     *                                                  tries to find an entity by the given conditions and
     *                                                  retrieves its IRI, it is then used as URI
     * @param string                $email              if given, tries to find a User with that email and sends
     *                                                  the token of getJWT() for this user as Bearer
     * @param string                $postFormAuth       if given together with 'email', sends the JWT as
     *                                                  'application/x-www-form-urlencoded' request in the
     *                                                  given field name
     * @param string                $method             HTTP method for the request, defaults to GET
     * @param array<string, mixed>  $requestOptions     options for the HTTP client, e.g. query parameters or
     *                                                  basic auth
     * @param array<string, string> $cookies            cookies to send, name => value
     * @param array<string, mixed>  $files              files to upload, field name =>
     *                                                  ['path' => .., 'originalName' => .., 'mimeType' => ..]
     * @param ?int                  $responseCode       asserts that the received status code matches
     * @param string                $contentType        asserts that the received content type header matches
     * @param array<mixed>          $json               asserts that the returned content is JSON and
     *                                                  contains the given array as subset
     * @param array<mixed>          $requiredKeys       asserts the dataset contains the list of keys.
     *                                                  Used for elements where the value is not known in advance,
     *                                                  e.g. ID, slug, timestamps. Can be nested:
     *                                                  ['member' => [['id', '@id']]]
     * @param array<mixed>          $forbiddenKeys      like requiredKeys, but the dataset may not contain those
     * @param string                $schemaClass        asserts that the received response matches the JSON
     *                                                  schema for the given class
     * @param array<array<mixed>>   $createdLogs        array of ["log message", LogLevel] entries, asserts the
     *                                                  messages to be present in the monolog handlers after the
     *                                                  operation ran
     * @param ?int                  $emailCount         asserts this number of emails to be sent via the
     *                                                  mailer after the operation was executed
     * @param ?int                  $messageCount       asserts this number of messages to be dispatched
     *                                                  to the message bus
     * @param array<mixed>          $dispatchedMessages array of message classes, asserts that at least one instance
     *                                                  of each given class has been dispatched to the message bus.
     *                                                  Instead of class names the elements can be an array of
     *                                                  [classname, callable]. This callback will be called
     *                                                  (for each matching message) with the message as first
     *                                                  parameter and the returned JSON as second parameter.
     * @param array<string>         $dispatchedEvents   array of event names, asserts that at least one
     *                                                  instance of each given event has been dispatched
     */
    protected function testOperation(
        string $uri = '',
        array $iri = [],
        string $email = '',
        string $postFormAuth = '',
        string $method = 'GET',
        array $requestOptions = [],
        array $cookies = [],
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
                        'Authorization' => \sprintf('Bearer %s', $token),
                    ],
                ]);
            }
        }

        foreach ($cookies as $name => $value) {
            $client->getKernelBrowser()->getCookieJar()->set(new Cookie($name, $value));
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

        foreach ($createdLogs as $createdLog) {
            self::assertLoggerHasMessage($createdLog[0], $createdLog[1]);
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
            /** @var TraceableMessageBus $messenger */
            $messenger = static::getContainer()->get('messenger.default_bus');
            $messages = $messenger->getDispatchedMessages();

            if (null !== $messageCount) {
                $found = \count($messages);
                self::assertSame(
                    $messageCount,
                    $found,
                    "Expected $messageCount messages to be dispatched, found $found"
                );
            }

            foreach ($dispatchedMessages as $message) {
                $messageCallback = null;

                if (\is_array($message)
                    && 2 === \count($message)
                    && \is_string($message[0])
                    && \is_callable($message[1])
                ) {
                    $messageClass = $message[0];
                    $messageCallback = $message[1];
                } elseif (\is_string($message)) {
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
                    static fn (array $ele) => is_a($ele['message'], $messageClass)
                );
                self::assertGreaterThan(
                    0,
                    \count($filtered),
                    "The expected '$messageClass' was not dispatched"
                );

                if (null !== $messageCallback) {
                    foreach ($filtered as $msg) {
                        $messageCallback($msg['message'], $response->toArray(false));
                    }
                }
            }
        }

        return $response;
    }

    /**
     * Creates a copy of the file given via $path and returns a UploadedFile to be given to
     * testOperation() / the kernelBrowser to unit test file uploads.
     */
    public static function prepareUploadedFile(
        string $path,
        string $originalName,
        string $mimeType,
    ): UploadedFile {
        // don't directly use the given file as the upload handler will most probably move or delete
        // the received file -> copy to temp file
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

    #[\Override]
    public static function tearDownAfterClass(): void
    {
        self::fixtureCleanup();
    }

    /**
     * Asserts that the given dataset $array does contain the list of $expected keys. The keys may
     * be nested.
     *
     * @param array<mixed> $expected list of keys to check:
     *                               ['hydra:member'][0]['id', '@id', 'slug']
     * @param array<mixed> $array    the dataset to verify
     * @param string       $parent   auto-set when called recursively
     */
    public static function assertDatasetHasKeys(
        array $expected,
        array $array,
        string $parent = '',
    ): void {
        foreach ($expected as $index => $value) {
            if (\is_array($value)) {
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
     * @param array<mixed> $expected list of keys to check:
     *                               ['hydra:member'][0]['internal', 'hidden', 'private']
     * @param array<mixed> $array    the dataset to verify
     * @param string       $parent   auto-set when called recursively
     */
    public static function assertDatasetNotHasKeys(
        array $expected,
        array $array,
        string $parent = '',
    ): void {
        foreach ($expected as $index => $value) {
            if (\is_array($value)) {
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
     * Generates a JWT for the user given by its identifying property, e.g. email, with the
     * lexik/jwt-authentication-bundle. Override it to create the token for another authenticator,
     * e.g. an OIDC token signed with a test key.
     *
     * @param array<string, string> $findUserBy
     */
    protected function getJWT(array $findUserBy): string
    {
        if (!static::getContainer()->has('lexik_jwt_authentication.jwt_manager')) {
            throw new \LogicException('getJWT() requires the lexik/jwt-authentication-bundle, install it or override getJWT() to create the token for your authenticator.');
        }

        /** @var EntityManagerInterface $em */
        $em = static::getContainer()->get('doctrine.orm.entity_manager');
        /** @var class-string $userClass */
        $userClass = static::$userClass;
        $user = $em->getRepository($userClass)->findOneBy($findUserBy);
        if (!$user) {
            throw new \RuntimeException('User specified for JWT authentication was not found, please check your test database/fixtures!');
        }

        $jwtManager = static::getContainer()->get('lexik_jwt_authentication.jwt_manager');

        // @phpstan-ignore method.notFound (lexik/jwt-authentication-bundle is optional)
        return $jwtManager->create($user);
    }
}
