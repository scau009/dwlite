<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\Fulfillment;
use App\Entity\Order;
use App\Repository\FulfillmentRepository;
use App\Repository\OrderRepository;
use App\Repository\SettlementRepository;
use App\Service\Fulfillment\FulfillmentCompletionService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:debug-order-completion',
    description: 'Debug order completion - manually trigger order status transition to COMPLETED'
)]
class DebugOrderCompletionCommand extends Command
{
    public function __construct(
        private readonly OrderRepository $orderRepository,
        private readonly FulfillmentRepository $fulfillmentRepository,
        private readonly SettlementRepository $settlementRepository,
        private readonly FulfillmentCompletionService $fulfillmentCompletionService,
        private readonly EntityManagerInterface $entityManager,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('order-id', InputArgument::REQUIRED, 'Order ID or Order No')
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Only show information, do not execute changes')
            ->addOption('force', null, InputOption::VALUE_NONE, 'Skip status checks, force complete (mark non-DELIVERED fulfillments as DELIVERED first)')
            ->addOption('skip-settlement', null, InputOption::VALUE_NONE, 'Skip settlement message dispatch');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $orderId = $input->getArgument('order-id');
        $dryRun = $input->getOption('dry-run');
        $force = $input->getOption('force');
        $skipSettlement = $input->getOption('skip-settlement');

        // 1. Find order
        $order = $this->findOrder($orderId);
        if ($order === null) {
            $io->error("Order not found: {$orderId}");

            return Command::FAILURE;
        }

        $io->title("Order Completion Debug - {$order->getOrderNo()}");

        // 2. Show order details
        $this->showOrderDetails($io, $order);

        // 3. Show fulfillments
        $fulfillments = $this->showFulfillments($io, $order);

        // 4. Show existing settlements
        $this->showExistingSettlements($io, $fulfillments);

        // 5. Pre-flight checks
        $checkResult = $this->runPreflightChecks($io, $order, $fulfillments, $force);

        if ($dryRun) {
            $io->note('Dry-run mode: no changes will be made.');
            $io->info('Use without --dry-run to execute the completion.');

            return Command::SUCCESS;
        }

        if (!$checkResult && !$force) {
            $io->error('Pre-flight checks failed. Use --force to override.');

            return Command::FAILURE;
        }

        // 6. Execute completion
        return $this->executeCompletion($io, $order, $fulfillments, $force, $skipSettlement);
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
                ['ID', $order->getId()],
                ['Order No', $order->getOrderNo()],
                ['Status', $this->formatOrderStatus($order->getStatus())],
                ['Sales Channel', $order->getSalesChannel()->getName().' ('.$order->getSalesChannel()->getCode().')'],
                ['Total Amount', $order->getTotalAmount().' '.$order->getCurrency()],
                ['Placed At', $order->getPlacedAt()->format('Y-m-d H:i:s')],
                ['Delivered At', $order->getDeliveredAt()?->format('Y-m-d H:i:s') ?? '-'],
                ['Completed At', $order->getCompletedAt()?->format('Y-m-d H:i:s') ?? '-'],
            ]
        );
    }

    /**
     * @return Fulfillment[]
     */
    private function showFulfillments(SymfonyStyle $io, Order $order): array
    {
        $io->section('Fulfillments');

        $fulfillments = $this->fulfillmentRepository->findByOrder($order);

        if (empty($fulfillments)) {
            $io->warning('No fulfillments found for this order.');

            return [];
        }

        $rows = [];
        foreach ($fulfillments as $fulfillment) {
            $rows[] = [
                $fulfillment->getFulfillmentNo(),
                $this->formatFulfillmentStatus($fulfillment->getStatus()),
                $fulfillment->getFulfillmentType(),
                $fulfillment->getMerchant()?->getName() ?? '-',
                $fulfillment->getWarehouse()->getName(),
                $fulfillment->getItems()->count(),
                $fulfillment->getDeliveredAt()?->format('Y-m-d H:i:s') ?? '-',
                $fulfillment->getCompletedAt()?->format('Y-m-d H:i:s') ?? '-',
            ];
        }

        $io->table(
            ['Fulfillment No', 'Status', 'Type', 'Merchant', 'Warehouse', 'Items', 'Delivered At', 'Completed At'],
            $rows
        );

        return $fulfillments;
    }

    /**
     * @param Fulfillment[] $fulfillments
     */
    private function showExistingSettlements(SymfonyStyle $io, array $fulfillments): void
    {
        $io->section('Existing Settlements');

        if (empty($fulfillments)) {
            $io->note('No fulfillments to check for settlements.');

            return;
        }

        $hasSettlements = false;
        $rows = [];

        foreach ($fulfillments as $fulfillment) {
            $settlement = $this->settlementRepository->findByFulfillment($fulfillment);
            if ($settlement !== null) {
                $hasSettlements = true;
                $rows[] = [
                    $settlement->getSettlementNo(),
                    $fulfillment->getFulfillmentNo(),
                    $this->formatSettlementStatus($settlement->getStatus()),
                    $settlement->getNetAmount().' '.$settlement->getCurrency(),
                    $settlement->getScheduledSettleAt()->format('Y-m-d H:i:s'),
                    $settlement->getSettledAt()?->format('Y-m-d H:i:s') ?? '-',
                ];
            }
        }

        if (!$hasSettlements) {
            $io->note('No settlements found for the fulfillments.');

            return;
        }

        $io->table(
            ['Settlement No', 'Fulfillment No', 'Status', 'Net Amount', 'Scheduled At', 'Settled At'],
            $rows
        );
    }

    /**
     * @param Fulfillment[] $fulfillments
     */
    private function runPreflightChecks(SymfonyStyle $io, Order $order, array $fulfillments, bool $force): bool
    {
        $io->section('Pre-flight Checks');

        $checks = [];
        $allPassed = true;

        // Check 1: Order not already completed
        if ($order->isCompleted()) {
            $checks[] = ['Order not completed', '<fg=yellow>WARN</> - Order is already COMPLETED'];
            if (!$force) {
                $allPassed = false;
            }
        } else {
            $checks[] = ['Order not completed', '<fg=green>OK</>'];
        }

        // Check 2: Has fulfillments
        if (empty($fulfillments)) {
            $checks[] = ['Has fulfillments', '<fg=red>FAIL</> - No fulfillments found'];
            $allPassed = false;
        } else {
            $checks[] = ['Has fulfillments', '<fg=green>OK</> - '.count($fulfillments).' fulfillment(s)'];
        }

        // Check 3: Fulfillments are DELIVERED
        $deliveredCount = 0;
        $completableCount = 0;
        $skippedStatuses = [];

        foreach ($fulfillments as $f) {
            if ($f->isDelivered()) {
                ++$deliveredCount;
                ++$completableCount;
            } elseif ($f->isCompleted()) {
                ++$completableCount; // Already completed, still counts
            } elseif ($f->isCancelled() || $f->isRejected() || $f->isExpired()) {
                $skippedStatuses[] = $f->getStatus();
            }
        }

        if ($deliveredCount === count($fulfillments)) {
            $checks[] = ['Fulfillments are DELIVERED', '<fg=green>OK</> - All '.$deliveredCount.' fulfillment(s) are DELIVERED'];
        } elseif ($completableCount > 0) {
            $checks[] = ['Fulfillments are DELIVERED', '<fg=yellow>WARN</> - '.$deliveredCount.' DELIVERED, '.(count($fulfillments) - $completableCount).' other status'];
        } else {
            $notDeliveredCount = count($fulfillments) - $deliveredCount;
            $checks[] = ['Fulfillments are DELIVERED', '<fg=red>FAIL</> - '.$notDeliveredCount.' fulfillment(s) not DELIVERED'];
            if (!$force) {
                $allPassed = false;
            }
        }

        // Display checks
        foreach ($checks as [$check, $result]) {
            $io->writeln(sprintf(' [%s] %s', $result, $check));
        }
        $io->newLine();

        return $allPassed;
    }

    /**
     * @param Fulfillment[] $fulfillments
     */
    private function executeCompletion(
        SymfonyStyle $io,
        Order $order,
        array $fulfillments,
        bool $force,
        bool $skipSettlement,
    ): int {
        $io->section('Executing...');

        if (empty($fulfillments)) {
            $io->error('Cannot complete order without fulfillments.');

            return Command::FAILURE;
        }

        // Step 1: Force mark as DELIVERED if needed
        if ($force) {
            $io->writeln(' [1/3] Checking fulfillment statuses...');
            $forceDeliveredCount = 0;

            foreach ($fulfillments as $f) {
                // Skip terminal states
                if ($f->isCancelled() || $f->isRejected() || $f->isExpired() || $f->isDelivered() || $f->isCompleted()) {
                    continue;
                }

                // Force mark as DELIVERED
                $f->markDelivered();
                ++$forceDeliveredCount;
                $io->writeln(sprintf('       - %s: force marked DELIVERED', $f->getFulfillmentNo()));
            }

            if ($forceDeliveredCount > 0) {
                $io->writeln(sprintf('       Force marked %d fulfillment(s) as DELIVERED', $forceDeliveredCount));
            } else {
                $io->writeln('       No fulfillments needed force marking');
            }
        } else {
            $io->writeln(' [1/3] Skipping force marking (--force not used)');
        }

        // Step 2: Mark order as COMPLETED
        $io->writeln(' [2/3] Marking order COMPLETED...');
        $previousStatus = $order->getStatus();
        $order->markCompleted();
        $io->writeln(sprintf('       Status: %s -> %s', $previousStatus, $order->getStatus()));

        // Step 3: Complete fulfillments (dispatches settlement messages)
        $io->writeln(' [3/3] Completing fulfillments...');

        if ($skipSettlement) {
            // Manual completion without settlement dispatch
            $completedCount = 0;
            foreach ($fulfillments as $f) {
                if ($f->isDelivered()) {
                    $f->markCompleted();
                    ++$completedCount;
                    $io->writeln(sprintf('       - %s: COMPLETED (settlement skipped)', $f->getFulfillmentNo()));
                } elseif ($f->isCancelled() || $f->isRejected() || $f->isExpired()) {
                    $io->writeln(sprintf('       - %s: skipped (%s)', $f->getFulfillmentNo(), $f->getStatus()));
                }
            }
        } else {
            // Use the service which dispatches settlement messages
            $completedCount = $this->fulfillmentCompletionService->completeOrderFulfillments($order);
            foreach ($fulfillments as $f) {
                if ($f->isCompleted()) {
                    $io->writeln(sprintf('       - %s: COMPLETED, settlement message dispatched', $f->getFulfillmentNo()));
                } elseif ($f->isCancelled() || $f->isRejected() || $f->isExpired()) {
                    $io->writeln(sprintf('       - %s: skipped (%s)', $f->getFulfillmentNo(), $f->getStatus()));
                }
            }
        }

        // Flush changes
        $io->writeln(' [4/4] Flushing changes...');
        $this->entityManager->flush();
        $io->writeln('       Done');

        // Summary
        $io->newLine();
        $io->section('Summary');
        $io->success([
            'Order completed successfully!',
            'Order Status: '.$order->getStatus(),
            'Completed Fulfillments: '.$completedCount,
            'Settlement Messages: '.($skipSettlement ? '0 (skipped)' : $completedCount),
        ]);

        if (!$skipSettlement && $completedCount > 0) {
            $io->note('Run the worker to process settlement messages: docker-compose exec backend php bin/console messenger:consume async -vv');
        }

        return Command::SUCCESS;
    }

    private function formatOrderStatus(string $status): string
    {
        return match ($status) {
            Order::STATUS_PENDING => '<fg=yellow>pending</>',
            Order::STATUS_ALLOCATING => '<fg=cyan>allocating</>',
            Order::STATUS_ALLOCATED => '<fg=green>allocated</>',
            Order::STATUS_ALLOCATION_FAILED => '<fg=red>allocation_failed</>',
            Order::STATUS_FULFILLING => '<fg=blue>fulfilling</>',
            Order::STATUS_SHIPPED => '<fg=green>shipped</>',
            Order::STATUS_DELIVERED => '<fg=green>delivered</>',
            Order::STATUS_COMPLETED => '<fg=bright-green>completed</>',
            Order::STATUS_CANCELLED => '<fg=gray>cancelled</>',
            default => $status,
        };
    }

    private function formatFulfillmentStatus(string $status): string
    {
        return match ($status) {
            Fulfillment::STATUS_PENDING => '<fg=yellow>pending</>',
            Fulfillment::STATUS_PROCESSING => '<fg=cyan>processing</>',
            Fulfillment::STATUS_SHIPPED => '<fg=blue>shipped</>',
            Fulfillment::STATUS_DELIVERED => '<fg=green>delivered</>',
            Fulfillment::STATUS_COMPLETED => '<fg=bright-green>completed</>',
            Fulfillment::STATUS_CANCELLED => '<fg=gray>cancelled</>',
            Fulfillment::STATUS_REJECTED => '<fg=red>rejected</>',
            Fulfillment::STATUS_EXPIRED => '<fg=gray>expired</>',
            default => $status,
        };
    }

    private function formatSettlementStatus(string $status): string
    {
        return match ($status) {
            'pending' => '<fg=yellow>pending</>',
            'settled' => '<fg=green>settled</>',
            'cancelled' => '<fg=gray>cancelled</>',
            default => $status,
        };
    }
}
