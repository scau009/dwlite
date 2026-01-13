<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\ChannelProductSource;
use App\Entity\Order;
use App\Entity\PlatformRule;
use App\Repository\ChannelProductSourceRepository;
use App\Repository\FulfillmentAllocationLogRepository;
use App\Repository\FulfillmentRepository;
use App\Repository\OrderRepository;
use App\Repository\PlatformRuleRepository;
use App\Service\Fulfillment\FulfillmentAllocationService;
use App\Service\RuleEngine\RuleEngineService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:debug-order-allocation',
    description: 'Debug order allocation - show order details, available sources, and allocation logs'
)]
class DebugOrderAllocationCommand extends Command
{
    public function __construct(
        private readonly OrderRepository $orderRepository,
        private readonly ChannelProductSourceRepository $sourceRepository,
        private readonly FulfillmentAllocationLogRepository $allocationLogRepository,
        private readonly FulfillmentRepository $fulfillmentRepository,
        private readonly PlatformRuleRepository $ruleRepository,
        private readonly RuleEngineService $ruleEngine,
        private readonly FulfillmentAllocationService $allocationService,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('order-id', InputArgument::REQUIRED, 'Order ID or Order No')
            ->addOption('allocate', null, InputOption::VALUE_NONE, 'Actually trigger allocation (not dry-run)')
            ->addOption('exclude-merchants', null, InputOption::VALUE_OPTIONAL, 'Comma-separated merchant IDs to exclude')
            ->addOption('show-rules', null, InputOption::VALUE_NONE, 'Show active allocation rules');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $orderId = $input->getArgument('order-id');
        $triggerAllocation = $input->getOption('allocate');
        $excludeMerchantsStr = $input->getOption('exclude-merchants');
        $showRules = $input->getOption('show-rules');

        $excludedMerchantIds = [];
        if ($excludeMerchantsStr !== null) {
            $excludedMerchantIds = array_filter(array_map('trim', explode(',', $excludeMerchantsStr)));
        }

        // Find order
        $order = $this->findOrder($orderId);
        if ($order === null) {
            $io->error("Order not found: {$orderId}");

            return Command::FAILURE;
        }

        $io->title("Order Allocation Debug - {$order->getOrderNo()}");

        // 1. Show order details
        $this->showOrderDetails($io, $order);

        // 2. Show order items with available sources
        $this->showOrderItems($io, $order, $excludedMerchantIds);

        // 3. Show allocation logs
        $this->showAllocationLogs($io, $order);

        // 4. Show fulfillments
        $this->showFulfillments($io, $order);

        // 5. Show rules if requested
        if ($showRules) {
            $this->showAllocationRules($io);
        }

        // 6. Trigger allocation if requested
        if ($triggerAllocation) {
            return $this->triggerAllocation($io, $order, $excludedMerchantIds);
        }

        // Summary
        $io->section('Summary');
        $canAllocate = $order->canAllocate();
        $io->info([
            'Can Allocate: '.($canAllocate ? 'Yes' : 'No'),
            'Current Status: '.$order->getStatus(),
            'Payment Status: '.$order->getPaymentStatus(),
        ]);

        if (!$canAllocate) {
            $io->warning('Order cannot be allocated. Check status and payment status.');
        }

        $io->note('Use --allocate to trigger allocation, --exclude-merchants=id1,id2 to exclude merchants');

        return Command::SUCCESS;
    }

    private function findOrder(string $orderId): ?Order
    {
        // Try by ID first
        $order = $this->orderRepository->find($orderId);
        if ($order !== null) {
            return $order;
        }

        // Try by order number
        return $this->orderRepository->findOneBy(['orderNo' => $orderId]);
    }

    private function showOrderDetails(SymfonyStyle $io, Order $order): void
    {
        $io->section('Order Details');

        $io->horizontalTable(
            ['Field', 'Value'],
            [
                ['Order ID', $order->getId()],
                ['Order No', $order->getOrderNo()],
                ['External Order ID', $order->getExternalOrderId()],
                ['External Order No', $order->getExternalOrderNo() ?? '-'],
                ['Sales Channel', $order->getSalesChannel()->getName().' ('.$order->getSalesChannel()->getCode().')'],
                ['Status', $this->formatStatus($order->getStatus())],
                ['Payment Status', $order->getPaymentStatus()],
                ['Total Amount', $order->getTotalAmount().' '.$order->getCurrency()],
                ['Item Count', (string) $order->getItems()->count()],
                ['Placed At', $order->getPlacedAt()->format('Y-m-d H:i:s')],
                ['Can Allocate', $order->canAllocate() ? 'Yes' : 'No'],
            ]
        );
    }

    private function showOrderItems(SymfonyStyle $io, Order $order, array $excludedMerchantIds): void
    {
        $io->section('Order Items & Available Sources');

        $allocationRules = $this->ruleRepository->findActiveByType(PlatformRule::TYPE_FULFILLMENT_ALLOCATION);

        foreach ($order->getItems() as $index => $item) {
            $io->writeln(sprintf('<info>Item #%d:</info> %s', $index + 1, $item->getSkuCode() ?? 'N/A'));
            $io->writeln(sprintf('  Product Name: %s', $item->getProductName()));
            $io->writeln(sprintf('  Size: %s', $item->getSizeValue() ?? 'N/A'));
            $io->writeln(sprintf('  Quantity: %d', $item->getQuantity()));
            $io->writeln(sprintf('  Unit Price: %s', $item->getUnitPrice()));
            $io->writeln(sprintf('  Allocated Qty: %d', $item->getAllocatedQuantity()));

            $channelProduct = $item->getChannelProduct();
            if ($channelProduct === null) {
                $io->warning('  No channel product linked - cannot allocate!');
                $io->newLine();

                continue;
            }

            $io->writeln(sprintf('  Platform Price: %s', $channelProduct->getPlatformPrice()));

            // Get available sources
            $sources = $this->sourceRepository->findActiveByProduct($channelProduct);

            if (empty($sources)) {
                $io->warning('  No active sources found!');
                $io->newLine();

                continue;
            }

            // Score and display sources
            $io->writeln('');
            $io->writeln('  <comment>Available Sources:</comment>');

            $rows = [];
            foreach ($sources as $source) {
                $merchant = $source->getMerchant();
                $listing = $source->getInventoryListing();
                $isExcluded = in_array($merchant->getId(), $excludedMerchantIds, true);
                $availableQty = $source->getAvailableQuantity();
                $hasStock = $availableQty > 0;
                $priceValid = bccomp($source->getMerchantPrice(), $channelProduct->getPlatformPrice(), 2) <= 0;

                // Calculate score
                $score = $this->calculateScore($source, $item, $allocationRules);

                // Build status flags
                $flags = [];
                if ($isExcluded) {
                    $flags[] = 'EXCLUDED';
                }
                if (!$hasStock) {
                    $flags[] = 'NO_STOCK';
                }
                if (!$priceValid) {
                    $flags[] = 'PRICE_HIGH';
                }
                if (!$listing->isActive()) {
                    $flags[] = 'INACTIVE';
                }
                $flagStr = empty($flags) ? 'OK' : implode(', ', $flags);

                $rows[] = [
                    $source->getId(),
                    $merchant->getName(),
                    $listing->getFulfillmentType(),
                    $source->getPriority(),
                    number_format($score, 2),
                    $availableQty,
                    $source->getMerchantPrice(),
                    $flagStr,
                ];
            }

            // Sort by score descending
            usort($rows, fn ($a, $b) => (float) $b[4] <=> (float) $a[4]);

            $io->table(
                ['Source ID', 'Merchant', 'Type', 'Priority', 'Score', 'Available', 'Price', 'Status'],
                $rows
            );
        }
    }

    private function calculateScore(ChannelProductSource $source, \App\Entity\OrderItem $orderItem, array $rules): float
    {
        $listing = $source->getInventoryListing();
        $channelProduct = $orderItem->getChannelProduct();

        // Build rule context
        $context = [
            'source' => [
                'merchantId' => $source->getMerchant()->getId(),
                'priority' => $source->getPriority(),
                'price' => $source->getMerchantPrice(),
                'availableQuantity' => $source->getAvailableQuantity(),
                'soldQuantity' => $source->getSoldQuantity(),
            ],
            'product' => [
                'platformPrice' => $channelProduct?->getPlatformPrice() ?? '0',
                'skuCode' => $orderItem->getSkuCode() ?? '',
                'categorySlug' => '',
            ],
            'fulfillmentType' => $listing->getFulfillmentType(),
            'order' => [
                'totalAmount' => $orderItem->getOrder()->getTotalAmount(),
                'itemCount' => $orderItem->getOrder()->getItems()->count(),
            ],
        ];

        $defaultScore = 100.0 - (float) $source->getPriority();

        if (empty($rules)) {
            return $defaultScore;
        }

        $ruleData = [];
        foreach ($rules as $rule) {
            $ruleData[] = [
                'expression' => $rule->getExpression(),
                'conditionExpression' => $rule->getConditionExpression(),
                'config' => $rule->getConfig() ?? [],
                'ruleId' => $rule->getId(),
                'ruleType' => $rule->getType(),
            ];
        }

        try {
            $score = $this->ruleEngine->executeRuleChain(
                $ruleData,
                $context,
                $defaultScore,
                'fulfillment_allocation',
                $orderItem->getId()
            );

            return is_numeric($score) ? (float) $score : $defaultScore;
        } catch (\Throwable) {
            return $defaultScore;
        }
    }

    private function showAllocationLogs(SymfonyStyle $io, Order $order): void
    {
        $io->section('Allocation Logs');

        $logs = $this->allocationLogRepository->findByOrder($order);

        if (empty($logs)) {
            $io->note('No allocation logs found.');

            return;
        }

        $rows = [];
        foreach ($logs as $log) {
            $candidates = $log->getCandidateSources();
            $candidateCount = is_array($candidates) ? count($candidates) : 0;

            $rows[] = [
                $log->getAttemptNumber(),
                $log->getOrderItem()?->getSkuCode() ?? '-',
                $this->formatResult($log->getResult()),
                $log->getSelectedMerchantId() ?? '-',
                $log->getSelectedSourceId() ?? '-',
                $candidateCount,
                $log->getFailureReason() ?? '-',
                $log->getCreatedAt()->format('Y-m-d H:i:s'),
            ];
        }

        $io->table(
            ['Attempt', 'SKU', 'Result', 'Merchant', 'Source', 'Candidates', 'Failure Reason', 'Time'],
            $rows
        );

        // Show candidate details for failed logs
        $failedLogs = array_filter($logs, fn ($l) => $l->getResult() !== 'success');
        if (!empty($failedLogs) && $io->isVerbose()) {
            $io->writeln('<comment>Failed Allocation Candidate Details:</comment>');
            foreach ($failedLogs as $log) {
                $candidates = $log->getCandidateSources();
                if (!empty($candidates)) {
                    $io->writeln(sprintf('Attempt #%d (%s):', $log->getAttemptNumber(), $log->getOrderItem()?->getSkuCode() ?? '-'));
                    $io->writeln(json_encode($candidates, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
                }
            }
        }
    }

    private function showFulfillments(SymfonyStyle $io, Order $order): void
    {
        $io->section('Fulfillments');

        $fulfillments = $this->fulfillmentRepository->findByOrder($order);

        if (empty($fulfillments)) {
            $io->note('No fulfillments found.');

            return;
        }

        $rows = [];
        foreach ($fulfillments as $fulfillment) {
            $rows[] = [
                $fulfillment->getId(),
                $fulfillment->getFulfillmentNo(),
                $fulfillment->getFulfillmentType(),
                $fulfillment->getStatus(),
                $fulfillment->getMerchant()?->getName() ?? '-',
                $fulfillment->getWarehouse()->getName(),
                $fulfillment->getItems()->count(),
                $fulfillment->getCreatedAt()->format('Y-m-d H:i:s'),
            ];
        }

        $io->table(
            ['ID', 'Fulfillment No', 'Type', 'Status', 'Merchant', 'Warehouse', 'Items', 'Created'],
            $rows
        );

        // Show fulfillment items
        if ($io->isVerbose()) {
            foreach ($fulfillments as $fulfillment) {
                $io->writeln(sprintf('<comment>Fulfillment %s Items:</comment>', $fulfillment->getFulfillmentNo()));
                $itemRows = [];
                foreach ($fulfillment->getItems() as $item) {
                    $itemRows[] = [
                        $item->getOrderItem()->getSkuCode() ?? '-',
                        $item->getQuantity(),
                        $item->getListPrice() ?? '-',
                        $item->getChannelProductSource()?->getId() ?? '-',
                    ];
                }
                $io->table(['SKU', 'Quantity', 'Price', 'Source ID'], $itemRows);
            }
        }
    }

    private function showAllocationRules(SymfonyStyle $io): void
    {
        $io->section('Active Allocation Rules');

        $rules = $this->ruleRepository->findActiveByType(PlatformRule::TYPE_FULFILLMENT_ALLOCATION);

        if (empty($rules)) {
            $io->note('No active allocation rules found. Default scoring (100 - priority) will be used.');

            return;
        }

        $rows = [];
        foreach ($rules as $rule) {
            $rows[] = [
                $rule->getId(),
                $rule->getName(),
                $rule->getPriority(),
                $rule->getConditionExpression() ?? '-',
                substr($rule->getExpression(), 0, 50).(strlen($rule->getExpression()) > 50 ? '...' : ''),
            ];
        }

        $io->table(
            ['ID', 'Name', 'Priority', 'Condition', 'Expression'],
            $rows
        );
    }

    private function triggerAllocation(SymfonyStyle $io, Order $order, array $excludedMerchantIds): int
    {
        $io->section('Triggering Allocation');

        if (!$order->canAllocate()) {
            $io->error('Order cannot be allocated. Status: '.$order->getStatus().', Payment: '.$order->getPaymentStatus());

            return Command::FAILURE;
        }

        $attemptNumber = $this->allocationLogRepository->countAttemptsByOrder($order) + 1;

        $io->info([
            'Order ID: '.$order->getId(),
            'Attempt Number: '.$attemptNumber,
            'Excluded Merchants: '.(empty($excludedMerchantIds) ? 'None' : implode(', ', $excludedMerchantIds)),
        ]);

        if (!$io->confirm('Proceed with allocation?', false)) {
            $io->note('Allocation cancelled.');

            return Command::SUCCESS;
        }

        try {
            // Option 1: Direct allocation (synchronous)
            $io->writeln('Executing allocation...');
            $result = $this->allocationService->allocateOrder($order, $excludedMerchantIds, $attemptNumber);

            if ($result->success) {
                $io->success([
                    'Allocation successful!',
                    'Fulfillments created: '.count($result->fulfillments),
                ]);

                // Show created fulfillments
                foreach ($result->fulfillments as $fulfillment) {
                    $io->writeln(sprintf(
                        '  - %s (%s): %s, %d items',
                        $fulfillment->getFulfillmentNo(),
                        $fulfillment->getFulfillmentType(),
                        $fulfillment->getMerchant()?->getName() ?? '-',
                        $fulfillment->getItems()->count()
                    ));
                }
            } else {
                $io->warning([
                    'Allocation failed!',
                    'Reason: '.($result->failureReason ?? 'Unknown'),
                ]);
            }

            return Command::SUCCESS;
        } catch (\Throwable $e) {
            $io->error([
                'Allocation error:',
                $e->getMessage(),
            ]);

            if ($io->isVerbose()) {
                $io->writeln($e->getTraceAsString());
            }

            return Command::FAILURE;
        }
    }

    private function formatStatus(string $status): string
    {
        return match ($status) {
            Order::STATUS_PENDING => '<fg=yellow>pending</>',
            Order::STATUS_ALLOCATING => '<fg=cyan>allocating</>',
            Order::STATUS_ALLOCATED => '<fg=green>allocated</>',
            Order::STATUS_ALLOCATION_FAILED => '<fg=red>allocation_failed</>',
            Order::STATUS_FULFILLING => '<fg=blue>fulfilling</>',
            Order::STATUS_SHIPPED => '<fg=green>shipped</>',
            Order::STATUS_DELIVERED => '<fg=green>delivered</>',
            Order::STATUS_COMPLETED => '<fg=green>completed</>',
            Order::STATUS_CANCELLED => '<fg=gray>cancelled</>',
            default => $status,
        };
    }

    private function formatResult(string $result): string
    {
        return match ($result) {
            'success' => '<fg=green>success</>',
            'no_stock' => '<fg=red>no_stock</>',
            'price_invalid' => '<fg=yellow>price_invalid</>',
            'rejected' => '<fg=red>rejected</>',
            'expired' => '<fg=gray>expired</>',
            'no_source' => '<fg=red>no_source</>',
            default => $result,
        };
    }
}
