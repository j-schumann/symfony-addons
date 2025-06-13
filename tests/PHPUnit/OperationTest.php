<?php

declare(strict_types=1);

namespace Vrok\SymfonyAddons\Tests\PHPUnit;

use http\Exception\RuntimeException;
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
            requiredKeys: ['detail', 'status', 'title', 'type'],
            forbiddenKeys: ['hydra:member'],
            messageCount: 0,
        );
    }

    public function testTestOperationCallsPrepare(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('I was called');

        $this->testOperation(
            uri: '/test',
            prepare: static function () {
                throw new \RuntimeException('I was called');
            }
        );
    }

    public function testTestOperationUsesPreparedParameters(): void
    {
        $this->expectException(AssertionFailedError::class);
        $this->expectExceptionMessage('Failed asserting that the Response status code is 555.');

        $this->testOperation(
            uri: '/test',
            prepare: static function ($container, array &$params) {
                $params['responseCode'] = 555;
            },
        );
    }

    public function testTestOperationChecksReturnCode(): void
    {
        $this->expectException(AssertionFailedError::class);
        $this->expectExceptionMessage('Failed asserting that the Response status code is 200.');

        $this->testOperation([
            'uri'          => '/test',
            'responseCode' => 200,
        ]);
    }

    public function testTestOperationChecksContentType(): void
    {
        $this->expectException(AssertionFailedError::class);
        $this->expectExceptionMessage('Failed asserting that the Response has header "content-type" with value "application/text".');

        $this->testOperation([
            'uri'         => '/test',
            'contentType' => 'application/text',
        ]);
    }

    public function testTestOperationChecksJson(): void
    {
        $this->expectException(AssertionFailedError::class);
        $this->expectExceptionMessage('Failed asserting that an array has the subset Array');

        $this->testOperation([
            'uri'  => '/test',
            'json' => ['success' => true],
        ]);
    }

    public function testTestOperationChecksRequiredKeys(): void
    {
        $this->expectException(AssertionFailedError::class);
        $this->expectExceptionMessage('Dataset does not have key [success]!');

        $this->testOperation([
            'uri'          => '/test',
            'requiredKeys' => ['success'],
        ]);
    }

    public function testTestOperationChecksForbiddenKeys(): void
    {
        $this->expectException(AssertionFailedError::class);
        $this->expectExceptionMessage('Dataset should not have key [detail]!');

        $this->testOperation([
            'uri'           => '/test',
            'forbiddenKeys' => ['detail'],
        ]);
    }

    public function testTestOperationDetectsDispatchedEvents(): void
    {
        $response = $this->testOperation([
            'uri'              => '/test',
            'dispatchedEvents' => ['kernel.request'],
        ]);

        self::assertInstanceOf(ResponseInterface::class, $response);
    }

    public function testTestOperationDetectsNotDispatchedEvents(): void
    {
        $this->expectException(AssertionFailedError::class);
        $this->expectExceptionMessage("Expected event 'failedEvent' was not dispatched");

        $this->testOperation([
            'uri'              => '/test',
            'dispatchedEvents' => ['failedEvent'],
        ]);
    }

    public function testTestOperationChecksCreatedLogs(): void
    {
        $this->expectException(AssertionFailedError::class);
        $this->expectExceptionMessage('Logger has no message with the given level that contains the given string!');

        $this->testOperation([
            'uri'         => '/test',
            'createdLogs' => [['not found', Level::Error]],
        ]);
    }

    public function testTestOperationChecksEmailCount(): void
    {
        $this->expectException(AssertionFailedError::class);
        $this->expectExceptionMessage('Failed asserting that the Transport has sent "1" emails (0 sent).');

        $this->testOperation([
            'uri'        => '/test',
            'emailCount' => 1,
        ]);
    }

    public function testTestOperationChecksMessageCount(): void
    {
        $this->expectException(AssertionFailedError::class);
        $this->expectExceptionMessage('Expected 1 messages to be dispatched, found 0');

        $this->testOperation([
            'uri'          => '/test',
            'messageCount' => 1,
        ]);
    }
}
