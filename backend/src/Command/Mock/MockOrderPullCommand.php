<?php

declare(strict_types=1);

namespace App\Command\Mock;

use App\Repository\SalesChannelRepository;
use App\Service\Mock\MockOrderStore;
use App\Service\OrderSyncService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:mock:pull-orders',
    description: 'Trigger order pull for MOCK channel (processes pending mock orders)'
)]
class MockOrderPullCommand extends Command
{
    private const MOCK_CHANNEL_CODE = 'MOCK';

    public function __construct(
        private readonly SalesChannelRepository $salesChannelRepo,
        private readonly OrderSyncService $orderSyncService,
        private readonly MockOrderStore $mockOrderStore,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('days', null, InputOption::VALUE_OPTIONAL, 'Days to look back (for time range)', '1')
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Show pending orders without pulling');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('Pull Mock Orders');

        // Find MOCK sales channel
        $salesChannel = $this->salesChannelRepo->findByCode(self::MOCK_CHANNEL_CODE);
        if ($salesChannel === null) {
            $io->error('MOCK sales channel not found. Please create a SalesChannel with code "MOCK" first.');

            return Command::FAILURE;
        }

        $io->info([
            'Channel ID: '.$salesChannel->getId(),
            'Channel Name: '.$salesChannel->getName(),
            'Status: '.$salesChannel->getStatus(),
        ]);

        // Check pending orders first
        $pendingOrders = $this->mockOrderStore->getPendingOrders($salesChannel->getId());
        $pendingCount = count($pendingOrders);

        if ($pendingCount === 0) {
            $io->warning('No pending mock orders in store. Create some first using:');
            $io->writeln('  php bin/console app:mock:create-order --auto');

            return Command::SUCCESS;
        }

        $io->section("Pending Orders: {$pendingCount}");

        // Show pending orders
        $rows = [];
        foreach ($pendingOrders as $order) {
            $itemInfo = '';
            if (!empty($order->orderData->items)) {
                $firstItem = $order->orderData->items[0];
                $itemInfo = sprintf('%s (%s)', $firstItem->productName, $firstItem->sizeValue ?? 'N/A');
            }

            $rows[] = [
                $order->mockOrderId,
                $order->orderData->externalOrderId,
                $order->fulfillmentType,
                $order->orderData->totalAmount.' '.$order->orderData->currency,
                $itemInfo,
            ];
        }

        $io->table(
            ['Mock ID', 'External ID', 'Fulfillment', 'Amount', 'Item'],
            $rows
        );

        if ($input->getOption('dry-run')) {
            $io->note('Dry run mode - no orders pulled');

            return Command::SUCCESS;
        }

        // Pull orders
        $io->section('Pulling Orders');

        $days = (int) $input->getOption('days');
        $endTime = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
        $startTime = $endTime->modify("-{$days} days");

        try {
            $stats = $this->orderSyncService->pullOrders(
                $salesChannel,
                $startTime,
                $endTime,
                1,
                100
            );

            $io->success([
                'Order pull completed!',
                "Created: {$stats['created']}",
                "Updated: {$stats['updated']}",
                "Skipped: {$stats['skipped']}",
                "Errors: {$stats['errors']}",
            ]);

            if ($stats['created'] > 0) {
                $io->note([
                    'Next steps:',
                    '1. Run "php bin/console messenger:consume async -vv --limit=10" to process messages',
                    '2. Run "php bin/console app:mock:order-status <order-id>" to check order status',
                ]);
            }

            return Command::SUCCESS;
        } catch (\Throwable $e) {
            $io->error([
                'Failed to pull orders:',
                $e->getMessage(),
            ]);

            if ($io->isVerbose()) {
                $io->writeln($e->getTraceAsString());
            }

            return Command::FAILURE;
        }
    }
}
