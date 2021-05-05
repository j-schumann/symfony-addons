<?php

declare(strict_types=1);

namespace Vrok\SymfonyAddons\Tests\Command;

use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Vrok\SymfonyAddons\Command\CronMonthlyCommand;
use Vrok\SymfonyAddons\Event\CronMonthlyEvent;

class CronMonthlyCommandTest extends TestCase
{
    public function testTriggersEventAndCreatesLog(): void
    {
        $logger = $this->createStub(LoggerInterface::class);
        $logger->expects($this->once())
            ->method('info')
            ->with('Running CronMonthlyEvent');

        $dispatcher = $this->createStub(EventDispatcherInterface::class);
        $dispatcher->expects($this->once())
            ->method('dispatch')
            ->with(new CronMonthlyEvent());

        $command = new CronMonthlyCommand($logger, $dispatcher);
        $commandTester = new CommandTester($command);
        $commandTester->execute([]);

        $this->assertSame(0, $commandTester->getStatusCode());
    }
}
