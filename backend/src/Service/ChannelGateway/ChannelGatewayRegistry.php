<?php

declare(strict_types=1);

namespace App\Service\ChannelGateway;

use Psr\Container\ContainerInterface;
use Symfony\Component\DependencyInjection\ServiceLocator;

/**
 * Registry for channel gateway providers.
 *
 * Uses Symfony's tagged service locator pattern to provide
 * access to different gateways by channel code.
 */
class ChannelGatewayRegistry
{
    public function __construct(
        private ContainerInterface $gateways,
    ) {
    }

    /**
     * Get a gateway by channel code.
     *
     * @throws \InvalidArgumentException if gateway not found
     */
    public function get(string $channelCode): ChannelGatewayInterface
    {
        $normalizedCode = strtoupper($channelCode);

        if (!$this->gateways->has($normalizedCode)) {
            throw new \InvalidArgumentException(sprintf('Unknown channel gateway: "%s". Available gateways: %s', $channelCode, implode(', ', $this->getAvailableGateways())));
        }

        $gateway = $this->gateways->get($normalizedCode);

        if (!$gateway instanceof ChannelGatewayInterface) {
            throw new \LogicException(sprintf('Gateway for channel "%s" must implement %s', $channelCode, ChannelGatewayInterface::class));
        }

        return $gateway;
    }

    /**
     * Check if a gateway exists.
     */
    public function has(string $channelCode): bool
    {
        return $this->gateways->has(strtoupper($channelCode));
    }

    /**
     * Get all available gateway codes.
     *
     * @return string[]
     */
    public function getAvailableGateways(): array
    {
        if ($this->gateways instanceof ServiceLocator) {
            return array_keys($this->gateways->getProvidedServices());
        }

        return [];
    }
}
