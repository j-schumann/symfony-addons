<?php

declare(strict_types=1);

namespace Vrok\SymfonyAddons\PHPUnit;

use Monolog\Handler\TestHandler;
use Monolog\Level;
use Monolog\Logger;
use Psr\Log\LoggerInterface;

/**
 * Helper to test if Monolog has a specific log message.
 */
trait MonologAssertsTrait
{
    public static function prepareLogger(): void
    {
        /** @var Logger $logger */
        $logger = static::getContainer()->get(LoggerInterface::class);
        $logger->pushHandler(new TestHandler());
    }

    /**
     * Asserts that the Logger service (monolog) has a log record with the given level
     * that contains the given message.
     */
    public static function assertLoggerHasMessage(string $message, Level $level): void
    {
        /** @var Logger $logger */
        $logger = static::getContainer()->get(LoggerInterface::class);

        foreach ($logger->getHandlers() as $handler) {
            if ($handler instanceof TestHandler) {
                self::assertTrue($handler->hasRecordThatContains($message, $level), 'Logger has no message with the given level that contains the given string!');

                return;
            }
        }

        throw new \RuntimeException('Logger has no TestHandler, please call self::prepareLogger() before the test!');
    }
}
