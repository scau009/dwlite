<?php

namespace App\Service;

use Predis\Client as RedisClient;

/**
 * 业务编号生成器.
 *
 * 生成格式: {前缀}{日期}{序号}
 * 示例: WT2024121900001
 */
class BusinessNoGenerator
{
    // 业务编号前缀常量
    public const PREFIX_WALLET_TRANSACTION = 'WT';  // 钱包流水
    public const PREFIX_ORDER = 'BN';               // 订单
    public const PREFIX_OUTBOUND = 'OL';            // 发货单
    public const PREFIX_WITHDRAW = 'WD';            // 提现单
    public const PREFIX_REFUND = 'RF';              // 退款单
    public const PREFIX_SETTLEMENT = 'ST';          // 结算单
    public const PREFIX_INBOUND_ORDER = 'IB';       // 入库单
    public const PREFIX_PLATFORM_ORDER = 'PO';      // 平台订单
    public const PREFIX_OUTBOUND_ORDER = 'OB';      // 出库单
    public const PREFIX_FULFILLMENT = 'FF';         // 履约单
    public const PREFIX_INBOUND_EXCEPTION = 'EX';   // 入库异常单
    public const PREFIX_ORDER_EXCEPTION = 'OE';     // 订单异常单
    public const PREFIX_PRODUCT = 'P';              // 商品
    public const PREFIX_PRODUCT_SKU = 'S';          // SKU
    public const PREFIX_USER = 'U';                 // 用户
    public const PREFIX_CHANNEL_PRODUCT = 'CP';     // 渠道商品
    public const PREFIX_CHANNEL_PRODUCT_SOURCE = 'CS';  // 渠道商品来源
    public const PREFIX_INVENTORY_LISTING = 'IL';  // 上架配置
    public const PREFIX_INBOUND_SHIPMENT = 'IS';   // 入库发货

    private const KEY_PREFIX = 'biz_seq:';
    private const SEQ_PAD_LENGTH = 5;  // 序号位数，00001-99999

    public function __construct(
        private RedisClient $redis,
    ) {
    }

    /**
     * 生成业务编号.
     *
     * @param string $prefix 业务前缀 (使用常量)
     * @param string|null $date 日期，默认当天 (Ymd格式)
     *
     * @return string 生成的业务编号
     */
    public function generate(string $prefix, ?string $date = null): string
    {
        $date = $date ?? date('Ymd');
        $key = self::KEY_PREFIX.$prefix.':'.$date;

        // Redis INCR 原子操作
        $seq = $this->redis->incr($key);

        // 设置过期时间（2天后过期，确保跨天安全）
        if ($seq === 1) {
            $this->redis->expire($key, 86400 * 2);
        }

        return $prefix.$date.str_pad((string) $seq, self::SEQ_PAD_LENGTH, '0', STR_PAD_LEFT);
    }

    /**
     * 生成钱包流水编号.
     */
    public function generateWalletTransactionNo(): string
    {
        return $this->generate(self::PREFIX_WALLET_TRANSACTION);
    }

    /**
     * 生成订单编号.
     */
    public function generateOrderNo(): string
    {
        return $this->generate(self::PREFIX_ORDER);
    }

    /**
     * 生成发货单编号.
     */
    public function generateOutboundNo(): string
    {
        return $this->generate(self::PREFIX_OUTBOUND);
    }

    /**
     * 生成提现单编号.
     */
    public function generateWithdrawNo(): string
    {
        return $this->generate(self::PREFIX_WITHDRAW);
    }

    /**
     * 生成退款单编号.
     */
    public function generateRefundNo(): string
    {
        return $this->generate(self::PREFIX_REFUND);
    }

    /**
     * 生成结算单编号.
     */
    public function generateSettlementNo(): string
    {
        return $this->generate(self::PREFIX_SETTLEMENT);
    }

    /**
     * 生成入库单编号.
     */
    public function generateInboundOrderNo(): string
    {
        return $this->generate(self::PREFIX_INBOUND_ORDER);
    }

    /**
     * 生成平台订单编号.
     */
    public function generatePlatformOrderNo(): string
    {
        return $this->generate(self::PREFIX_PLATFORM_ORDER);
    }

    /**
     * 生成出库单编号.
     */
    public function generateOutboundOrderNo(): string
    {
        return $this->generate(self::PREFIX_OUTBOUND_ORDER);
    }

    /**
     * 生成履约单编号.
     */
    public function generateFulfillmentNo(): string
    {
        return $this->generate(self::PREFIX_FULFILLMENT);
    }

    /**
     * 生成入库异常单编号.
     */
    public function generateInboundExceptionNo(): string
    {
        return $this->generate(self::PREFIX_INBOUND_EXCEPTION);
    }

    /**
     * 生成订单异常单编号.
     */
    public function generateOrderExceptionNo(): string
    {
        return $this->generate(self::PREFIX_ORDER_EXCEPTION);
    }

    /**
     * 生成商品ID.
     *
     * 格式: P{8位序号}，如 P00000001
     */
    public function generateProductId(): string
    {
        return $this->generateWithoutDate(self::PREFIX_PRODUCT);
    }

    /**
     * 生成SKU ID.
     *
     * 格式: S{8位序号}，如 S00000001
     */
    public function generateProductSkuId(): string
    {
        return $this->generateWithoutDate(self::PREFIX_PRODUCT_SKU);
    }

    /**
     * 生成用户ID.
     *
     * 格式: U{8位序号}，如 U00000001
     */
    public function generateUserId(): string
    {
        return $this->generateWithoutDate(self::PREFIX_USER);
    }

    /**
     * 生成渠道商品ID.
     *
     * 格式: CP{8位序号}，如 CP00000001
     */
    public function generateChannelProductId(): string
    {
        return $this->generateWithoutDate(self::PREFIX_CHANNEL_PRODUCT);
    }

    /**
     * 生成渠道商品来源ID.
     *
     * 格式: CS{8位序号}，如 CS00000001
     */
    public function generateChannelProductSourceId(): string
    {
        return $this->generateWithoutDate(self::PREFIX_CHANNEL_PRODUCT_SOURCE);
    }

    /**
     * 生成上架配置ID.
     *
     * 格式: IL{8位序号}，如 IL00000001
     */
    public function generateInventoryListingId(): string
    {
        return $this->generateWithoutDate(self::PREFIX_INVENTORY_LISTING);
    }

    /**
     * 生成入库发货ID.
     *
     * 格式: IS{8位序号}，如 IS00000001
     */
    public function generateInboundShipmentId(): string
    {
        return $this->generateWithoutDate(self::PREFIX_INBOUND_SHIPMENT);
    }

    /**
     * 生成不含日期的业务编号.
     *
     * @param string $prefix 业务前缀
     *
     * @return string 生成的业务编号
     */
    private function generateWithoutDate(string $prefix): string
    {
        $key = 'business_no:'.$prefix;
        $sequence = $this->redis->incr($key);

        return $prefix.str_pad((string) $sequence, 8, '0', STR_PAD_LEFT);
    }
}
