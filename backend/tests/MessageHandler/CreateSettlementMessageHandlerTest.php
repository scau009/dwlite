<?php

declare(strict_types=1);

namespace App\Tests\MessageHandler;

use App\Entity\Fulfillment;
use App\Entity\FulfillmentItem;
use App\Entity\Merchant;
use App\Entity\Order;
use App\Entity\SalesChannel;
use App\Entity\Settlement;
use App\Message\CreateSettlementMessage;
use App\MessageHandler\CreateSettlementMessageHandler;
use App\Repository\FulfillmentRepository;
use App\Repository\SettlementRepository;
use App\Service\BusinessNoGenerator;
use App\Service\OpenApi\WebhookService;
use App\Service\RuleEngine\PlatformRuleService;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Lock\LockInterface;

class CreateSettlementMessageHandlerTest extends TestCase
{
    private EntityManagerInterface&MockObject $entityManager;
    private FulfillmentRepository&MockObject $fulfillmentRepository;
    private SettlementRepository&MockObject $settlementRepository;
    private BusinessNoGenerator&MockObject $businessNoGenerator;
    private LockFactory&MockObject $lockFactory;
    private WebhookService&MockObject $webhookService;
    private PlatformRuleService&MockObject $platformRuleService;
    private LoggerInterface&MockObject $logger;
    private CreateSettlementMessageHandler $handler;

    protected function setUp(): void
    {
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->fulfillmentRepository = $this->createMock(FulfillmentRepository::class);
        $this->settlementRepository = $this->createMock(SettlementRepository::class);
        $this->businessNoGenerator = $this->createMock(BusinessNoGenerator::class);
        $this->lockFactory = $this->createMock(LockFactory::class);
        $this->webhookService = $this->createMock(WebhookService::class);
        $this->platformRuleService = $this->createMock(PlatformRuleService::class);
        $this->logger = $this->createMock(LoggerInterface::class);

        $this->handler = new CreateSettlementMessageHandler(
            $this->entityManager,
            $this->fulfillmentRepository,
            $this->settlementRepository,
            $this->businessNoGenerator,
            $this->lockFactory,
            $this->webhookService,
            $this->platformRuleService,
            $this->logger,
        );
    }

    public function testHandleSuccessfulSettlementCreation(): void
    {
        $message = new CreateSettlementMessage('fulfillment-123');

        $lock = $this->createMock(LockInterface::class);
        $lock->expects($this->once())
            ->method('acquire')
            ->with(false)
            ->willReturn(true);
        $lock->expects($this->once())
            ->method('release');

        $this->lockFactory->expects($this->once())
            ->method('createLock')
            ->willReturn($lock);

        $merchant = $this->createMock(Merchant::class);
        $merchant->method('getId')->willReturn('merchant-123');

        $salesChannel = $this->createMock(SalesChannel::class);
        $salesChannel->method('getCode')->willReturn('KICKSCREW');

        $order = $this->createMock(Order::class);
        $order->method('getCurrency')->willReturn('CNY');
        $order->method('getSalesChannel')->willReturn($salesChannel);

        $fulfillmentItem = $this->createMock(FulfillmentItem::class);
        $fulfillmentItem->method('getCommissionRate')->willReturn('5.00');

        $fulfillment = $this->createMock(Fulfillment::class);
        $fulfillment->method('getId')->willReturn('fulfillment-123');
        $fulfillment->method('isCompleted')->willReturn(true);
        $fulfillment->method('getMerchant')->willReturn($merchant);
        $fulfillment->method('getOrder')->willReturn($order);
        $fulfillment->method('getCompletedAt')->willReturn(
            new \DateTimeImmutable('now', new \DateTimeZone('UTC'))
        );
        $fulfillment->method('getItems')->willReturn(
            new ArrayCollection([$fulfillmentItem])
        );

        $this->fulfillmentRepository->expects($this->once())
            ->method('find')
            ->with('fulfillment-123')
            ->willReturn($fulfillment);

        $this->settlementRepository->expects($this->once())
            ->method('findOneBy')
            ->with(['fulfillment' => $fulfillment])
            ->willReturn(null);

        $this->businessNoGenerator->expects($this->once())
            ->method('generateSettlementNo')
            ->willReturn('ST20240115000001');

        $this->platformRuleService->expects($this->once())
            ->method('getSettlementFeeRate')
            ->with('merchant-123', 'KICKSCREW')
            ->willReturn('5.00');

        $this->entityManager->expects($this->once())
            ->method('persist')
            ->with($this->isInstanceOf(Settlement::class));

        $this->entityManager->expects($this->once())
            ->method('flush');

        ($this->handler)($message);
    }

    public function testHandleLockAcquisitionFailed(): void
    {
        $message = new CreateSettlementMessage('fulfillment-123');

        $lock = $this->createMock(LockInterface::class);
        $lock->expects($this->once())
            ->method('acquire')
            ->with(false)
            ->willReturn(false);

        $this->lockFactory->expects($this->once())
            ->method('createLock')
            ->willReturn($lock);

        $this->fulfillmentRepository->expects($this->never())
            ->method('find');

        $this->logger->expects($this->once())
            ->method('info')
            ->with(
                'Settlement creation already in progress',
                $this->arrayHasKey('fulfillmentId')
            );

        ($this->handler)($message);
    }

    public function testHandleFulfillmentNotFound(): void
    {
        $message = new CreateSettlementMessage('fulfillment-999');

        $lock = $this->createMock(LockInterface::class);
        $lock->method('acquire')->willReturn(true);
        $lock->expects($this->once())->method('release');

        $this->lockFactory->method('createLock')->willReturn($lock);

        $this->fulfillmentRepository->expects($this->once())
            ->method('find')
            ->willReturn(null);

        $this->entityManager->expects($this->never())
            ->method('persist');

        $this->logger->expects($this->once())
            ->method('warning')
            ->with(
                'Fulfillment not found',
                $this->arrayHasKey('fulfillmentId')
            );

        ($this->handler)($message);
    }

    public function testHandleFulfillmentNotCompleted(): void
    {
        $message = new CreateSettlementMessage('fulfillment-123');

        $lock = $this->createMock(LockInterface::class);
        $lock->method('acquire')->willReturn(true);
        $lock->expects($this->once())->method('release');

        $this->lockFactory->method('createLock')->willReturn($lock);

        $fulfillment = $this->createMock(Fulfillment::class);
        $fulfillment->method('getId')->willReturn('fulfillment-123');
        $fulfillment->method('isCompleted')->willReturn(false);
        $fulfillment->method('getStatus')->willReturn('shipped');

        $this->fulfillmentRepository->expects($this->once())
            ->method('find')
            ->willReturn($fulfillment);

        $this->entityManager->expects($this->never())
            ->method('persist');

        $this->logger->expects($this->once())
            ->method('warning')
            ->with(
                'Fulfillment not completed',
                $this->callback(function ($context) {
                    return isset($context['status']) && $context['status'] === 'shipped';
                })
            );

        ($this->handler)($message);
    }

    public function testHandleSettlementAlreadyExists(): void
    {
        $message = new CreateSettlementMessage('fulfillment-123');

        $lock = $this->createMock(LockInterface::class);
        $lock->method('acquire')->willReturn(true);
        $lock->expects($this->once())->method('release');

        $this->lockFactory->method('createLock')->willReturn($lock);

        $fulfillment = $this->createMock(Fulfillment::class);
        $fulfillment->method('isCompleted')->willReturn(true);

        $existingSettlement = $this->createMock(Settlement::class);
        $existingSettlement->method('getId')->willReturn('settlement-456');

        $this->fulfillmentRepository->expects($this->once())
            ->method('find')
            ->willReturn($fulfillment);

        $this->settlementRepository->expects($this->once())
            ->method('findOneBy')
            ->willReturn($existingSettlement);

        $this->businessNoGenerator->expects($this->never())
            ->method('generateSettlementNo');

        $this->entityManager->expects($this->never())
            ->method('persist');

        $this->logger->expects($this->once())
            ->method('info')
            ->with(
                'Settlement already exists',
                $this->callback(function ($context) {
                    return isset($context['settlementId']) && $context['settlementId'] === 'settlement-456';
                })
            );

        ($this->handler)($message);
    }

    /**
     * 测试平台仓场景：履约单无商户，但履约明细有商户.
     */
    public function testHandlePlatformWarehouseMerchantFromItem(): void
    {
        $message = new CreateSettlementMessage('fulfillment-123');

        $lock = $this->createMock(LockInterface::class);
        $lock->expects($this->once())
            ->method('acquire')
            ->with(false)
            ->willReturn(true);
        $lock->expects($this->once())
            ->method('release');

        $this->lockFactory->expects($this->once())
            ->method('createLock')
            ->willReturn($lock);

        $merchant = $this->createMock(Merchant::class);
        $merchant->method('getId')->willReturn('merchant-123');

        $salesChannel = $this->createMock(SalesChannel::class);
        $salesChannel->method('getCode')->willReturn('KICKSCREW');

        $order = $this->createMock(Order::class);
        $order->method('getCurrency')->willReturn('USD');
        $order->method('getSalesChannel')->willReturn($salesChannel);

        $fulfillmentItem = $this->createMock(FulfillmentItem::class);
        $fulfillmentItem->method('getCommissionRate')->willReturn('5.00');
        $fulfillmentItem->method('getMerchant')->willReturn($merchant);

        $fulfillment = $this->createMock(Fulfillment::class);
        $fulfillment->method('getId')->willReturn('fulfillment-123');
        $fulfillment->method('isCompleted')->willReturn(true);
        $fulfillment->method('getMerchant')->willReturn(null); // 平台仓履约单无商户
        $fulfillment->method('getOrder')->willReturn($order);
        $fulfillment->method('getCompletedAt')->willReturn(
            new \DateTimeImmutable('now', new \DateTimeZone('UTC'))
        );
        $fulfillment->method('getItems')->willReturn(
            new ArrayCollection([$fulfillmentItem])
        );

        $this->fulfillmentRepository->expects($this->once())
            ->method('find')
            ->with('fulfillment-123')
            ->willReturn($fulfillment);

        $this->settlementRepository->expects($this->once())
            ->method('findOneBy')
            ->with(['fulfillment' => $fulfillment])
            ->willReturn(null);

        $this->businessNoGenerator->expects($this->once())
            ->method('generateSettlementNo')
            ->willReturn('ST20240115000001');

        $this->platformRuleService->expects($this->once())
            ->method('getSettlementFeeRate')
            ->with('merchant-123', 'KICKSCREW')
            ->willReturn('5.00');

        $this->entityManager->expects($this->once())
            ->method('persist')
            ->with($this->isInstanceOf(Settlement::class));

        $this->entityManager->expects($this->once())
            ->method('flush');

        ($this->handler)($message);
    }

    /**
     * 测试商户缺失场景：履约单和履约明细都无商户.
     */
    public function testHandleMerchantNotFound(): void
    {
        $message = new CreateSettlementMessage('fulfillment-123');

        $lock = $this->createMock(LockInterface::class);
        $lock->method('acquire')->willReturn(true);
        $lock->expects($this->once())->method('release');

        $this->lockFactory->method('createLock')->willReturn($lock);

        $fulfillmentItem = $this->createMock(FulfillmentItem::class);
        $fulfillmentItem->method('getMerchant')->willReturn(null);

        $fulfillment = $this->createMock(Fulfillment::class);
        $fulfillment->method('getId')->willReturn('fulfillment-123');
        $fulfillment->method('isCompleted')->willReturn(true);
        $fulfillment->method('getMerchant')->willReturn(null);
        $fulfillment->method('getFulfillmentType')->willReturn('platform_warehouse');
        $fulfillment->method('getItems')->willReturn(
            new ArrayCollection([$fulfillmentItem])
        );

        $this->fulfillmentRepository->expects($this->once())
            ->method('find')
            ->willReturn($fulfillment);

        $this->settlementRepository->expects($this->once())
            ->method('findOneBy')
            ->willReturn(null);

        $this->entityManager->expects($this->never())
            ->method('persist');

        $this->logger->expects($this->once())
            ->method('error')
            ->with(
                'Cannot create settlement: merchant not found',
                $this->callback(function ($context) {
                    return isset($context['fulfillmentId'])
                        && isset($context['fulfillmentType'])
                        && $context['fulfillmentType'] === 'platform_warehouse';
                })
            );

        ($this->handler)($message);
    }

    /**
     * 测试结算单使用订单中的货币.
     */
    public function testSettlementUsesCurrencyFromOrder(): void
    {
        $message = new CreateSettlementMessage('fulfillment-123');

        $lock = $this->createMock(LockInterface::class);
        $lock->method('acquire')->willReturn(true);
        $lock->expects($this->once())->method('release');

        $this->lockFactory->method('createLock')->willReturn($lock);

        $merchant = $this->createMock(Merchant::class);
        $merchant->method('getId')->willReturn('merchant-123');

        $salesChannel = $this->createMock(SalesChannel::class);
        $salesChannel->method('getCode')->willReturn('KICKSCREW');

        $order = $this->createMock(Order::class);
        $order->method('getCurrency')->willReturn('USD'); // 使用 USD
        $order->method('getSalesChannel')->willReturn($salesChannel);

        $fulfillmentItem = $this->createMock(FulfillmentItem::class);
        $fulfillmentItem->method('getCommissionRate')->willReturn('5.00');

        $fulfillment = $this->createMock(Fulfillment::class);
        $fulfillment->method('getId')->willReturn('fulfillment-123');
        $fulfillment->method('isCompleted')->willReturn(true);
        $fulfillment->method('getMerchant')->willReturn($merchant);
        $fulfillment->method('getOrder')->willReturn($order);
        $fulfillment->method('getCompletedAt')->willReturn(
            new \DateTimeImmutable('now', new \DateTimeZone('UTC'))
        );
        $fulfillment->method('getItems')->willReturn(
            new ArrayCollection([$fulfillmentItem])
        );

        $this->fulfillmentRepository->method('find')->willReturn($fulfillment);
        $this->settlementRepository->method('findOneBy')->willReturn(null);
        $this->businessNoGenerator->method('generateSettlementNo')->willReturn('ST20240115000001');
        $this->platformRuleService->method('getSettlementFeeRate')->willReturn('5.00');

        // 验证结算单使用 USD 货币
        $this->entityManager->expects($this->once())
            ->method('persist')
            ->with($this->callback(function (Settlement $settlement) {
                return $settlement->getCurrency() === 'USD';
            }));

        ($this->handler)($message);
    }

    /**
     * 测试结算单使用规则引擎返回的佣金费率.
     */
    public function testSettlementUsesCommissionRateFromRuleEngine(): void
    {
        $message = new CreateSettlementMessage('fulfillment-123');

        $lock = $this->createMock(LockInterface::class);
        $lock->method('acquire')->willReturn(true);
        $lock->expects($this->once())->method('release');

        $this->lockFactory->method('createLock')->willReturn($lock);

        $merchant = $this->createMock(Merchant::class);
        $merchant->method('getId')->willReturn('merchant-123');

        $salesChannel = $this->createMock(SalesChannel::class);
        $salesChannel->method('getCode')->willReturn('KICKSCREW');

        $order = $this->createMock(Order::class);
        $order->method('getCurrency')->willReturn('CNY');
        $order->method('getSalesChannel')->willReturn($salesChannel);

        $fulfillmentItem = $this->createMock(FulfillmentItem::class);
        $fulfillmentItem->method('getCommissionRate')->willReturn(null); // 无商品级别费率

        $fulfillment = $this->createMock(Fulfillment::class);
        $fulfillment->method('getId')->willReturn('fulfillment-123');
        $fulfillment->method('isCompleted')->willReturn(true);
        $fulfillment->method('getMerchant')->willReturn($merchant);
        $fulfillment->method('getOrder')->willReturn($order);
        $fulfillment->method('getCompletedAt')->willReturn(
            new \DateTimeImmutable('now', new \DateTimeZone('UTC'))
        );
        $fulfillment->method('getItems')->willReturn(
            new ArrayCollection([$fulfillmentItem])
        );

        $this->fulfillmentRepository->method('find')->willReturn($fulfillment);
        $this->settlementRepository->method('findOneBy')->willReturn(null);
        $this->businessNoGenerator->method('generateSettlementNo')->willReturn('ST20240115000001');

        // 规则引擎返回 3% 费率
        $this->platformRuleService->expects($this->once())
            ->method('getSettlementFeeRate')
            ->with('merchant-123', 'KICKSCREW')
            ->willReturn('3.00');

        // 验证结算单使用 3% 佣金费率
        $this->entityManager->expects($this->once())
            ->method('persist')
            ->with($this->callback(function (Settlement $settlement) {
                return $settlement->getCommissionRate() === '3.00';
            }));

        ($this->handler)($message);
    }

    /**
     * 测试无规则配置时使用默认 5% 费率.
     */
    public function testSettlementUsesDefaultRateWhenNoRulesConfigured(): void
    {
        $message = new CreateSettlementMessage('fulfillment-123');

        $lock = $this->createMock(LockInterface::class);
        $lock->method('acquire')->willReturn(true);
        $lock->expects($this->once())->method('release');

        $this->lockFactory->method('createLock')->willReturn($lock);

        $merchant = $this->createMock(Merchant::class);
        $merchant->method('getId')->willReturn('merchant-123');

        $salesChannel = $this->createMock(SalesChannel::class);
        $salesChannel->method('getCode')->willReturn('KICKSCREW');

        $order = $this->createMock(Order::class);
        $order->method('getCurrency')->willReturn('CNY');
        $order->method('getSalesChannel')->willReturn($salesChannel);

        $fulfillmentItem = $this->createMock(FulfillmentItem::class);
        $fulfillmentItem->method('getCommissionRate')->willReturn(null);

        $fulfillment = $this->createMock(Fulfillment::class);
        $fulfillment->method('getId')->willReturn('fulfillment-123');
        $fulfillment->method('isCompleted')->willReturn(true);
        $fulfillment->method('getMerchant')->willReturn($merchant);
        $fulfillment->method('getOrder')->willReturn($order);
        $fulfillment->method('getCompletedAt')->willReturn(
            new \DateTimeImmutable('now', new \DateTimeZone('UTC'))
        );
        $fulfillment->method('getItems')->willReturn(
            new ArrayCollection([$fulfillmentItem])
        );

        $this->fulfillmentRepository->method('find')->willReturn($fulfillment);
        $this->settlementRepository->method('findOneBy')->willReturn(null);
        $this->businessNoGenerator->method('generateSettlementNo')->willReturn('ST20240115000001');

        // getSettlementFeeRate 返回默认值 5%（无规则时）
        $this->platformRuleService->method('getSettlementFeeRate')
            ->willReturn('5.00');

        // 验证结算单使用默认 5% 佣金费率
        $this->entityManager->expects($this->once())
            ->method('persist')
            ->with($this->callback(function (Settlement $settlement) {
                return $settlement->getCommissionRate() === '5.00';
            }));

        ($this->handler)($message);
    }
}
