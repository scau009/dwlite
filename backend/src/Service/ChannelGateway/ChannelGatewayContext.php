<?php

declare(strict_types=1);

namespace App\Service\ChannelGateway;

use App\Entity\MerchantSalesChannel;
use App\Entity\SalesChannel;

/**
 * Context object containing channel configuration and credentials.
 *
 * Combines global channel config (SalesChannel) with merchant-specific
 * config (MerchantSalesChannel) for API operations.
 */
readonly class ChannelGatewayContext
{
    public function __construct(
        private SalesChannel          $salesChannel,
        private ?MerchantSalesChannel $merchantChannel = null,
    )
    {
    }

    public function getChannelCode(): string
    {
        return $this->salesChannel->getCode();
    }

    public function getChannelName(): string
    {
        return $this->salesChannel->getName();
    }

    public function getCurrency(): string
    {
        return $this->salesChannel->getCurrency();
    }

    /**
     * Get config value, checking merchant config first, then global config.
     */
    public function getConfigValue(string $key, mixed $default = null): mixed
    {
        // Merchant-specific config takes precedence
        if ($this->merchantChannel !== null) {
            $merchantConfig = $this->merchantChannel->getConfig();
            if ($merchantConfig !== null && isset($merchantConfig[$key])) {
                $value = $merchantConfig[$key];
                // Handle config items with isSecret metadata
                if (is_array($value) && isset($value['value'])) {
                    return $value['value'];
                }

                return $value;
            }
        }

        // Fall back to global channel config
        $globalConfig = $this->salesChannel->getConfig();
        if ($globalConfig !== null && isset($globalConfig[$key])) {
            $value = $globalConfig[$key];
            // Handle config items with isSecret metadata
            if (is_array($value) && isset($value['value'])) {
                return $value['value'];
            }

            return $value;
        }

        return $default;
    }

    /**
     * Get all merged configuration.
     */
    public function getMergedConfig(): array
    {
        $globalConfig = $this->salesChannel->getConfig() ?? [];
        $merchantConfig = $this->merchantChannel?->getConfig() ?? [];

        // Flatten config values (handle isSecret metadata)
        $flattenConfig = function (array $config): array {
            $result = [];
            foreach ($config as $key => $value) {
                if (is_array($value) && isset($value['value'])) {
                    $result[$key] = $value['value'];
                } else {
                    $result[$key] = $value;
                }
            }

            return $result;
        };

        return array_merge($flattenConfig($globalConfig), $flattenConfig($merchantConfig));
    }

    public function getSalesChannel(): SalesChannel
    {
        return $this->salesChannel;
    }

    public function getMerchantChannel(): ?MerchantSalesChannel
    {
        return $this->merchantChannel;
    }

    public function hasMerchantChannel(): bool
    {
        return $this->merchantChannel !== null;
    }
}
