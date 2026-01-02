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
}
