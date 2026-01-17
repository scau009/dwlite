<?php

declare(strict_types=1);

namespace App\Service\Fulfillment;

use App\Entity\Fulfillment;
use App\Entity\FulfillmentItem;
use App\Entity\OutboundOrder;
use App\Entity\OutboundOrderItem;
use App\Service\BusinessNoGenerator;
use App\Service\OpenApi\WebhookService;
use Psr\Log\LoggerInterface;

/**
 * 出库单创建服务 - 从履约单创建出库单.
 */
class OutboundOrderCreationService
{
    public function __construct(
        private readonly BusinessNoGenerator $businessNoGenerator,
        private readonly WebhookService $webhookService,
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * 从履约单创建出库单.
     *
     * 一个履约单对应一个出库单（履约单已按商户分组）。
     *
     * @throws \InvalidArgumentException
     */
    public function createFromFulfillment(Fulfillment $fulfillment): OutboundOrder
    {
        // 验证履约单类型
        if (!$fulfillment->isPlatformWarehouse()) {
            throw new \InvalidArgumentException('只有寄售履约单可以创建出库单');
        }

        // 验证履约单有明细
        if ($fulfillment->getItems()->isEmpty()) {
            throw new \InvalidArgumentException('履约单没有明细项');
        }

        // 1. 创建出库单
        $outbound = OutboundOrder::createFromFulfillment($fulfillment);
        $outbound->setOutboundNo($this->businessNoGenerator->generateOutboundOrderNo());

        // 2. 从 FulfillmentItem 获取商户（寄售履约单的 merchant 字段为 null）
        /** @var FulfillmentItem $firstItem */
        $firstItem = $fulfillment->getItems()->first();
        if ($firstItem->getMerchant() !== null) {
            $outbound->setMerchant($firstItem->getMerchant());
        } else {
            throw new \InvalidArgumentException('履约单明细缺少商户信息');
        }

        // 3. 创建出库单明细
        foreach ($fulfillment->getItems() as $fulfillmentItem) {
            $outboundItem = new OutboundOrderItem();
            $outboundItem->setQuantity($fulfillmentItem->getQuantity());
            $outboundItem->setWarehouse($fulfillment->getWarehouse());
            $outboundItem->setMerchant($fulfillmentItem->getMerchant());

            // 从 OrderItem 获取 SKU 信息
            $orderItem = $fulfillmentItem->getOrderItem();
            $sku = $orderItem->getProductSku();
            if ($sku !== null) {
                $outboundItem->snapshotFromSku($sku);
                $outboundItem->setProductSku($sku);
            }

            $outbound->addItem($outboundItem);
        }

        // 4. 提交出库单 (draft → pending)
        $outbound->submit();

        $this->logger->info('OutboundOrder created from fulfillment', [
            'outboundOrderId' => $outbound->getId(),
            'outboundNo' => $outbound->getOutboundNo(),
            'fulfillmentId' => $fulfillment->getId(),
            'fulfillmentNo' => $fulfillment->getFulfillmentNo(),
            'itemCount' => $outbound->getItems()->count(),
            'totalQuantity' => $outbound->getTotalQuantity(),
        ]);

        // Trigger webhook for warehouse
        $this->webhookService->triggerWarehouseEvent(
            \App\Entity\Webhook::EVENT_OUTBOUND_ORDER_CREATED,
            $fulfillment->getWarehouse(),
            [
                'outbound_no' => $outbound->getOutboundNo(),
                'fulfillment_no' => $fulfillment->getFulfillmentNo(),
                'order_external_id' => $fulfillment->getOrder()->getExternalOrderId(),
                'merchant_id' => $outbound->getMerchant()->getId(),
                'merchant_name' => $outbound->getMerchant()->getName(),
                'warehouse_id' => $fulfillment->getWarehouse()->getId(),
                'warehouse_code' => $fulfillment->getWarehouse()->getCode(),
                'total_quantity' => $outbound->getTotalQuantity(),
                'items_count' => $outbound->getItems()->count(),
                'status' => $outbound->getStatus(),
            ]
        );

        return $outbound;
    }
}
