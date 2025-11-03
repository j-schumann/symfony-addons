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

final class MonologAssertsTraitTest extends KernelTestCase
{
    use MonologAssertsTrait;

    public function testPrepareLogger(): void
    {
        /** @var Logger $logger */
        $logger = self::getContainer()->get(LoggerInterface::class);

        foreach ($logger->getHandlers() as $handler) {
            self::assertNotInstanceOf(TestHandler::class, $handler);
        }

        self::prepareLogger();

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
        self::prepareLogger();

        /** @var Logger $logger */
        $logger = self::getContainer()->get(LoggerInterface::class);
        $logger->debug('my debug message');

        self::assertLoggerHasMessage('my debug message', Level::Debug);
    }

    public function testHasMessageThrows(): void
    {
        self::prepareLogger();

        /** @var Logger $logger */
        $logger = self::getContainer()->get(LoggerInterface::class);
        $logger->debug('my test message');

        $this->expectException(AssertionFailedError::class);
        $this->expectExceptionMessage('Logger has no message with the given level that contains the given string!');
        self::assertLoggerHasMessage('my debug message', Level::Debug);
    }

    public function testHasMessageRespectsLevel(): void
    {
        self::prepareLogger();

        /** @var Logger $logger */
        $logger = self::getContainer()->get(LoggerInterface::class);
        $logger->debug('my debug message');

        $this->expectException(AssertionFailedError::class);
        $this->expectExceptionMessage('Logger has no message with the given level that contains the given string!');
        self::assertLoggerHasMessage('my debug message', Level::Error);
    }

    public function testHasMessageRequiresTestHandler(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Logger has no TestHandler, please call self::prepareLogger() before the test!');
        self::assertLoggerHasMessage('fail', Level::Error);
    }
}
