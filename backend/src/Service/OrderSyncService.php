<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\MerchantSalesChannel;
use App\Entity\Order;
use App\Entity\OrderItem;
use App\Entity\OrderSyncLog;
use App\Entity\OrderSyncState;
use App\Entity\SalesChannel;
use App\Message\PushOrderStatusMessage;
use App\Repository\ChannelProductRepository;
use App\Repository\OrderRepository;
use App\Repository\OrderSyncStateRepository;
use App\Service\ChannelGateway\ChannelGatewayContext;
use App\Service\ChannelGateway\ChannelGatewayInterface;
use App\Service\ChannelGateway\ChannelGatewayRegistry;
use App\Service\ChannelGateway\Dto\Request\ConfirmOrderRequest;
use App\Service\ChannelGateway\Dto\Request\PullOrdersRequest;
use App\Service\ChannelGateway\Dto\Request\ShipOrderRequest;
use App\Service\ChannelGateway\Dto\Response\PulledOrderDto;
use App\Service\ChannelGateway\Exception\ChannelGatewayException;
use App\Service\OrderSync\ChannelStatusMapper;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * 订单同步服务 - 处理订单拉取和推送逻辑.
 */
class OrderSyncService
{
    public function __construct(
        private readonly OrderRepository $orderRepo,
        private readonly OrderSyncStateRepository $syncStateRepo,
        private readonly ChannelProductRepository $channelProductRepo,
        private readonly ChannelGatewayRegistry $gatewayRegistry,
        private readonly ChannelStatusMapper $statusMapper,
        private readonly EntityManagerInterface $entityManager,
        private readonly MessageBusInterface $messageBus,
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * 从渠道拉取订单.
     *
     * @return array{created: int, updated: int, skipped: int, errors: int}
     */
    public function pullOrders(
        SalesChannel $salesChannel,
        ?MerchantSalesChannel $merchantChannel,
        \DateTimeImmutable $startTime,
        \DateTimeImmutable $endTime,
        int $page = 1,
        int $pageSize = 100,
    ): array {
        $stats = ['created' => 0, 'updated' => 0, 'skipped' => 0, 'errors' => 0];
        $channelCode = $salesChannel->getCode();

        if (!$this->gatewayRegistry->has($channelCode)) {
            $this->logger->warning('No gateway available for channel', [
                'channelCode' => $channelCode,
            ]);

            return $stats;
        }

        $gateway = $this->gatewayRegistry->get($channelCode);

        if (!$gateway->supports(ChannelGatewayInterface::OPERATION_PULL_ORDERS)) {
            $this->logger->info('Channel does not support pulling orders', [
                'channelCode' => $channelCode,
            ]);

            return $stats;
        }

        $context = new ChannelGatewayContext($salesChannel, $merchantChannel);

        try {
            $request = new PullOrdersRequest(
                startTime: $startTime,
                endTime: $endTime,
                page: $page,
                pageSize: $pageSize,
            );

            $pulledOrders = $gateway->pullOrders($context, $request);

            $this->logger->info('Pulled orders from channel', [
                'channelCode' => $channelCode,
                'count' => count($pulledOrders),
                'page' => $page,
                'startTime' => $startTime->format(\DateTimeInterface::ATOM),
                'endTime' => $endTime->format(\DateTimeInterface::ATOM),
            ]);

            foreach ($pulledOrders as $pulledOrder) {
                try {
                    $result = $this->processChannelOrder($salesChannel, $pulledOrder);
                    ++$stats[$result];
                } catch (\Throwable $e) {
                    ++$stats['errors'];
                    $this->logger->error('Failed to process pulled order', [
                        'externalOrderId' => $pulledOrder->externalOrderId,
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            return $stats;
        } catch (ChannelGatewayException $e) {
            $this->logger->error('Channel gateway error during order pull', [
                'channelCode' => $channelCode,
                'error' => $e->getMessage(),
                'errorCode' => $e->getErrorCode(),
            ]);
            throw $e;
        }
    }

    /**
     * 处理单个渠道订单.
     *
     * @return string 'created'|'updated'|'skipped'
     */
    public function processChannelOrder(
        SalesChannel $salesChannel,
        PulledOrderDto $pulledOrder,
    ): string {
        $syncLog = OrderSyncLog::createForPull(
            $salesChannel->getId(),
            $pulledOrder->externalOrderId,
        );
        $syncLog->markProcessing();
        $syncLog->setRequestData([
            'externalOrderId' => $pulledOrder->externalOrderId,
            'status' => $pulledOrder->status,
            'paymentStatus' => $pulledOrder->paymentStatus,
        ]);

        $this->entityManager->persist($syncLog);

        try {
            // 检查是否已存在（去重）
            $existingOrder = $this->orderRepo->findByExternalOrderId(
                $pulledOrder->externalOrderId,
                $salesChannel,
            );

            if ($existingOrder !== null) {
                $updated = $this->updateExistingOrder($existingOrder, $salesChannel, $pulledOrder);
                $syncLog->setOrderId($existingOrder->getId());
                $syncLog->markSuccess(['action' => $updated ? 'updated' : 'skipped']);
                $this->entityManager->flush();

                return $updated ? 'updated' : 'skipped';
            }

            // 创建新订单
            $order = $this->createOrderFromPulled($salesChannel, $pulledOrder);

            // 创建同步状态
            $syncState = new OrderSyncState($order);
            $syncState->markPulled();

            // 如果已支付，设置待确认操作
            if ($order->isPaid()) {
                $syncState->setPendingOperation(OrderSyncState::OP_CONFIRM);
            }

            $this->entityManager->persist($syncState);

            $syncLog->setOrderId($order->getId());
            $syncLog->markSuccess(['action' => 'created', 'orderId' => $order->getId()]);
            $this->entityManager->flush();

            // 已支付订单派发确认消息
            if ($order->isPaid()) {
                $this->messageBus->dispatch(PushOrderStatusMessage::confirm($order->getId()));
            }

            return 'created';
        } catch (\Throwable $e) {
            $syncLog->markFailed($e->getMessage());
            $this->entityManager->flush();
            throw $e;
        }
    }

    /**
     * 推送订单确认到渠道.
     */
    public function confirmOrder(Order $order): OrderSyncLog
    {
        $syncLog = OrderSyncLog::createForPush($order, OrderSyncLog::OP_CONFIRM_ORDER);
        $syncLog->markProcessing();
        $this->entityManager->persist($syncLog);

        $syncState = $this->syncStateRepo->getOrCreate($order);

        try {
            $salesChannel = $order->getSalesChannel();
            $channelCode = $salesChannel->getCode();

            if (!$this->gatewayRegistry->has($channelCode)) {
                $syncLog->markSkipped('No gateway for channel');
                $this->entityManager->flush();

                return $syncLog;
            }

            $gateway = $this->gatewayRegistry->get($channelCode);

            if (!$gateway->supports(ChannelGatewayInterface::OPERATION_CONFIRM_ORDER)) {
                $syncState->markConfirmed();
                $syncLog->markSkipped('Operation not supported');
                $this->entityManager->flush();

                return $syncLog;
            }

            $context = new ChannelGatewayContext($salesChannel);
            $request = new ConfirmOrderRequest(
                externalOrderId: $order->getExternalOrderId(),
                internalOrderId: $order->getId(),
            );

            $syncLog->setRequestData([
                'externalOrderId' => $order->getExternalOrderId(),
            ]);

            $response = $gateway->confirmOrder($context, $request);

            if ($response->success) {
                $syncState->markConfirmed();
                $syncLog->markSuccess(['externalId' => $response->externalId]);

                $this->logger->info('Order confirmation pushed successfully', [
                    'orderId' => $order->getId(),
                    'externalOrderId' => $order->getExternalOrderId(),
                ]);
            } else {
                $syncState->recordFailure($response->message ?? 'Unknown error');
                $syncLog->markFailed($response->message ?? 'Confirm failed');

                throw new ChannelGatewayException(
                    $response->message ?? 'Confirm order failed',
                    'CONFIRM_FAILED',
                );
            }

            $this->entityManager->flush();

            return $syncLog;
        } catch (ChannelGatewayException $e) {
            $syncState->recordFailure($e->getMessage(), $e->getErrorCode());
            $syncLog->markFailed($e->getMessage(), $e->getErrorCode());
            $this->entityManager->flush();
            throw $e;
        }
    }

    /**
     * 推送发货信息到渠道.
     */
    public function shipOrder(Order $order): OrderSyncLog
    {
        $syncLog = OrderSyncLog::createForPush($order, OrderSyncLog::OP_SHIP_ORDER);
        $syncLog->markProcessing();
        $this->entityManager->persist($syncLog);

        $syncState = $this->syncStateRepo->getOrCreate($order);

        try {
            $salesChannel = $order->getSalesChannel();
            $channelCode = $salesChannel->getCode();

            if (!$this->gatewayRegistry->has($channelCode)) {
                $syncLog->markSkipped('No gateway for channel');
                $this->entityManager->flush();

                return $syncLog;
            }

            $gateway = $this->gatewayRegistry->get($channelCode);

            if (!$gateway->supports(ChannelGatewayInterface::OPERATION_SHIP_ORDER)) {
                $syncState->markShipped();
                $syncLog->markSkipped('Operation not supported');
                $this->entityManager->flush();

                return $syncLog;
            }

            // 获取物流信息
            $fulfillment = $order->getFulfillments()->first();
            if ($fulfillment === false || $fulfillment->getTrackingNumber() === null) {
                $syncLog->markFailed('No shipping info available');
                $this->entityManager->flush();

                return $syncLog;
            }

            $context = new ChannelGatewayContext($salesChannel);
            $request = new ShipOrderRequest(
                externalOrderId: $order->getExternalOrderId(),
                trackingNumber: $fulfillment->getTrackingNumber(),
                shippingCarrier: $fulfillment->getShippingCarrier() ?? '',
                shippedAt: $fulfillment->getShippedAt(),
            );

            $syncLog->setRequestData([
                'externalOrderId' => $order->getExternalOrderId(),
                'trackingNumber' => $fulfillment->getTrackingNumber(),
                'shippingCarrier' => $fulfillment->getShippingCarrier(),
            ]);

            $response = $gateway->shipOrder($context, $request);

            if ($response->success) {
                $syncState->markShipped();
                $syncLog->markSuccess([
                    'trackingAccepted' => $response->trackingAccepted,
                ]);

                $this->logger->info('Order shipping info pushed successfully', [
                    'orderId' => $order->getId(),
                    'externalOrderId' => $order->getExternalOrderId(),
                    'trackingNumber' => $fulfillment->getTrackingNumber(),
                ]);
            } else {
                $syncState->recordFailure($response->message ?? 'Unknown error');
                $syncLog->markFailed($response->message ?? 'Ship failed');

                throw new ChannelGatewayException(
                    $response->message ?? 'Ship order failed',
                    'SHIP_FAILED',
                );
            }

            $this->entityManager->flush();

            return $syncLog;
        } catch (ChannelGatewayException $e) {
            $syncState->recordFailure($e->getMessage(), $e->getErrorCode());
            $syncLog->markFailed($e->getMessage(), $e->getErrorCode());
            $this->entityManager->flush();
            throw $e;
        }
    }

    /**
     * 查找需要重试的同步状态.
     *
     * @return OrderSyncState[]
     */
    public function findPendingOperations(
        \DateTimeImmutable $threshold,
        int $maxRetryCount = 5,
        int $limit = 100,
    ): array {
        return $this->syncStateRepo->findPendingOperations($threshold, $maxRetryCount, $limit);
    }

    /**
     * 从拉取的订单创建平台订单.
     */
    private function createOrderFromPulled(
        SalesChannel $salesChannel,
        PulledOrderDto $pulledOrder,
    ): Order {
        $channelCode = $salesChannel->getCode();

        $order = new Order();
        $order->setSalesChannel($salesChannel);
        $order->setExternalOrderId($pulledOrder->externalOrderId);
        $order->setExternalOrderNo($pulledOrder->externalOrderNo);

        // 状态映射
        $order->setStatus($this->statusMapper->mapOrderStatus($channelCode, $pulledOrder->status));
        $order->setPaymentStatus($this->statusMapper->mapPaymentStatus($channelCode, $pulledOrder->paymentStatus));

        // 收货人信息
        $order->setReceiverName($pulledOrder->receiver->name);
        $order->setReceiverPhone($pulledOrder->receiver->phone);
        $order->setReceiverProvince($pulledOrder->receiver->province);
        $order->setReceiverCity($pulledOrder->receiver->city);
        $order->setReceiverDistrict($pulledOrder->receiver->district);
        $order->setReceiverAddress($pulledOrder->receiver->address);
        $order->setReceiverPostalCode($pulledOrder->receiver->postalCode);

        // 金额信息
        $order->setTotalAmount($pulledOrder->totalAmount);
        $order->setProductAmount($pulledOrder->productAmount);
        $order->setShippingAmount($pulledOrder->shippingAmount);
        $order->setDiscountAmount($pulledOrder->discountAmount);
        $order->setCurrency($pulledOrder->currency);

        // 时间信息
        $order->setPlacedAt($pulledOrder->placedAt);
        if ($pulledOrder->paidAt !== null) {
            $order->setPaidAt($pulledOrder->paidAt);
        }

        // 备注
        $order->setBuyerRemark($pulledOrder->buyerRemark);

        // 原始数据
        $order->setExternalData($pulledOrder->rawData);

        $this->entityManager->persist($order);

        // 创建订单项
        foreach ($pulledOrder->items as $pulledItem) {
            $orderItem = new OrderItem();
            $orderItem->setOrder($order);
            $orderItem->setExternalProductId($pulledItem->externalProductId);
            $orderItem->setExternalProductName($pulledItem->productName);
            $orderItem->setExternalProductImage($pulledItem->productImage);
            $orderItem->setQuantity($pulledItem->quantity);
            $orderItem->setUnitPrice($pulledItem->unitPrice);
            $orderItem->setTotalPrice($pulledItem->totalPrice);
            $orderItem->setPayableAmount($pulledItem->totalPrice);
            $orderItem->setSizeValue($pulledItem->sizeValue);
            $orderItem->setSkuCode($pulledItem->skuCode);

            // 尝试匹配渠道商品
            $channelProduct = $this->matchChannelProduct(
                $salesChannel,
                $pulledItem->externalProductId,
            );
            if ($channelProduct !== null) {
                $orderItem->setChannelProduct($channelProduct);
                $orderItem->setProductSku($channelProduct->getProductSku());
            }

            $this->entityManager->persist($orderItem);
            $order->addItem($orderItem);
        }

        return $order;
    }

    /**
     * 更新已存在的订单.
     */
    private function updateExistingOrder(
        Order $order,
        SalesChannel $salesChannel,
        PulledOrderDto $pulledOrder,
    ): bool {
        $channelCode = $salesChannel->getCode();
        $changed = false;

        // 更新状态
        $newStatus = $this->statusMapper->mapOrderStatus($channelCode, $pulledOrder->status);
        if ($order->getStatus() !== $newStatus) {
            $order->setStatus($newStatus);
            $changed = true;
        }

        $newPaymentStatus = $this->statusMapper->mapPaymentStatus($channelCode, $pulledOrder->paymentStatus);
        if ($order->getPaymentStatus() !== $newPaymentStatus) {
            $order->setPaymentStatus($newPaymentStatus);
            if ($pulledOrder->paidAt !== null && $order->getPaidAt() === null) {
                $order->setPaidAt($pulledOrder->paidAt);
            }
            $changed = true;
        }

        // 更新原始数据
        $order->setExternalData($pulledOrder->rawData);
        $order->setSyncedAt(new \DateTimeImmutable('now', new \DateTimeZone('UTC')));

        return $changed;
    }

    /**
     * 匹配渠道商品.
     */
    private function matchChannelProduct(
        SalesChannel $salesChannel,
        string $externalProductId,
    ): ?\App\Entity\ChannelProduct {
        return $this->channelProductRepo->findByExternalId(
            $salesChannel,
            $externalProductId,
        );
    }
}
