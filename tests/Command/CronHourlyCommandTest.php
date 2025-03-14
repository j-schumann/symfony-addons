<?php

declare(strict_types=1);

namespace Vrok\SymfonyAddons\Tests\Command;

use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Vrok\SymfonyAddons\Command\CronHourlyCommand;
use Vrok\SymfonyAddons\Event\CronHourlyEvent;

class CronHourlyCommandTest extends KernelTestCase
{
    public function testTriggersEventAndCreatesLog(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())
            ->method('info')
            ->with('Running CronHourlyEvent');

        $dispatcher = $this->createMock(EventDispatcherInterface::class);
        $dispatcher->expects($this->once())
            ->method('dispatch')
            ->with(new CronHourlyEvent());

        $command = new CronHourlyCommand($logger, $dispatcher);
        $commandTester = new CommandTester($command);
        $commandTester->execute([]);

        self::assertSame(
            CronHourlyCommand::SUCCESS,
            $commandTester->getStatusCode()
        );
    }

    public function testService(): void
    {
        $application = new Application(static::bootKernel());
        self::assertTrue($application->has('cron:hourly'));
    }
}
