<?php

declare(strict_types=1);

namespace App\Command\Mock;

use App\Entity\Order;
use App\Repository\OrderRepository;
use App\Repository\SalesChannelRepository;
use App\Service\Fulfillment\FulfillmentCompletionService;
use App\Service\Mock\Dto\MockOrderDto;
use App\Service\Mock\MockOrderStore;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:mock:update-order',
    description: 'Simulate order status changes for testing (deliver, complete)'
)]
class MockOrderUpdateCommand extends Command
{
    private const MOCK_CHANNEL_CODE = 'MOCK';

    private const ACTION_DELIVER = 'deliver';
    private const ACTION_COMPLETE = 'complete';

    public function __construct(
        private readonly SalesChannelRepository $salesChannelRepo,
        private readonly OrderRepository $orderRepo,
        private readonly MockOrderStore $mockOrderStore,
        private readonly FulfillmentCompletionService $fulfillmentCompletionService,
        private readonly EntityManagerInterface $entityManager,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('order-id', InputArgument::REQUIRED, 'Internal order ID (ULID)')
            ->addArgument('action', InputArgument::REQUIRED, 'Action to perform: deliver, complete');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $orderId = $input->getArgument('order-id');
        $action = strtolower($input->getArgument('action'));

        $io->title("Mock Order Update: {$action}");

        // Validate action
        if (!in_array($action, [self::ACTION_DELIVER, self::ACTION_COMPLETE], true)) {
            $io->error("Invalid action: {$action}. Use 'deliver' or 'complete'.");

            return Command::FAILURE;
        }

        // Find the order
        $order = $this->orderRepo->find($orderId);
        if ($order === null) {
            $io->error("Order not found: {$orderId}");

            return Command::FAILURE;
        }

        // Verify it's a MOCK channel order
        if ($order->getSalesChannel()->getCode() !== self::MOCK_CHANNEL_CODE) {
            $io->error("Order is not from MOCK channel. Channel: {$order->getSalesChannel()->getCode()}");

            return Command::FAILURE;
        }

        $io->info([
            "Order ID: {$order->getId()}",
            "Order No: {$order->getOrderNo()}",
            "External ID: {$order->getExternalOrderId()}",
            "Current Status: {$order->getStatus()}",
        ]);

        // Execute action
        try {
            match ($action) {
                self::ACTION_DELIVER => $this->handleDeliver($io, $order),
                self::ACTION_COMPLETE => $this->handleComplete($io, $order),
            };

            return Command::SUCCESS;
        } catch (\Throwable $e) {
            $io->error([
                'Failed to update order:',
                $e->getMessage(),
            ]);

            if ($io->isVerbose()) {
                $io->writeln($e->getTraceAsString());
            }

            return Command::FAILURE;
        }
    }

    private function handleDeliver(SymfonyStyle $io, Order $order): void
    {
        // Check current status
        if ($order->getStatus() !== Order::STATUS_SHIPPED) {
            $io->warning("Order status is '{$order->getStatus()}', expected 'shipped'. Proceeding anyway...");
        }

        // Mark order as delivered
        $order->markDelivered();

        // Mark all shipped fulfillments as delivered
        $deliveredCount = 0;
        foreach ($order->getFulfillments() as $fulfillment) {
            if ($fulfillment->isShipped()) {
                $fulfillment->markDelivered();
                ++$deliveredCount;
                $io->info("Fulfillment {$fulfillment->getFulfillmentNo()} marked as delivered");
            }
        }

        $this->entityManager->flush();

        // Update mock order status
        $this->updateMockOrderStatus($order, MockOrderDto::STATUS_DELIVERED);

        $io->success([
            'Order marked as DELIVERED',
            "Order ID: {$order->getId()}",
            "Fulfillments delivered: {$deliveredCount}",
        ]);

        $io->note([
            'Next step:',
            "Run: php bin/console app:mock:update-order {$order->getId()} complete",
        ]);
    }

    private function handleComplete(SymfonyStyle $io, Order $order): void
    {
        // Check current status
        if ($order->getStatus() !== Order::STATUS_DELIVERED) {
            $io->warning("Order status is '{$order->getStatus()}', expected 'delivered'. Proceeding anyway...");
        }

        // Mark order as completed
        $order->markCompleted();

        // Complete fulfillments and trigger settlement creation
        $completedCount = $this->fulfillmentCompletionService->completeOrderFulfillments($order);

        $this->entityManager->flush();

        // Update mock order status
        $this->updateMockOrderStatus($order, MockOrderDto::STATUS_COMPLETED);

        $io->success([
            'Order marked as COMPLETED',
            "Order ID: {$order->getId()}",
            "Fulfillments completed: {$completedCount}",
            "Settlement messages dispatched: {$completedCount}",
        ]);

        $io->note([
            'Next steps:',
            '1. Run "php bin/console messenger:consume async -vv --limit=10" to process settlement messages',
            "2. Run: php bin/console app:mock:order-status {$order->getId()} to verify settlement created",
        ]);
    }

    private function updateMockOrderStatus(Order $order, string $newStatus): void
    {
        $salesChannel = $this->salesChannelRepo->findByCode(self::MOCK_CHANNEL_CODE);
        if ($salesChannel === null) {
            return;
        }

        $mockOrder = $this->mockOrderStore->findByExternalOrderId(
            $salesChannel->getId(),
            $order->getExternalOrderId()
        );

        if ($mockOrder !== null) {
            $this->mockOrderStore->updateStatus(
                $salesChannel->getId(),
                $mockOrder->mockOrderId,
                $newStatus
            );
        }
    }
}
