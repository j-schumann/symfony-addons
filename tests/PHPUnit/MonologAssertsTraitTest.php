<?php

declare(strict_types=1);

namespace Vrok\SymfonyAddons\Tests\PHPUnit;

use Monolog\Handler\TestHandler;
use Monolog\Level;
use Monolog\Logger;
use PHPUnit\Framework\AssertionFailedError;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Vrok\SymfonyAddons\PHPUnit\MonologAssertsTrait;

/**
 * @group MonologAssertsTrait
 */
class MonologAssertsTraitTest extends KernelTestCase
{
    use MonologAssertsTrait;

    public function testPrepareLogger(): void
    {
        /** @var Logger $logger */
        $logger = static::getContainer()->get(LoggerInterface::class);

        foreach ($logger->getHandlers() as $handler) {
            self::assertNotInstanceOf(TestHandler::class, $handler);
        }

        static::prepareLogger();

        $found = false;
        foreach ($logger->getHandlers() as $handler) {
            if ($handler instanceof TestHandler) {
                $found = true;
                break;
            }
        }

        self::assertTrue($found);
    }

    public function testHasMessagePasses(): void
    {
        static::prepareLogger();

        /** @var Logger $logger */
        $logger = static::getContainer()->get(LoggerInterface::class);
        $logger->debug('my debug message');

        self::assertLoggerHasMessage('my debug message', Level::Debug);
    }

    public function testHasMessagePassesWithContextCheck(): void
    {
        static::prepareLogger();

        /** @var Logger $logger */
        $logger = static::getContainer()->get(LoggerInterface::class);
        $logger->debug('my debug message', ['data' => 'some data']);

        self::assertLoggerHasMessage('my debug message', Level::Debug, ['data' => 'some data']);
    }

    public function testHasMessagePassesWithoutContextCheck(): void
    {
        static::prepareLogger();

        /** @var Logger $logger */
        $logger = static::getContainer()->get(LoggerInterface::class);
        $logger->debug('my debug message', ['data' => 'some data']);

        self::assertLoggerHasMessage('my debug message', Level::Debug);
    }

    public function testHasMessageThrows(): void
    {
        static::prepareLogger();

        /** @var Logger $logger */
        $logger = static::getContainer()->get(LoggerInterface::class);
        $logger->debug('my test message');

        $this->expectException(AssertionFailedError::class);
        $this->expectExceptionMessage('Logger has no message with the given level that contains the given string and the given context!');
        self::assertLoggerHasMessage('my debug message', Level::Debug);
    }

    public function testHasMessageRespectsLevel(): void
    {
        static::prepareLogger();

        /** @var Logger $logger */
        $logger = static::getContainer()->get(LoggerInterface::class);
        $logger->debug('my debug message');

        $this->expectException(AssertionFailedError::class);
        $this->expectExceptionMessage('Logger has no message with the given level that contains the given string and the given context!');
        self::assertLoggerHasMessage('my debug message', Level::Error);
    }

    public function testHasMessageRespectsContext(): void
    {
        static::prepareLogger();

        /** @var Logger $logger */
        $logger = static::getContainer()->get(LoggerInterface::class);
        $logger->debug('my debug message', ['data' => 'some data']);

        $this->expectException(AssertionFailedError::class);
        $this->expectExceptionMessage('Logger has no message with the given level that contains the given string and the given context!');
        self::assertLoggerHasMessage('my debug message', Level::Debug, ['status' => 1]);
    }

    public function testHasMessageRequiresTestHandler(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Logger has no TestHandler, please call self::prepareLogger() before the test!');
        self::assertLoggerHasMessage('fail', Level::Error);
    }
}
