<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Dto\Inbound\AddInboundOrderItemRequest;
use App\Dto\Inbound\CreateInboundOrderRequest;
use App\Dto\Inbound\ShipInboundOrderRequest;
use App\Dto\Inbound\UpdateInboundOrderRequest;
use App\Entity\InboundOrder;
use App\Entity\InboundOrderItem;
use App\Entity\Merchant;
use App\Entity\ProductSku;
use App\Entity\Warehouse;
use App\Repository\InboundExceptionRepository;
use App\Repository\InboundOrderItemRepository;
use App\Repository\InboundOrderRepository;
use App\Repository\ProductSkuRepository;
use App\Repository\WarehouseRepository;
use App\Service\InboundOrderService;
use App\Service\InventoryService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class InboundOrderServiceTest extends TestCase
{
    private InboundOrderRepository&MockObject $inboundOrderRepository;
    private InboundOrderItemRepository&MockObject $itemRepository;
    private InboundExceptionRepository&MockObject $exceptionRepository;
    private ProductSkuRepository&MockObject $skuRepository;
    private WarehouseRepository&MockObject $warehouseRepository;
    private InventoryService&MockObject $inventoryService;
    private EntityManagerInterface&MockObject $entityManager;
    private LoggerInterface&MockObject $logger;
    private InboundOrderService $service;

    protected function setUp(): void
    {
        $this->inboundOrderRepository = $this->createMock(InboundOrderRepository::class);
        $this->itemRepository = $this->createMock(InboundOrderItemRepository::class);
        $this->exceptionRepository = $this->createMock(InboundExceptionRepository::class);
        $this->skuRepository = $this->createMock(ProductSkuRepository::class);
        $this->warehouseRepository = $this->createMock(WarehouseRepository::class);
        $this->inventoryService = $this->createMock(InventoryService::class);
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->logger = $this->createMock(LoggerInterface::class);

        $this->service = new InboundOrderService(
            $this->inboundOrderRepository,
            $this->itemRepository,
            $this->exceptionRepository,
            $this->skuRepository,
            $this->warehouseRepository,
            $this->inventoryService,
            $this->entityManager,
            $this->logger,
        );
    }

    public function testCreateInboundOrder(): void
    {
        $merchant = $this->createMock(Merchant::class);
        $merchant->method('getId')->willReturn('merchant-123');

        $warehouse = $this->createMock(Warehouse::class);
        $warehouse->method('getId')->willReturn('warehouse-123');

        $dto = new CreateInboundOrderRequest();
        $dto->warehouseId = 'warehouse-123';
        $dto->merchantNotes = 'Test notes';

        $this->warehouseRepository->expects($this->once())
            ->method('find')
            ->with('warehouse-123')
            ->willReturn($warehouse);

        $this->entityManager->expects($this->once())
            ->method('persist')
            ->with($this->isInstanceOf(InboundOrder::class));

        $this->entityManager->expects($this->once())
            ->method('flush');

        $result = $this->service->createInboundOrder($merchant, $dto);

        $this->assertInstanceOf(InboundOrder::class, $result);
    }

    public function testCreateInboundOrderWarehouseNotFound(): void
    {
        $merchant = $this->createMock(Merchant::class);

        $dto = new CreateInboundOrderRequest();
        $dto->warehouseId = 'invalid-warehouse';

        $this->warehouseRepository->expects($this->once())
            ->method('find')
            ->with('invalid-warehouse')
            ->willReturn(null);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Warehouse not found');

        $this->service->createInboundOrder($merchant, $dto);
    }

    public function testUpdateDraftOrder(): void
    {
        $order = $this->createMock(InboundOrder::class);
        $order->method('isDraft')->willReturn(true);

        $dto = new UpdateInboundOrderRequest();
        $dto->merchantNotes = 'Updated notes';

        $order->expects($this->once())
            ->method('setMerchantNotes')
            ->with('Updated notes');

        $this->entityManager->expects($this->once())
            ->method('flush');

        $result = $this->service->updateOrder($order, $dto);

        $this->assertSame($order, $result);
    }

    public function testUpdateNonDraftOrderFails(): void
    {
        $order = $this->createMock(InboundOrder::class);
        $order->method('isDraft')->willReturn(false);

        $dto = new UpdateInboundOrderRequest();

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('Can only update draft orders');

        $this->service->updateOrder($order, $dto);
    }

    public function testAddItem(): void
    {
        $order = $this->createMock(InboundOrder::class);
        $order->method('isDraft')->willReturn(true);
        $order->method('getId')->willReturn('order-123');

        $sku = $this->createMock(ProductSku::class);
        $sku->method('getId')->willReturn('sku-123');

        $dto = new AddInboundOrderItemRequest();
        $dto->productSkuId = 'sku-123';
        $dto->expectedQuantity = 10;
        $dto->unitCost = '50.00';

        $this->skuRepository->expects($this->once())
            ->method('find')
            ->with('sku-123')
            ->willReturn($sku);

        $this->itemRepository->expects($this->once())
            ->method('findOneBy')
            ->with(['inboundOrder' => $order, 'productSku' => $sku])
            ->willReturn(null);

        $order->expects($this->once())
            ->method('addItem')
            ->with($this->isInstanceOf(InboundOrderItem::class));

        $order->expects($this->once())
            ->method('recalculateTotals');

        $this->entityManager->expects($this->once())
            ->method('persist')
            ->with($this->isInstanceOf(InboundOrderItem::class));

        $this->entityManager->expects($this->once())
            ->method('flush');

        $result = $this->service->addItem($order, $dto);

        $this->assertInstanceOf(InboundOrderItem::class, $result);
    }

    public function testAddItemSkuNotFound(): void
    {
        $order = $this->createMock(InboundOrder::class);
        $order->method('isDraft')->willReturn(true);

        $dto = new AddInboundOrderItemRequest();
        $dto->productSkuId = 'invalid-sku';
        $dto->expectedQuantity = 10;

        $this->skuRepository->expects($this->once())
            ->method('find')
            ->with('invalid-sku')
            ->willReturn(null);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Product SKU not found');

        $this->service->addItem($order, $dto);
    }

    public function testAddDuplicateSkuFails(): void
    {
        $order = $this->createMock(InboundOrder::class);
        $order->method('isDraft')->willReturn(true);

        $sku = $this->createMock(ProductSku::class);
        $existingItem = $this->createMock(InboundOrderItem::class);

        $dto = new AddInboundOrderItemRequest();
        $dto->productSkuId = 'sku-123';
        $dto->expectedQuantity = 10;

        $this->skuRepository->expects($this->once())
            ->method('find')
            ->with('sku-123')
            ->willReturn($sku);

        $this->itemRepository->expects($this->once())
            ->method('findOneBy')
            ->willReturn($existingItem);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('SKU already exists in this order');

        $this->service->addItem($order, $dto);
    }

    public function testAddItemToNonDraftOrderFails(): void
    {
        $order = $this->createMock(InboundOrder::class);
        $order->method('isDraft')->willReturn(false);

        $dto = new AddInboundOrderItemRequest();
        $dto->productSkuId = 'sku-123';
        $dto->expectedQuantity = 10;

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('Can only add items to draft orders');

        $this->service->addItem($order, $dto);
    }

    public function testShipOrder(): void
    {
        $merchant = $this->createMock(Merchant::class);
        $warehouse = $this->createMock(Warehouse::class);

        $order = $this->createMock(InboundOrder::class);
        $order->method('getId')->willReturn('order-123');
        $order->method('getOrderNo')->willReturn('IB20240115000001');
        $order->method('getMerchant')->willReturn($merchant);
        $order->method('getWarehouse')->willReturn($warehouse);

        $dto = new ShipInboundOrderRequest();
        $dto->carrierCode = 'SF';
        $dto->trackingNumber = 'SF123456789';
        $dto->senderName = 'Test Sender';
        $dto->senderPhone = '13800138000';
        $dto->senderAddress = 'Test Address';
        $dto->boxCount = 1;

        $order->expects($this->once())
            ->method('markAsShipped');

        $this->inventoryService->expects($this->once())
            ->method('addInTransitStock')
            ->with($order, 'operator-123', 'Operator');

        $this->entityManager->expects($this->once())
            ->method('flush');

        $result = $this->service->shipOrder($order, $dto, 'operator-123', 'Operator');

        $this->assertSame($order, $result);
    }

    public function testDeleteOrder(): void
    {
        $order = $this->createMock(InboundOrder::class);
        $order->method('isDraft')->willReturn(true);
        $order->method('getId')->willReturn('order-123');
        $order->method('getOrderNo')->willReturn('IB20240115000001');
        $order->method('getItems')->willReturn(new \Doctrine\Common\Collections\ArrayCollection());

        $this->entityManager->expects($this->once())
            ->method('remove')
            ->with($order);

        $this->entityManager->expects($this->once())
            ->method('flush');

        $this->service->deleteOrder($order);
    }

    public function testDeleteNonDraftOrderFails(): void
    {
        $order = $this->createMock(InboundOrder::class);
        $order->method('isDraft')->willReturn(false);

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('Only draft orders can be deleted');

        $this->service->deleteOrder($order);
    }

    public function testCancelOrder(): void
    {
        $order = $this->createMock(InboundOrder::class);
        $order->method('getId')->willReturn('order-123');
        $order->method('getOrderNo')->willReturn('IB20240115000001');
        $order->method('isShipped')->willReturn(true);

        $this->inventoryService->expects($this->once())
            ->method('rollbackInTransit')
            ->with($order, 'operator-123', 'Operator');

        $order->expects($this->once())
            ->method('cancel')
            ->with('Test cancellation reason');

        $this->entityManager->expects($this->once())
            ->method('flush');

        $result = $this->service->cancelOrder($order, 'Test cancellation reason', 'operator-123', 'Operator');

        $this->assertSame($order, $result);
    }
}
