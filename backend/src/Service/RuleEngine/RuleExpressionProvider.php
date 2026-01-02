<?php

namespace App\Service\RuleEngine;

use Symfony\Component\ExpressionLanguage\ExpressionFunction;
use Symfony\Component\ExpressionLanguage\ExpressionFunctionProviderInterface;

/**
 * 规则引擎自定义函数提供者.
 *
 * 提供规则表达式中可使用的自定义函数
 */
class RuleExpressionProvider implements ExpressionFunctionProviderInterface
{
    public function getFunctions(): array
    {
        return [
            // 价格计算函数
            $this->createMarkupFunction(),
            $this->createDiscountFunction(),
            $this->createAddFeeFunction(),

            // 库存计算函数
            $this->createRatioFunction(),
            $this->createLimitFunction(),

            // 阶梯费率函数
            $this->createTieredRateFunction(),

            // 数学函数
            $this->createRoundFunction(),
            $this->createFloorFunction(),
            $this->createCeilFunction(),
            $this->createMinFunction(),
            $this->createMaxFunction(),
            $this->createAbsFunction(),

            // 条件函数
            $this->createInListFunction(),
            $this->createStartsWithFunction(),
            $this->createEndsWithFunction(),
            $this->createContainsFunction(),

            // 配置函数
            $this->createConfigFunction(),
        ];
    }

    /**
     * 加价函数: markup(price, rate)
     * 例: markup(100, 0.15) = 115.
     */
    private function createMarkupFunction(): ExpressionFunction
    {
        return new ExpressionFunction(
            'markup',
            fn ($price, $rate) => sprintf('(%s) * (1 + %s)', $price, $rate),
            fn (array $values, float $price, float $rate): float => $price * (1 + $rate)
        );
    }

    /**
     * 折扣函数: discount(price, rate)
     * 例: discount(100, 0.1) = 90.
     */
    private function createDiscountFunction(): ExpressionFunction
    {
        return new ExpressionFunction(
            'discount',
            fn ($price, $rate) => sprintf('(%s) * (1 - %s)', $price, $rate),
            fn (array $values, float $price, float $rate): float => $price * (1 - $rate)
        );
    }

    /**
     * 加费函数: addFee(price, rate, fixed = 0)
     * 例: addFee(100, 0.05, 2) = 107.
     */
    private function createAddFeeFunction(): ExpressionFunction
    {
        return new ExpressionFunction(
            'addFee',
            fn ($price, $rate, $fixed = '0') => sprintf('(%s) * (1 + %s) + %s', $price, $rate, $fixed),
            fn (array $values, float $price, float $rate, float $fixed = 0): float => ($price * (1 + $rate)) + $fixed
        );
    }

    /**
     * 比例函数: ratio(value, rate)
     * 例: ratio(100, 0.8) = 80.
     */
    private function createRatioFunction(): ExpressionFunction
    {
        return new ExpressionFunction(
            'ratio',
            fn ($value, $rate) => sprintf('floor((%s) * %s)', $value, $rate),
            fn (array $values, int|float $value, float $rate): int => (int) floor($value * $rate)
        );
    }

    /**
     * 上限函数: limit(value, max)
     * 例: limit(150, 100) = 100.
     */
    private function createLimitFunction(): ExpressionFunction
    {
        return new ExpressionFunction(
            'limit',
            fn ($value, $max) => sprintf('min(%s, %s)', $value, $max),
            fn (array $values, int|float $value, int|float $max): int|float => min($value, $max)
        );
    }

    /**
     * 阶梯费率函数: tieredRate(value, tiers)
     * tiers 格式: [[threshold, rate], [threshold2, rate2], ...]
     * 例: tieredRate(8000, [[10000, 0.03], [5000, 0.04], [0, 0.05]]) = 0.04.
     */
    private function createTieredRateFunction(): ExpressionFunction
    {
        return new ExpressionFunction(
            'tieredRate',
            fn ($value, $tiers) => 'tieredRate_compiled()',
            function (array $values, float $value, array $tiers): float {
                // 按阈值降序排序
                usort($tiers, fn ($a, $b) => $b[0] <=> $a[0]);

                foreach ($tiers as $tier) {
                    if ($value >= $tier[0]) {
                        return (float) $tier[1];
                    }
                }

                // 返回最低阈值的费率
                return (float) ($tiers[count($tiers) - 1][1] ?? 0);
            }
        );
    }

    /**
     * 四舍五入函数: round(value, precision = 2).
     */
    private function createRoundFunction(): ExpressionFunction
    {
        return new ExpressionFunction(
            'round',
            fn ($value, $precision = '2') => sprintf('round(%s, %s)', $value, $precision),
            fn (array $values, float $value, int $precision = 2): float => round($value, $precision)
        );
    }

    /**
     * 向下取整函数: floor(value).
     */
    private function createFloorFunction(): ExpressionFunction
    {
        return new ExpressionFunction(
            'floor',
            fn ($value) => sprintf('floor(%s)', $value),
            fn (array $values, float $value): int => (int) floor($value)
        );
    }

    /**
     * 向上取整函数: ceil(value).
     */
    private function createCeilFunction(): ExpressionFunction
    {
        return new ExpressionFunction(
            'ceil',
            fn ($value) => sprintf('ceil(%s)', $value),
            fn (array $values, float $value): int => (int) ceil($value)
        );
    }

    /**
     * 最小值函数: min(a, b).
     */
    private function createMinFunction(): ExpressionFunction
    {
        return new ExpressionFunction(
            'min',
            fn ($a, $b) => sprintf('min(%s, %s)', $a, $b),
            fn (array $values, int|float $a, int|float $b): int|float => min($a, $b)
        );
    }

    /**
     * 最大值函数: max(a, b).
     */
    private function createMaxFunction(): ExpressionFunction
    {
        return new ExpressionFunction(
            'max',
            fn ($a, $b) => sprintf('max(%s, %s)', $a, $b),
            fn (array $values, int|float $a, int|float $b): int|float => max($a, $b)
        );
    }

    /**
     * 绝对值函数: abs(value).
     */
    private function createAbsFunction(): ExpressionFunction
    {
        return new ExpressionFunction(
            'abs',
            fn ($value) => sprintf('abs(%s)', $value),
            fn (array $values, int|float $value): int|float => abs($value)
        );
    }

    /**
     * 列表包含函数: inList(value, list)
     * 例: inList('nike', ['nike', 'adidas']) = true.
     */
    private function createInListFunction(): ExpressionFunction
    {
        return new ExpressionFunction(
            'inList',
            fn ($value, $list) => sprintf('in_array(%s, %s, true)', $value, $list),
            fn (array $values, mixed $value, array $list): bool => in_array($value, $list, true)
        );
    }

    /**
     * 字符串开头判断: startsWith(str, prefix)
     * 例: startsWith('nike-air', 'nike') = true.
     */
    private function createStartsWithFunction(): ExpressionFunction
    {
        return new ExpressionFunction(
            'startsWith',
            fn ($str, $prefix) => sprintf('str_starts_with(%s, %s)', $str, $prefix),
            fn (array $values, ?string $str, string $prefix): bool => str_starts_with($str ?? '', $prefix)
        );
    }

    /**
     * 字符串结尾判断: endsWith(str, suffix)
     * 例: endsWith('nike-air', 'air') = true.
     */
    private function createEndsWithFunction(): ExpressionFunction
    {
        return new ExpressionFunction(
            'endsWith',
            fn ($str, $suffix) => sprintf('str_ends_with(%s, %s)', $str, $suffix),
            fn (array $values, ?string $str, string $suffix): bool => str_ends_with($str ?? '', $suffix)
        );
    }

    /**
     * 字符串包含判断: contains(str, needle)
     * 例: contains('nike-air-max', 'air') = true.
     */
    private function createContainsFunction(): ExpressionFunction
    {
        return new ExpressionFunction(
            'contains',
            fn ($str, $needle) => sprintf('str_contains(%s, %s)', $str, $needle),
            fn (array $values, ?string $str, string $needle): bool => str_contains($str ?? '', $needle)
        );
    }

    /**
     * 配置值获取函数: config(key, default = null)
     * 从上下文中的 config 数组获取值
     * 例: config('rate', 0.1).
     */
    private function createConfigFunction(): ExpressionFunction
    {
        return new ExpressionFunction(
            'config',
            fn ($key, $default = 'null') => sprintf('(config[%s] ?? %s)', $key, $default),
            fn (array $values, string $key, mixed $default = null): mixed => $values['config'][$key] ?? $default
        );
    }
}
