<?php

declare(strict_types=1);

namespace App\Command\Mock;

use App\Entity\ChannelProduct;
use App\Repository\ChannelProductRepository;
use App\Repository\SalesChannelRepository;
use App\Service\ChannelGateway\Dto\Response\PulledOrderDto;
use App\Service\ChannelGateway\Dto\Response\PulledOrderItemDto;
use App\Service\ChannelGateway\Dto\Response\ReceiverDto;
use App\Service\Mock\Dto\MockOrderDto;
use App\Service\Mock\MockOrderStore;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Uid\Ulid;

#[AsCommand(
    name: 'app:mock:create-order',
    description: 'Create a mock order for MOCK channel testing'
)]
class MockOrderCreateCommand extends Command
{
    private const MOCK_CHANNEL_CODE = 'MOCK';

    public function __construct(
        private readonly SalesChannelRepository $salesChannelRepo,
        private readonly ChannelProductRepository $channelProductRepo,
        private readonly MockOrderStore $mockOrderStore,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('channel-product', null, InputOption::VALUE_OPTIONAL, 'Channel product ID to use')
            ->addOption('quantity', null, InputOption::VALUE_OPTIONAL, 'Quantity of items', '1')
            ->addOption('auto', null, InputOption::VALUE_NONE, 'Auto-select an active channel product with stock')
            ->addOption('fulfillment', null, InputOption::VALUE_OPTIONAL, 'Fulfillment type: consignment or self_fulfillment', 'consignment')
            ->addOption('amount', null, InputOption::VALUE_OPTIONAL, 'Order total amount', '100.00');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('Create Mock Order');

        // Find MOCK sales channel
        $salesChannel = $this->salesChannelRepo->findByCode(self::MOCK_CHANNEL_CODE);
        if ($salesChannel === null) {
            $io->error('MOCK sales channel not found. Please create a SalesChannel with code "MOCK" first.');

            return Command::FAILURE;
        }

        $io->info([
            'Channel ID: '.$salesChannel->getId(),
            'Channel Name: '.$salesChannel->getName(),
        ]);

        // Determine which channel product to use
        $channelProductId = $input->getOption('channel-product');
        $autoSelect = $input->getOption('auto');
        $quantity = (int) $input->getOption('quantity');
        $fulfillmentType = $input->getOption('fulfillment');
        $amount = $input->getOption('amount');

        // Validate fulfillment type
        if (!in_array($fulfillmentType, [MockOrderDto::FULFILLMENT_CONSIGNMENT, MockOrderDto::FULFILLMENT_SELF], true)) {
            $io->error('Invalid fulfillment type. Use "consignment" or "self_fulfillment".');

            return Command::FAILURE;
        }

        $channelProduct = null;

        if ($channelProductId !== null) {
            $channelProduct = $this->channelProductRepo->find($channelProductId);
            if ($channelProduct === null) {
                $io->error("Channel product not found: {$channelProductId}");

                return Command::FAILURE;
            }
        } elseif ($autoSelect) {
            // Auto-select an active channel product with stock
            $channelProduct = $this->findActiveProductWithStock($salesChannel->getId());
            if ($channelProduct === null) {
                $io->error('No active channel products with stock found for MOCK channel.');
                $io->note('Make sure you have created and activated channel products for the MOCK channel.');

                return Command::FAILURE;
            }
            $io->note("Auto-selected channel product: {$channelProduct->getId()}");
        } else {
            $io->error('Please specify --channel-product=ID or use --auto to auto-select.');

            return Command::FAILURE;
        }

        // Verify the channel product belongs to the MOCK channel
        if ($channelProduct->getSalesChannel()->getId() !== $salesChannel->getId()) {
            $io->error('Selected channel product does not belong to MOCK channel.');

            return Command::FAILURE;
        }

        // Create the mock order
        $mockOrderId = (string) new Ulid();
        $externalOrderId = 'MOCK_'.time().'_'.substr($mockOrderId, -6);
        $externalOrderNo = 'MO'.date('YmdHis').str_pad((string) random_int(1, 999), 3, '0', STR_PAD_LEFT);
        $now = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));

        // Build product info
        $productSku = $channelProduct->getProductSku();
        $product = $productSku->getProduct();
        $productName = $product->getName();
        $sizeValue = $productSku->getSizeValue() ?? 'N/A';
        $unitPrice = $channelProduct->getPlatformPrice();
        $totalPrice = bcmul($unitPrice, (string) $quantity, 2);

        // Create PulledOrderDto
        $pulledOrder = new PulledOrderDto(
            externalOrderId: $externalOrderId,
            externalOrderNo: $externalOrderNo,
            status: 'paid',
            paymentStatus: 'paid',
            receiver: new ReceiverDto(
                name: 'Mock Receiver',
                phone: '13800138000',
                address: '123 Mock Street, Mock Building, Unit 456',
                province: 'Mock Province',
                city: 'Mock City',
                district: 'Mock District',
                postalCode: '100000',
            ),
            totalAmount: $totalPrice,
            productAmount: $totalPrice,
            shippingAmount: '0.00',
            discountAmount: '0.00',
            currency: $salesChannel->getCurrency(),
            placedAt: $now,
            paidAt: $now,
            items: [
                new PulledOrderItemDto(
                    externalProductId: $channelProduct->getExternalId() ?? $channelProduct->getId(),
                    externalSkuId: $productSku->getId(),
                    productName: $productName,
                    productImage: null,
                    quantity: $quantity,
                    unitPrice: $unitPrice,
                    totalPrice: $totalPrice,
                    skuCode: null,
                    sizeValue: $sizeValue,
                ),
            ],
            rawData: [
                'mock' => true,
                'generated_at' => $now->format(\DateTimeInterface::ATOM),
                'channel_product_id' => $channelProduct->getId(),
                'fulfillment_type' => $fulfillmentType,
            ],
        );

        // Create MockOrderDto
        $mockOrder = new MockOrderDto(
            mockOrderId: $mockOrderId,
            channelProductId: $channelProduct->getId(),
            status: MockOrderDto::STATUS_PENDING,
            fulfillmentType: $fulfillmentType,
            orderData: $pulledOrder,
            createdAt: $now,
        );

        // Store the mock order
        $this->mockOrderStore->store($salesChannel->getId(), $mockOrder);

        $io->success([
            'Mock order created successfully!',
            "Mock Order ID: {$mockOrderId}",
            "External Order ID: {$externalOrderId}",
            "External Order No: {$externalOrderNo}",
            "Channel Product: {$channelProduct->getId()}",
            "Product: {$productName} (Size: {$sizeValue})",
            "Quantity: {$quantity}",
            "Amount: {$totalPrice} {$salesChannel->getCurrency()}",
            "Fulfillment Type: {$fulfillmentType}",
        ]);

        $io->note([
            'Next steps:',
            '1. Run "php bin/console app:mock:list-orders" to see all mock orders',
            '2. Run "php bin/console app:mock:pull-orders" to trigger order sync',
        ]);

        return Command::SUCCESS;
    }

    private function findActiveProductWithStock(string $salesChannelId): ?ChannelProduct
    {
        $salesChannel = $this->salesChannelRepo->find($salesChannelId);
        if ($salesChannel === null) {
            return null;
        }

        $activeProducts = $this->channelProductRepo->findActiveByChannel($salesChannel);

        foreach ($activeProducts as $product) {
            if ($product->getStockQuantity() > 0) {
                return $product;
            }
        }

        // If no products with stock, return first active product
        return $activeProducts[0] ?? null;
    }
}
