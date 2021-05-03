<?php

declare(strict_types=1);

namespace Vrok\SymfonyAddons\Tests\Command;

use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Vrok\SymfonyAddons\Command\CronDailyCommand;
use Vrok\SymfonyAddons\Event\CronDailyEvent;

class CronDailyCommandTest extends TestCase
{
    public function testTriggersEventAndCreatesLog(): void
    {
        $logger = $this->createStub(LoggerInterface::class);
        $logger->expects($this->once())
            ->method('info')
            ->with('Running CronDailyEvent');

        $dispatcher = $this->createStub(EventDispatcherInterface::class);
        $dispatcher->expects($this->once())
            ->method('dispatch')
            ->with(new CronDailyEvent());

        $command = new CronDailyCommand($logger, $dispatcher);
        $commandTester = new CommandTester($command);
        $commandTester->execute([]);

        $this->assertSame(0, $commandTester->getStatusCode());
    }
}
