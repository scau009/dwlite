<?php

declare(strict_types=1);

namespace App\Command;

use App\Service\ChannelGateway\Provider\KicksCrew\KicksCrewApiClient;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:debug-kickscrew-order',
    description: 'Debug Kickscrew order API - fetch and display raw response'
)]
class DebugKickscrewOrderCommand extends Command
{
    public function __construct(
        private readonly KicksCrewApiClient $apiClient,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('api-key', InputArgument::REQUIRED, 'Kickscrew API key')
            ->addOption('order-id', null, InputOption::VALUE_OPTIONAL, 'Specific order ID to fetch')
            ->addOption('hours', null, InputOption::VALUE_OPTIONAL, 'Hours to look back (default: 24)', '24')
            ->addOption('page', null, InputOption::VALUE_OPTIONAL, 'Page number (default: 1)', '1')
            ->addOption('status', null, InputOption::VALUE_OPTIONAL, 'Order status filter (comma-separated)')
            ->addOption('save', null, InputOption::VALUE_OPTIONAL, 'Save raw response to file');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $apiKey = $input->getArgument('api-key');
        $orderId = $input->getOption('order-id');
        $hours = (int) $input->getOption('hours');
        $page = (int) $input->getOption('page');
        $status = $input->getOption('status');
        $saveFile = $input->getOption('save');

        $io->title('Kickscrew Order API Debug');

        try {
            if ($orderId !== null) {
                // Fetch single order
                return $this->fetchSingleOrder($io, $apiKey, (int) $orderId, $saveFile);
            }

            // Fetch orders list
            return $this->fetchOrdersList($io, $apiKey, $hours, $page, $status, $saveFile);
        } catch (\Throwable $e) {
            $io->error([
                'API request failed:',
                $e->getMessage(),
            ]);

            if ($io->isVerbose()) {
                $io->writeln($e->getTraceAsString());
            }

            return Command::FAILURE;
        }
    }

    private function fetchSingleOrder(SymfonyStyle $io, string $apiKey, int $orderId, ?string $saveFile): int
    {
        $io->section("Fetching Single Order: {$orderId}");

        $response = $this->apiClient->getOrder($apiKey, $orderId);

        $this->displayResponse($io, $response, $saveFile);

        return Command::SUCCESS;
    }

    private function fetchOrdersList(
        SymfonyStyle $io,
        string $apiKey,
        int $hours,
        int $page,
        ?string $status,
        ?string $saveFile,
    ): int {
        $endTime = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
        $startTime = $endTime->modify("-{$hours} hours");

        $dateFrom = $startTime->getTimestamp() * 1000;
        $dateTo = $endTime->getTimestamp() * 1000;

        $io->section('Fetching Orders List');
        $io->info([
            "Start: {$startTime->format(\DateTimeInterface::ATOM)}",
            "End: {$endTime->format(\DateTimeInterface::ATOM)}",
            "Lookback: {$hours} hours",
            "Page: {$page}",
            'Status: '.($status ?? 'all'),
        ]);

        $response = $this->apiClient->getOrders(
            $apiKey,
            page: $page,
            dateFrom: $dateFrom,
            dateTo: $dateTo,
            status: $status
        );

        $this->displayResponse($io, $response, $saveFile);

        // Display summary
        $orders = $response['data'] ?? [];
        $io->newLine();
        $io->success(sprintf('Found %d orders', count($orders)));

        if (!empty($orders)) {
            $io->section('Orders Summary');
            $rows = [];
            foreach ($orders as $order) {
                $rows[] = [
                    $order['order_id'] ?? '-',
                    $order['order_number'] ?? '-',
                    $order['status'] ?? '-',
                    $order['model_no'] ?? '-',
                    ($order['price'] ?? 0).' '.($order['currency'] ?? 'USD'),
                    $order['created_at'] ?? '-',
                    $order['on_hold'] ?? false ? 'Yes' : 'No',
                ];
            }
            $io->table(
                ['Order ID', 'Order No', 'Status', 'Model No', 'Price', 'Created At', 'On Hold'],
                $rows
            );
        }

        return Command::SUCCESS;
    }

    private function displayResponse(SymfonyStyle $io, array $response, ?string $saveFile): void
    {
        $jsonOutput = json_encode($response, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

        $io->section('Raw API Response');
        $io->writeln($jsonOutput);

        if ($saveFile !== null) {
            file_put_contents($saveFile, $jsonOutput);
            $io->success("Response saved to: {$saveFile}");
        }
    }
}
