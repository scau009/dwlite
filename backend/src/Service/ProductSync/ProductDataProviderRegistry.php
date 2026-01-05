<?php

namespace App\Service\ProductSync;

use Psr\Container\ContainerInterface;

/**
 * Registry for product data providers.
 *
 * Uses Symfony's tagged service locator pattern to provide
 * access to different providers by name.
 */
class ProductDataProviderRegistry
{
    public function __construct(
        private ContainerInterface $providers,
    ) {
    }

    /**
     * Get a provider by name.
     *
     * @throws \InvalidArgumentException if provider not found
     */
    public function get(string $providerName): ProductDataProviderInterface
    {
        if (!$this->providers->has($providerName)) {
            throw new \InvalidArgumentException(sprintf('Unknown product data provider: "%s". Available providers: %s', $providerName, implode(', ', $this->getAvailableProviders())));
        }

        return $this->providers->get($providerName);
    }

    /**
     * Check if a provider exists.
     */
    public function has(string $providerName): bool
    {
        return $this->providers->has($providerName);
    }

    /**
     * Get all available provider names.
     *
     * @return string[]
     */
    public function getAvailableProviders(): array
    {
        // The tagged locator provides an iterable of provider names
        $providers = [];

        // Use reflection to get the keys from the locator
        if ($this->providers instanceof \Symfony\Component\DependencyInjection\ServiceLocator) {
            $providers = array_keys($this->providers->getProvidedServices());
        }

        return $providers;
    }
}
