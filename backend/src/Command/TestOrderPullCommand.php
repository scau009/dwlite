<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\SalesChannel;
use App\Repository\SalesChannelRepository;
use App\Service\ChannelGateway\ChannelGatewayContext;
use App\Service\ChannelGateway\ChannelGatewayInterface;
use App\Service\ChannelGateway\ChannelGatewayRegistry;
use App\Service\ChannelGateway\Dto\Request\PullOrdersRequest;
use App\Service\OrderSyncService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:test-order-pull',
    description: 'Test order pull from a sales channel'
)]
class TestOrderPullCommand extends Command
{
    public function __construct(
        private readonly SalesChannelRepository $salesChannelRepo,
        private readonly OrderSyncService $orderSyncService,
        private readonly ChannelGatewayRegistry $gatewayRegistry,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('channel', InputArgument::OPTIONAL, 'Channel code (e.g., KICKSCREW)', 'KICKSCREW')
            ->addOption('days', null, InputOption::VALUE_OPTIONAL, 'Days to look back', '24')
            ->addOption('api-key', null, InputOption::VALUE_OPTIONAL, 'API key for direct testing (overrides channel config)')
            ->addOption('page', null, InputOption::VALUE_OPTIONAL, 'Page number', '1')
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Only fetch and display, do not save to database');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $channelCode = $input->getArgument('channel');
        $days = (int) $input->getOption('days');
        $apiKey = $input->getOption('api-key');
        $page = (int) $input->getOption('page');
        $dryRun = $input->getOption('dry-run');

        $io->title("Testing Order Pull - Channel: {$channelCode}");

        // Find sales channel
        $salesChannel = $this->salesChannelRepo->findOneBy(['code' => $channelCode]);
        if ($salesChannel === null) {
            $io->error("Sales channel not found: {$channelCode}");
            $io->note('You need to create a SalesChannel record in the database first.');

            return Command::FAILURE;
        }

        $io->info([
            "Channel ID: {$salesChannel->getId()}",
            "Channel Name: {$salesChannel->getName()}",
            "Status: {$salesChannel->getStatus()}",
            "Currency: {$salesChannel->getCurrency()}",
        ]);

        // Check if gateway exists and supports pullOrders
        if (!$this->gatewayRegistry->has($channelCode)) {
            $io->error("No gateway registered for channel: {$channelCode}");

            return Command::FAILURE;
        }

        $gateway = $this->gatewayRegistry->get($channelCode);

        if (!$gateway->supports(ChannelGatewayInterface::OPERATION_PULL_ORDERS)) {
            $io->error('Gateway does not support pullOrders operation');

            return Command::FAILURE;
        }

        $io->success("Gateway found: {$gateway->getChannelName()}");

        // Build time range
        $endTime = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
        $startTime = $endTime->modify("-{$days} days");

        $io->section('Time Range');
        $io->info([
            "Start: {$startTime->format(\DateTimeInterface::ATOM)}",
            "End: {$endTime->format(\DateTimeInterface::ATOM)}",
            "Lookback: {$days} hours",
        ]);

        // If API key provided, override channel config
        if ($apiKey !== null) {
            $io->note('Using provided API key (overriding channel config)');
            $salesChannel->setConfig(array_merge($salesChannel->getConfig() ?? [], ['api_key' => $apiKey]));
        }

        // Check if channel has API key configured
        $hasApiKey = $salesChannel->getConfigValue('api_key') !== null;
        $io->info('Has API Key: '.($hasApiKey ? 'Yes' : 'No'));

        if (!$hasApiKey) {
            $io->error('No API key configured. Either set api_key in SalesChannel.config or use --api-key option.');

            return Command::FAILURE;
        }

        if ($dryRun) {
            return $this->executeDryRun($io, $gateway, $salesChannel, $startTime, $endTime, $page);
        }

        return $this->executeFullSync($io, $salesChannel, $startTime, $endTime, $page);
    }

    private function executeDryRun(
        SymfonyStyle $io,
        ChannelGatewayInterface $gateway,
        SalesChannel $salesChannel,
        \DateTimeImmutable $startTime,
        \DateTimeImmutable $endTime,
        int $page,
    ): int {
        $io->section('Dry Run - Fetching Orders (not saving to database)');

        try {
            $context = new ChannelGatewayContext($salesChannel);
            $request = new PullOrdersRequest(
                startTime: $startTime,
                endTime: $endTime,
                page: $page,
                pageSize: 100,
            );

            $pulledOrders = $gateway->pullOrders($context, $request);

            if (empty($pulledOrders)) {
                $io->warning('No orders found in the specified time range');

                return Command::SUCCESS;
            }

            $io->success(count($pulledOrders).' orders fetched');

            // Display orders in table
            $rows = [];
            foreach ($pulledOrders as $order) {
                $itemInfo = '';
                if (!empty($order->items)) {
                    $firstItem = $order->items[0];
                    $itemInfo = sprintf('%s (Size: %s)', $firstItem->skuCode ?? $firstItem->externalProductId, $firstItem->sizeValue ?? 'N/A');
                }

                $rows[] = [
                    $order->externalOrderId,
                    $order->externalOrderNo ?? '-',
                    $order->status,
                    $order->totalAmount.' '.$order->currency,
                    $itemInfo,
                    $order->placedAt->format('Y-m-d H:i:s'),
                ];
            }

            $io->table(
                ['Order ID', 'Order No', 'Status', 'Amount', 'Item', 'Placed At'],
                $rows
            );

            // Show raw data for first order if verbose
            if ($io->isVerbose()) {
                $io->section('Raw Data (First Order)');
                $io->writeln(json_encode($pulledOrders[0]->rawData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
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

    private function executeFullSync(
        SymfonyStyle $io,
        SalesChannel $salesChannel,
        \DateTimeImmutable $startTime,
        \DateTimeImmutable $endTime,
        int $page,
    ): int {
        $io->section('Full Sync - Pulling and Saving Orders');

        try {
            $stats = $this->orderSyncService->pullOrders(
                $salesChannel,
                $startTime,
                $endTime,
                $page,
                100
            );

            $io->success([
                'Order sync completed!',
                "Created: {$stats['created']}",
                "Updated: {$stats['updated']}",
                "Skipped: {$stats['skipped']}",
                "Errors: {$stats['errors']}",
            ]);

            return Command::SUCCESS;
        } catch (\Throwable $e) {
            $io->error([
                'Failed to sync orders:',
                $e->getMessage(),
            ]);

            if ($io->isVerbose()) {
                $io->writeln($e->getTraceAsString());
            }

            return Command::FAILURE;
        }
    }
}
