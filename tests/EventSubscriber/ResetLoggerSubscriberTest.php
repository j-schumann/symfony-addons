<?php

declare(strict_types=1);

namespace Vrok\SymfonyAddons\Tests;

use Monolog\Handler\BufferHandler;
use Monolog\Logger;
use Monolog\Processor\UidProcessor;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Messenger\Event\WorkerMessageFailedEvent;
use Symfony\Component\Messenger\Event\WorkerMessageHandledEvent;
use Symfony\Component\Messenger\Event\WorkerMessageReceivedEvent;
use Vrok\SymfonyAddons\EventSubscriber\ResetLoggerSubscriber;

class ResetLoggerSubscriberTest extends TestCase
{
    protected Logger $stubLogger;
    protected BufferHandler $stubHandler;
    protected UidProcessor $stubProcessor;

    protected function createLogger($name = 'app'): void
    {
        $this->stubLogger = new Logger($name);

        $this->stubHandler = $this->createStub(BufferHandler::class);
        $this->stubLogger->pushHandler($this->stubHandler);

        $this->stubProcessor = $this->createStub(UidProcessor::class);
        $this->stubLogger->pushProcessor($this->stubProcessor);
    }

    public function testRegistersEvents(): void
    {
        $events = ResetLoggerSubscriber::getSubscribedEvents();
        $this->assertIsArray($events);
        $this->assertArrayHasKey(WorkerMessageReceivedEvent::class, $events);
        $this->assertArrayHasKey(WorkerMessageHandledEvent::class, $events);
        $this->assertArrayHasKey(WorkerMessageFailedEvent::class, $events);
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
