<?php

declare(strict_types=1);

namespace App\Command\Mock;

use App\Entity\Fulfillment;
use App\Entity\Order;
use App\Repository\FulfillmentRepository;
use App\Repository\OrderRepository;
use App\Repository\SalesChannelRepository;
use App\Repository\SettlementRepository;
use App\Service\Mock\MockOrderStore;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:mock:order-status',
    description: 'Display detailed status of an order (order, items, fulfillments, settlements)'
)]
class MockOrderStatusCommand extends Command
{
    private const MOCK_CHANNEL_CODE = 'MOCK';

    public function __construct(
        private readonly OrderRepository $orderRepository,
        private readonly FulfillmentRepository $fulfillmentRepository,
        private readonly SettlementRepository $settlementRepository,
        private readonly SalesChannelRepository $salesChannelRepository,
        private readonly MockOrderStore $mockOrderStore,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('order-id', InputArgument::REQUIRED, 'Internal order ID or Order No');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $orderId = $input->getArgument('order-id');

        // Find order
        $order = $this->findOrder($orderId);
        if ($order === null) {
            $io->error("Order not found: {$orderId}");

            return Command::FAILURE;
        }

        $io->title("Order Status - {$order->getOrderNo()}");

        // Show order details
        $this->showOrderDetails($io, $order);

        // Show order items
        $this->showOrderItems($io, $order);

        // Show fulfillments
        $fulfillments = $this->showFulfillments($io, $order);

        // Show settlements
        $this->showSettlements($io, $fulfillments);

        // Show mock order status (if MOCK channel)
        if ($order->getSalesChannel()->getCode() === self::MOCK_CHANNEL_CODE) {
            $this->showMockOrderStatus($io, $order);
        }

        // Suggest next actions based on current status
        $this->suggestNextActions($io, $order, $fulfillments);

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

        $rows = [
            ['ID', $order->getId()],
            ['Order No', $order->getOrderNo()],
            ['External ID', $order->getExternalOrderId()],
            ['External No', $order->getExternalOrderNo() ?? '-'],
            ['Status', $this->formatOrderStatus($order->getStatus())],
            ['Payment Status', $this->formatPaymentStatus($order->getPaymentStatus())],
            ['Sales Channel', $order->getSalesChannel()->getName().' ('.$order->getSalesChannel()->getCode().')'],
            ['Total Amount', $order->getTotalAmount().' '.$order->getCurrency()],
            ['Product Amount', $order->getProductAmount().' '.$order->getCurrency()],
            ['Placed At', $order->getPlacedAt()->format('Y-m-d H:i:s')],
            ['Paid At', $order->getPaidAt()?->format('Y-m-d H:i:s') ?? '-'],
            ['Allocated At', $order->getAllocatedAt()?->format('Y-m-d H:i:s') ?? '-'],
            ['Shipped At', $order->getShippedAt()?->format('Y-m-d H:i:s') ?? '-'],
            ['Delivered At', $order->getDeliveredAt()?->format('Y-m-d H:i:s') ?? '-'],
            ['Completed At', $order->getCompletedAt()?->format('Y-m-d H:i:s') ?? '-'],
        ];

        if ($order->getAllocationFailReason() !== null) {
            $rows[] = ['Allocation Fail Reason', $order->getAllocationFailReason()];
        }

        $io->horizontalTable(['Field', 'Value'], $rows);
    }

    private function showOrderItems(SymfonyStyle $io, Order $order): void
    {
        $io->section('Order Items');

        $items = $order->getItems();
        if ($items->isEmpty()) {
            $io->warning('No items found.');

            return;
        }

        $rows = [];
        foreach ($items as $item) {
            $rows[] = [
                $item->getId(),
                $item->getExternalProductId(),
                substr($item->getExternalProductName() ?? '-', 0, 30),
                $item->getSizeValue() ?? '-',
                $item->getQuantity(),
                $item->getShippedQuantity(),
                $item->getUnitPrice().' '.$order->getCurrency(),
                $item->getTotalPrice().' '.$order->getCurrency(),
                $item->getChannelProduct()?->getId() ?? '<fg=yellow>unmatched</>',
            ];
        }

        $io->table(
            ['ID', 'External Product', 'Name', 'Size', 'Qty', 'Shipped', 'Unit Price', 'Total', 'Channel Product'],
            $rows
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
            $io->warning('No fulfillments found.');

            return [];
        }

        $rows = [];
        foreach ($fulfillments as $fulfillment) {
            $rows[] = [
                $fulfillment->getId(),
                $fulfillment->getFulfillmentNo(),
                $this->formatFulfillmentStatus($fulfillment->getStatus()),
                $fulfillment->getFulfillmentType(),
                $fulfillment->getMerchant()?->getName() ?? '-',
                $fulfillment->getWarehouse()->getName(),
                $fulfillment->getTotalQuantity(),
                $fulfillment->getTrackingNumber() ?? '-',
                $fulfillment->getShippedAt()?->format('Y-m-d H:i') ?? '-',
                $fulfillment->getDeliveredAt()?->format('Y-m-d H:i') ?? '-',
                $fulfillment->getCompletedAt()?->format('Y-m-d H:i') ?? '-',
            ];
        }

        $io->table(
            ['ID', 'Fulfillment No', 'Status', 'Type', 'Merchant', 'Warehouse', 'Qty', 'Tracking', 'Shipped', 'Delivered', 'Completed'],
            $rows
        );

        // Show fulfillment items
        foreach ($fulfillments as $fulfillment) {
            $items = $fulfillment->getItems();
            if (!$items->isEmpty()) {
                $io->writeln("  <info>Fulfillment {$fulfillment->getFulfillmentNo()} Items:</info>");
                foreach ($items as $item) {
                    $orderItem = $item->getOrderItem();
                    $sku = $orderItem->getProductSku();
                    $productName = $sku !== null ? $sku->getProduct()->getName() : $orderItem->getExternalProductName();
                    $sizeValue = $sku?->getSizeValue() ?? $orderItem->getSizeValue() ?? 'N/A';
                    $io->writeln(sprintf(
                        '    - %s (Size: %s) x %d @ %s',
                        $productName ?? 'Unknown',
                        $sizeValue,
                        $item->getQuantity(),
                        $item->getListPrice() ?? '-'
                    ));
                }
            }
        }

        return $fulfillments;
    }

    /**
     * @param Fulfillment[] $fulfillments
     */
    private function showSettlements(SymfonyStyle $io, array $fulfillments): void
    {
        $io->section('Settlements');

        if (empty($fulfillments)) {
            $io->note('No fulfillments to check for settlements.');

            return;
        }

        $rows = [];
        foreach ($fulfillments as $fulfillment) {
            $settlement = $this->settlementRepository->findByFulfillment($fulfillment);
            if ($settlement !== null) {
                $rows[] = [
                    $settlement->getId(),
                    $settlement->getSettlementNo(),
                    $fulfillment->getFulfillmentNo(),
                    $settlement->getMerchant()->getName(),
                    $this->formatSettlementStatus($settlement->getStatus()),
                    $settlement->getGrossAmount().' '.$settlement->getCurrency(),
                    $settlement->getCommissionAmount().' '.$settlement->getCurrency(),
                    $settlement->getNetAmount().' '.$settlement->getCurrency(),
                    $settlement->getScheduledSettleAt()->format('Y-m-d'),
                    $settlement->getSettledAt()?->format('Y-m-d H:i') ?? '-',
                ];
            }
        }

        if (empty($rows)) {
            $io->note('No settlements found for the fulfillments.');

            return;
        }

        $io->table(
            ['ID', 'Settlement No', 'Fulfillment', 'Merchant', 'Status', 'Gross', 'Commission', 'Net', 'Scheduled', 'Settled'],
            $rows
        );
    }

    private function showMockOrderStatus(SymfonyStyle $io, Order $order): void
    {
        $io->section('Mock Order Status');

        $salesChannel = $this->salesChannelRepository->findByCode(self::MOCK_CHANNEL_CODE);
        if ($salesChannel === null) {
            $io->warning('MOCK channel not found.');

            return;
        }

        $mockOrder = $this->mockOrderStore->findByExternalOrderId(
            $salesChannel->getId(),
            $order->getExternalOrderId()
        );

        if ($mockOrder === null) {
            $io->note('Mock order not found in store (may have expired after 24 hours).');

            return;
        }

        $io->horizontalTable(
            ['Field', 'Value'],
            [
                ['Mock Order ID', $mockOrder->mockOrderId],
                ['Mock Status', $this->formatMockStatus($mockOrder->status)],
                ['Fulfillment Type', $mockOrder->fulfillmentType],
                ['Channel Product ID', $mockOrder->channelProductId],
                ['Created At', $mockOrder->createdAt->format('Y-m-d H:i:s')],
            ]
        );
    }

    /**
     * @param Fulfillment[] $fulfillments
     */
    private function suggestNextActions(SymfonyStyle $io, Order $order, array $fulfillments): void
    {
        $io->section('Next Actions');

        $suggestions = [];

        switch ($order->getStatus()) {
            case Order::STATUS_PENDING:
                $suggestions[] = 'Order is pending. Run worker to process confirmation: messenger:consume async -vv';
                break;

            case Order::STATUS_ALLOCATED:
            case Order::STATUS_FULFILLING:
                $suggestions[] = 'Order is being fulfilled. Wait for shipping.';
                break;

            case Order::STATUS_SHIPPED:
                $suggestions[] = sprintf(
                    'Order is shipped. To mark as delivered, run: php bin/console app:mock:update-order %s deliver',
                    $order->getId()
                );
                break;

            case Order::STATUS_DELIVERED:
                $suggestions[] = sprintf(
                    'Order is delivered. To mark as completed, run: php bin/console app:mock:update-order %s complete',
                    $order->getId()
                );
                break;

            case Order::STATUS_COMPLETED:
                // Check if settlements exist
                $hasSettlement = false;
                foreach ($fulfillments as $f) {
                    if ($this->settlementRepository->findByFulfillment($f) !== null) {
                        $hasSettlement = true;
                        break;
                    }
                }

                if (!$hasSettlement && !empty($fulfillments)) {
                    $suggestions[] = 'No settlements found. Run worker to process: messenger:consume async -vv';
                } else {
                    $suggestions[] = 'Order flow completed!';
                }
                break;

            case Order::STATUS_ALLOCATION_FAILED:
                $suggestions[] = 'Allocation failed. Check inventory or manually resolve.';
                break;
        }

        if (empty($suggestions)) {
            $suggestions[] = 'No suggested actions.';
        }

        foreach ($suggestions as $suggestion) {
            $io->writeln("  - {$suggestion}");
        }
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
            Order::STATUS_COMPLETED => '<fg=bright-green;options=bold>completed</>',
            Order::STATUS_CANCELLED => '<fg=gray>cancelled</>',
            default => $status,
        };
    }

    private function formatPaymentStatus(string $status): string
    {
        return match ($status) {
            Order::PAYMENT_PENDING => '<fg=yellow>pending</>',
            Order::PAYMENT_PAID => '<fg=green>paid</>',
            Order::PAYMENT_REFUNDED => '<fg=red>refunded</>',
            Order::PAYMENT_PARTIAL_REFUNDED => '<fg=yellow>partial_refunded</>',
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
            Fulfillment::STATUS_COMPLETED => '<fg=bright-green;options=bold>completed</>',
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

    private function formatMockStatus(string $status): string
    {
        return match ($status) {
            'pending' => '<fg=yellow>pending</>',
            'pulled' => '<fg=cyan>pulled</>',
            'confirmed' => '<fg=blue>confirmed</>',
            'shipped' => '<fg=magenta>shipped</>',
            'delivered' => '<fg=green>delivered</>',
            'completed' => '<fg=bright-green;options=bold>completed</>',
            default => $status,
        };
    }
}
