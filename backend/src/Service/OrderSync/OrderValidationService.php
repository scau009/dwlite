<?php

declare(strict_types=1);

namespace App\Service\OrderSync;

use App\Entity\Order;
use App\Entity\OrderException;
use App\Repository\OrderExceptionRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;

/**
 * 订单校验服务 - 基于 ChannelProduct（平台商品）进行校验.
 */
class OrderValidationService
{
    public function __construct(
        private readonly OrderExceptionRepository $exceptionRepo,
        private readonly EntityManagerInterface $entityManager,
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * 校验订单并创建异常（如有）.
     *
     * @return OrderException[] 返回创建的异常列表
     */
    public function validateOrder(Order $order): array
    {
        $exceptions = [];

        foreach ($order->getItems() as $item) {
            // 1. 商品匹配校验
            $channelProduct = $item->getChannelProduct();
            if ($channelProduct === null) {
                $exception = OrderException::createForOrder(
                    $order,
                    OrderException::TYPE_PRODUCT_NOT_MATCHED,
                    sprintf('订单商品未匹配平台商品: %s', $item->getExternalProductId()),
                    [
                        'externalProductId' => $item->getExternalProductId(),
                        'productName' => $item->getExternalProductName(),
                    ]
                );
                $exceptions[] = $exception;
                $this->entityManager->persist($exception);

                $this->logger->warning('Order item not matched to channel product', [
                    'orderId' => $order->getId(),
                    'externalProductId' => $item->getExternalProductId(),
                ]);
                continue;
            }

            // 2. 库存校验 - 检查 ChannelProduct.stockQuantity
            $sku = $channelProduct->getProductSku();
            $skuName = $sku->getSkuName();

            if ($channelProduct->getStockQuantity() < $item->getQuantity()) {
                $exception = OrderException::createForOrder(
                    $order,
                    OrderException::TYPE_INVENTORY_INSUFFICIENT,
                    sprintf(
                        '平台库存不足: %s, 需要 %d, 可用 %d',
                        $skuName,
                        $item->getQuantity(),
                        $channelProduct->getStockQuantity()
                    ),
                    [
                        'channelProductId' => $channelProduct->getId(),
                        'skuId' => $sku->getId(),
                        'skuName' => $skuName,
                        'required' => $item->getQuantity(),
                        'available' => $channelProduct->getStockQuantity(),
                    ]
                );
                $exceptions[] = $exception;
                $this->entityManager->persist($exception);

                $this->logger->warning('Insufficient platform stock for order item', [
                    'orderId' => $order->getId(),
                    'channelProductId' => $channelProduct->getId(),
                    'required' => $item->getQuantity(),
                    'available' => $channelProduct->getStockQuantity(),
                ]);
            }

            // 3. 价格校验 - 检查订单单价 vs ChannelProduct.platformPrice
            $platformPrice = $channelProduct->getPlatformPrice();
            $orderUnitPrice = $item->getUnitPrice();

            if (bccomp($orderUnitPrice, $platformPrice, 2) < 0) {
                $exception = OrderException::createForOrder(
                    $order,
                    OrderException::TYPE_PRICE_BELOW_PLATFORM,
                    sprintf(
                        '订单单价 %s 低于平台价 %s: %s',
                        $orderUnitPrice,
                        $platformPrice,
                        $skuName
                    ),
                    [
                        'channelProductId' => $channelProduct->getId(),
                        'skuId' => $sku->getId(),
                        'skuName' => $skuName,
                        'orderUnitPrice' => $orderUnitPrice,
                        'platformPrice' => $platformPrice,
                        'difference' => bcsub($platformPrice, $orderUnitPrice, 2),
                    ]
                );
                $exceptions[] = $exception;
                $this->entityManager->persist($exception);

                $this->logger->warning('Order item price below platform price', [
                    'orderId' => $order->getId(),
                    'channelProductId' => $channelProduct->getId(),
                    'orderUnitPrice' => $orderUnitPrice,
                    'platformPrice' => $platformPrice,
                ]);
            }
        }

        if (!empty($exceptions)) {
            $this->logger->info('Order validation completed with exceptions', [
                'orderId' => $order->getId(),
                'exceptionCount' => count($exceptions),
            ]);
        }

        return $exceptions;
    }

    /**
     * 检查订单是否可以确认（无未处理异常）.
     */
    public function canConfirm(Order $order): bool
    {
        return $this->exceptionRepo->countPendingByOrder($order) === 0;
    }

    /**
     * 获取订单的待处理异常.
     *
     * @return OrderException[]
     */
    public function getPendingExceptions(Order $order): array
    {
        return $this->exceptionRepo->findPendingByOrder($order);
    }

    /**
     * 统计订单的待处理异常数量.
     */
    public function countPendingExceptions(Order $order): int
    {
        return $this->exceptionRepo->countPendingByOrder($order);
    }
}
