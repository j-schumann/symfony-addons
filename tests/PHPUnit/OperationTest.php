<?php

namespace Vrok\SymfonyAddons\Tests\PHPUnit;

use Monolog\Level;
use PHPUnit\Framework\AssertionFailedError;
use Symfony\Contracts\HttpClient\ResponseInterface;
use Vrok\SymfonyAddons\PHPUnit\ApiPlatformTestCase;
use Vrok\SymfonyAddons\Tests\Fixtures\Entity\TestEntity;

/**
 * To test the ApiPlatformTestCase we need a non-abstract class. So we directly use the child class
 * for our tests.
 */
final class OperationTest extends ApiPlatformTestCase
{
    private ?string $jwt = null;

    public function testTestOperationCanBeCalled(): void
    {
        $this->testOperation(
            uri: '/test',
            responseCode: 404,
            contentType: ApiPlatformTestCase::PROBLEM_MEDIA_TYPE,
            json: [
                'detail' => 'No route found for "GET http://localhost/test"',
            ],
            requiredKeys: [
                'detail',
                'status',
                'title',
                'type',
            ],
            forbiddenKeys: ['member'],
            messageCount: 0,
        );
    }

    public function testTestOperationFailsWithoutJwtBundle(): void
    {
        $this->expectException(\LogicException::class);
        $this->expectExceptionMessageIsOrContains('getJWT() requires the lexik/jwt-authentication-bundle');

        $this->testOperation(uri: '/test', email: 'test@example.com');
    }

    public function testTestOperationSendsCookies(): void
    {
        $this->testOperation(uri: '/test', cookies: ['BEARER' => 'token', 'other' => 'value']);

        self::assertSame(
            ['BEARER' => 'token', 'other' => 'value'],
            self::getClient()->getRequest()->cookies->all()
        );
    }

    public function testTestOperationChecksReturnCode(): void
    {
        $this->expectException(AssertionFailedError::class);
        $this->expectExceptionMessageIsOrContains('Failed asserting that the Response status code is 200.');

        $this->testOperation(uri: '/test', responseCode: 200);
    }

    public function testTestOperationChecksContentType(): void
    {
        $this->expectException(AssertionFailedError::class);
        $this->expectExceptionMessageIs('Failed asserting that the Response has header "content-type" with value "application/text".');

        $this->testOperation(uri: '/test', contentType: 'application/text');
    }

    public function testTestOperationChecksJson(): void
    {
        $this->expectException(AssertionFailedError::class);
        $this->expectExceptionMessageIsOrContains('Failed asserting that an array has the subset Array');

        $this->testOperation(uri: '/test', json: ['success' => true]);
    }

    public function testTestOperationChecksRequiredKeys(): void
    {
        $this->expectException(AssertionFailedError::class);
        $this->expectExceptionMessageIsOrContains('Dataset does not have key [success]!');

        $this->testOperation(uri: '/test', requiredKeys: ['success']);
    }

    public function testTestOperationChecksForbiddenKeys(): void
    {
        $this->expectException(AssertionFailedError::class);
        $this->expectExceptionMessageIsOrContains('Dataset should not have key [detail]!');

        $this->testOperation(uri: '/test', forbiddenKeys: ['detail']);
    }

    public function testTestOperationDetectsDispatchedEvents(): void
    {
        $response = $this->testOperation(uri: '/test', dispatchedEvents: ['kernel.request']);

        self::assertInstanceOf(ResponseInterface::class, $response);
    }

    public function testTestOperationDetectsNotDispatchedEvents(): void
    {
        $this->expectException(AssertionFailedError::class);
        $this->expectExceptionMessageIsOrContains("Expected event 'failedEvent' was not dispatched");

        $this->testOperation(uri: '/test', dispatchedEvents: ['failedEvent']);
    }

    public function testTestOperationChecksCreatedLogs(): void
    {
        $this->expectException(AssertionFailedError::class);
        $this->expectExceptionMessageIsOrContains('Logger has no message with the given level that contains the given string!');

        $this->testOperation(uri: '/test', createdLogs: [['not found', Level::Error]]);
    }

    public function testTestOperationChecksEmailCount(): void
    {
        $this->expectException(AssertionFailedError::class);
        $this->expectExceptionMessageIs('Failed asserting that the Transport has sent "1" emails (0 sent).');

        $this->testOperation(uri: '/test', emailCount: 1);
    }

    public function testTestOperationChecksMessageCount(): void
    {
        $this->expectException(AssertionFailedError::class);
        $this->expectExceptionMessageIsOrContains('Expected 1 messages to be dispatched, found 0');

        $this->testOperation(uri: '/test', messageCount: 1);
    }

    public function testTestOperationFailsWithIriAndUri(): void
    {
        $this->expectException(\LogicException::class);
        $this->expectExceptionMessageIs('Setting both $iri and $uri is not allowed because it serves no purpose.');

        $this->testOperation(uri: '/test', iri: [TestEntity::class, ['id' => 1]]);
    }

    public function testTestOperationChecksDispatchedMessages(): void
    {
        $this->expectException(AssertionFailedError::class);
        $this->expectExceptionMessageIsOrContains("The expected 'stdClass' was not dispatched");

        $this->testOperation(uri: '/test', dispatchedMessages: [\stdClass::class]);
    }

    public function testTestOperationFailsWithInvalidDispatchedMessage(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageIsOrContains('Entries of "dispatchedMessages" must either be a string');

        $this->testOperation(uri: '/test', dispatchedMessages: [[\stdClass::class]]);
    }

    public function testTestOperationSendsJwtAsBearer(): void
    {
        $this->jwt = 'token';

        $this->testOperation(uri: '/test', email: 'test@example.com');

        self::assertSame('Bearer token', self::getClient()->getRequest()->headers->get('Authorization'));
    }

    public function testTestOperationSendsJwtAsFormParameter(): void
    {
        $this->jwt = 'token';

        $this->testOperation(
            uri: '/test',
            email: 'test@example.com',
            postFormAuth: 'auth',
            method: 'POST'
        );

        $request = self::getClient()->getRequest();
        self::assertSame('application/x-www-form-urlencoded', $request->headers->get('content-type'));
        self::assertSame('token', $request->request->get('auth'));
        self::assertFalse($request->headers->has('Authorization'));
    }

    public function testTestOperationSendsFiles(): void
    {
        $this->testOperation(
            uri: '/test',
            method: 'POST',
            files: ['file' => ['path' => __FILE__, 'originalName' => 'test.php', 'mimeType' => 'text/x-php']],
        );

        $request = self::getClient()->getRequest();
        self::assertStringStartsWith('multipart/form-data', (string) $request->headers->get('content-type'));
        self::assertSame('test.php', $request->files->get('file')->getClientOriginalName());
    }

    public function testTestOperationSendsPatchAsMergePatch(): void
    {
        $this->testOperation(
            uri: '/test',
            method: 'PATCH',
            requestOptions: ['json' => []]
        );

        self::assertSame(
            'application/merge-patch+json',
            self::getClient()->getRequest()->headers->get('content-type')
        );
    }

    public function testTestOperationResolvesIri(): void
    {
        $this->testOperation(
            iri: [TestEntity::class, ['varcharColumn' => 'record EINS varchar']],
            responseCode: 200,
            json: ['@type' => 'TestEntity'],
            schemaClass: TestEntity::class,
        );

        self::assertMatchesRegularExpression('#^/test_entities/\d+$#', self::getClient()->getRequest()->getPathInfo());
    }

    public function testTestOperationFailsWithUnknownIri(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageIs('IRI could not be resolved with the given parameters!');

        $this->testOperation(iri: [TestEntity::class, ['varcharColumn' => 'unknown']]);
    }

    public function testTestOperationChecksCollectionSchema(): void
    {
        $this->testOperation(
            uri: '/test_entities',
            responseCode: 200,
            schemaClass: TestEntity::class
        );
    }

    public function testTestOperationDetectsDispatchedMessages(): void
    {
        $dispatched = [];

        $this->testOperation(
            uri: '/test_entities',
            method: 'POST',
            requestOptions: ['json' => ['varcharColumn' => 'new']],
            responseCode: 202,
            messageCount: 1,
            dispatchedMessages: [
                TestEntity::class,
                [
                    TestEntity::class,
                    static function (TestEntity $message, array $data) use (&$dispatched): void {
                        $dispatched[] = [$message->varcharColumn, $data];
                    },
                ],
            ],
        );

        self::assertSame([['new', []]], $dispatched);
    }

    protected function getJWT(array $findUserBy): string
    {
        return $this->jwt ?? parent::getJWT($findUserBy);
    }
}
