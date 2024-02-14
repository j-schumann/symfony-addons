<?php

declare(strict_types=1);

namespace Vrok\SymfonyAddons\Command;

use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Vrok\SymfonyAddons\Event\CronMonthlyEvent;

#[AsCommand('cron:monthly')]
class CronMonthlyCommand extends Command
{
    public function __construct(
        private readonly LoggerInterface $logger,
        private readonly EventDispatcherInterface $dispatcher)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->setDescription('Calls all event subscribers listening '
            .'to the CronMonthlyEvent. To be called via crontab automatically.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->logger->info('Running CronMonthlyEvent');
        $this->dispatcher->dispatch(new CronMonthlyEvent());

        return self::SUCCESS;
    }
}
