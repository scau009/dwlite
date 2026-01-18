<?php

declare(strict_types=1);

namespace App\Command\Mock;

use App\Repository\SalesChannelRepository;
use App\Service\Mock\MockOrderStore;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:mock:list-orders',
    description: 'List mock orders in MockOrderStore'
)]
class MockOrderListCommand extends Command
{
    private const MOCK_CHANNEL_CODE = 'MOCK';

    public function __construct(
        private readonly SalesChannelRepository $salesChannelRepo,
        private readonly MockOrderStore $mockOrderStore,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('status', null, InputOption::VALUE_OPTIONAL, 'Filter by status (pending, pulled, confirmed, shipped, delivered, completed)')
            ->addOption('clear', null, InputOption::VALUE_NONE, 'Clear all mock orders');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('Mock Orders');

        // Find MOCK sales channel
        $salesChannel = $this->salesChannelRepo->findByCode(self::MOCK_CHANNEL_CODE);
        if ($salesChannel === null) {
            $io->error('MOCK sales channel not found.');

            return Command::FAILURE;
        }

        $channelId = $salesChannel->getId();

        // Handle clear option
        if ($input->getOption('clear')) {
            $count = $this->mockOrderStore->clearChannel($channelId);
            $io->success("Cleared {$count} mock orders.");

            return Command::SUCCESS;
        }

        // Get orders
        $status = $input->getOption('status');
        $orders = $this->mockOrderStore->getAllOrders($channelId, $status);

        if (empty($orders)) {
            $io->warning($status !== null
                ? "No mock orders found with status: {$status}"
                : 'No mock orders found.');

            return Command::SUCCESS;
        }

        $io->info('Found '.count($orders).' mock orders:');

        // Display as table
        $rows = [];
        foreach ($orders as $order) {
            $itemInfo = '';
            if (!empty($order->orderData->items)) {
                $firstItem = $order->orderData->items[0];
                $itemInfo = sprintf(
                    '%s (Size: %s, Qty: %d)',
                    substr($firstItem->productName, 0, 30),
                    $firstItem->sizeValue ?? 'N/A',
                    $firstItem->quantity
                );
            }

            $rows[] = [
                $order->mockOrderId,
                $order->orderData->externalOrderId,
                $this->formatStatus($order->status),
                $order->fulfillmentType,
                $order->orderData->totalAmount.' '.$order->orderData->currency,
                $itemInfo,
                $order->internalOrderId ?? '-',
                $order->createdAt->format('Y-m-d H:i:s'),
            ];
        }

        $io->table(
            ['Mock ID', 'External ID', 'Status', 'Fulfillment', 'Amount', 'Item', 'Internal ID', 'Created'],
            $rows
        );

        // Summary by status
        $statusCounts = [];
        foreach ($orders as $order) {
            $statusCounts[$order->status] = ($statusCounts[$order->status] ?? 0) + 1;
        }

        $io->section('Summary');
        foreach ($statusCounts as $s => $count) {
            $io->writeln("  {$this->formatStatus($s)}: {$count}");
        }

        return Command::SUCCESS;
    }

    private function formatStatus(string $status): string
    {
        return match ($status) {
            'pending' => '<fg=yellow>pending</>',
            'pulled' => '<fg=cyan>pulled</>',
            'confirmed' => '<fg=blue>confirmed</>',
            'shipped' => '<fg=magenta>shipped</>',
            'delivered' => '<fg=green>delivered</>',
            'completed' => '<fg=green;options=bold>completed</>',
            default => $status,
        };
    }
}
