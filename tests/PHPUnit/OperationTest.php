<?php

declare(strict_types=1);

namespace Vrok\SymfonyAddons\Tests\PHPUnit;

use Monolog\Level;
use PHPUnit\Framework\AssertionFailedError;
use Symfony\Contracts\HttpClient\ResponseInterface;
use Vrok\SymfonyAddons\PHPUnit\ApiPlatformTestCase;

/**
 * To test the ApiPlatformTestCase we need a non-abstract class. So we directly
 * use the child class for our tests.
 */
class OperationTest extends ApiPlatformTestCase
{
    public function testTestOperationCanBeCalled(): void
    {
        $this->testOperation(
            uri: '/test',
            responseCode: 404,
            contentType: ApiPlatformTestCase::PROBLEM_CONTENT_TYPE,
            json: [
                'detail' => 'No route found for "GET http://localhost/test"',
            ],
            requiredKeys: [
                'detail',
                'status',
                'type',

                // ApiPlatform 4.2 is backwards incompatible (again):
                // Before, "title" and "hydra:title" were both set, so old
                // clients, depending on the "hydra" values worked and new ones
                // that use only "detail"+"title". Now we only get either
                // title or hydra:title
                // @todo Change when api_platform.serializer.hydra_prefix is
                //       set to false
                'hydra:title',
            ],

            // @todo Change when api_platform.serializer.hydra_prefix is
            //       set to false
            forbiddenKeys: ['hydra:member'],
            messageCount: 0,
        );
    }

    public function testTestOperationCallsPrepare(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('I was called');

        $this->testOperation(
            prepare: static function (): void {
                throw new \RuntimeException('I was called');
            },
            uri: '/test'
        );
    }

    public function testTestOperationUsesPreparedParameters(): void
    {
        $this->expectException(AssertionFailedError::class);
        $this->expectExceptionMessage('Failed asserting that the Response status code is 555.');

        $this->testOperation(
            prepare: static function ($container, array &$params): void {
                $params['responseCode'] = 555;
            },
            uri: '/test',
        );
    }

    public function testTestOperationChecksReturnCode(): void
    {
        $this->expectException(AssertionFailedError::class);
        $this->expectExceptionMessage('Failed asserting that the Response status code is 200.');

        $this->testOperation(uri: '/test', responseCode: 200);
    }

    public function testTestOperationChecksContentType(): void
    {
        $this->expectException(AssertionFailedError::class);
        $this->expectExceptionMessage('Failed asserting that the Response has header "content-type" with value "application/text".');

        $this->testOperation(uri: '/test', contentType: 'application/text');
    }

    public function testTestOperationChecksJson(): void
    {
        $this->expectException(AssertionFailedError::class);
        $this->expectExceptionMessage('Failed asserting that an array has the subset Array');

        $this->testOperation(uri: '/test', json: ['success' => true]);
    }

    public function testTestOperationChecksRequiredKeys(): void
    {
        $this->expectException(AssertionFailedError::class);
        $this->expectExceptionMessage('Dataset does not have key [success]!');

        $this->testOperation(uri: '/test', requiredKeys: ['success']);
    }

    public function testTestOperationChecksForbiddenKeys(): void
    {
        $this->expectException(AssertionFailedError::class);
        $this->expectExceptionMessage('Dataset should not have key [detail]!');

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
        $this->expectExceptionMessage("Expected event 'failedEvent' was not dispatched");

        $this->testOperation(uri: '/test', dispatchedEvents: ['failedEvent']);
    }

    public function testTestOperationChecksCreatedLogs(): void
    {
        $this->expectException(AssertionFailedError::class);
        $this->expectExceptionMessage('Logger has no message with the given level that contains the given string!');

        $this->testOperation(uri: '/test', createdLogs: [['not found', Level::Error]]);
    }

    public function testTestOperationChecksEmailCount(): void
    {
        $this->expectException(AssertionFailedError::class);
        $this->expectExceptionMessage('Failed asserting that the Transport has sent "1" emails (0 sent).');

        $this->testOperation(uri: '/test', emailCount: 1);
    }

    public function testTestOperationChecksMessageCount(): void
    {
        $this->expectException(AssertionFailedError::class);
        $this->expectExceptionMessage('Expected 1 messages to be dispatched, found 0');

        $this->testOperation(uri: '/test', messageCount: 1);
    }
}
