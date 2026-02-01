<?php

declare(strict_types=1);

namespace Vrok\SymfonyAddons\Tests\EventSubscriber;

use Monolog\Handler\BufferHandler;
use Monolog\Logger;
use Monolog\Processor\UidProcessor;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Messenger\Event\WorkerMessageFailedEvent;
use Symfony\Component\Messenger\Event\WorkerMessageHandledEvent;
use Symfony\Component\Messenger\Event\WorkerMessageReceivedEvent;
use Vrok\SymfonyAddons\EventSubscriber\ResetLoggerSubscriber;

final class ResetLoggerSubscriberTest extends TestCase
{
    private Logger $stubLogger;
    private ?MockObject $stubHandler = null;
    private ?MockObject $stubProcessor = null;

    private function createLogger(string $name = 'app'): void
    {
        $this->stubLogger = new Logger($name);

        $this->stubHandler = $this->createMock(BufferHandler::class);
        $this->stubLogger->pushHandler($this->stubHandler);

        $this->stubProcessor = $this->createMock(UidProcessor::class);
        $this->stubLogger->pushProcessor($this->stubProcessor);
    }

    public function testRegistersEvents(): void
    {
        $events = ResetLoggerSubscriber::getSubscribedEvents();
        self::assertIsArray($events);
        self::assertArrayHasKey(WorkerMessageReceivedEvent::class, $events);
        self::assertArrayHasKey(WorkerMessageHandledEvent::class, $events);
        self::assertArrayHasKey(WorkerMessageFailedEvent::class, $events);
    }

    public function testResetsUidForApp(): void
    {
        $this->createLogger();
        $this->stubProcessor->expects($this->once())
            ->method('reset');

        $subscriber = new ResetLoggerSubscriber();
        $subscriber->setLogger($this->stubLogger);

        $subscriber->resetLogger();
    }

    public function testKeepsUidForOthers(): void
    {
        $this->createLogger('fake');
        $this->stubProcessor->expects($this->never())
            ->method('reset');

        $subscriber = new ResetLoggerSubscriber();
        $subscriber->setLogger($this->stubLogger);

        $subscriber->resetLogger();
    }

    public function testFlushesBuffer(): void
    {
        $this->createLogger();
        $this->stubHandler->expects($this->once())
            ->method('flush');

        $subscriber = new ResetLoggerSubscriber();
        $subscriber->setLogger($this->stubLogger);

        $subscriber->flushLogger();
    }
}
