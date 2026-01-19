<?php

namespace App\Command;

use App\Message\StartProductSyncMessage;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Messenger\MessageBusInterface;

#[AsCommand(
    name: 'app:trigger-product-sync',
    description: 'Manually trigger a full product sync job'
)]
class TriggerProductSyncCommand extends Command
{
    public function __construct(
        private MessageBusInterface $messageBus,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('provider', InputArgument::OPTIONAL, 'Provider name', 'kicksdb');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $provider = $input->getArgument('provider');

        $message = new StartProductSyncMessage($provider);

        $this->messageBus->dispatch($message);

        $io->success("StartProductSyncMessage dispatched for provider: {$provider}");
        $io->note('The sync job will be processed by the worker. Check logs with: docker-compose logs -f worker');

        return Command::SUCCESS;
    }
}
