<?php

declare(strict_types=1);

namespace Token27\NexusAI\Driver;

use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Token27\NexusAI\Contract\DriverInterface;

/**
 * Registry that maps provider names to driver factories and configurations.
 *
 * Allows the NexusAI facade to resolve drivers without a PSR-11 container.
 * Provider factories receive the configuration and PSR-18/PSR-17 dependencies,
 * and return a configured DriverInterface instance.
 *
 * Instances are cached after first resolution for performance.
 *
 * @see \Token27\NexusAI\NexusAI
 * @see \Token27\NexusAI\PendingRequest
 */
final class DriverRegistry
{
    /**
     * Map of provider name → factory callable.
     *
     * @var array<string, callable(array<string, mixed>, ClientInterface, RequestFactoryInterface, StreamFactoryInterface): DriverInterface>
     */
    private array $factories = [];

    /**
     * Configuration per provider.
     *
     * @var array<string, array<string, mixed>>
     */
    private array $configs = [];

    /**
     * Cached driver instances (resolved lazily).
     *
     * @var array<string, DriverInterface>
     */
    private array $instances = [];

    /**
     * PSR-18 HTTP client for driver construction.
     */
    private ?ClientInterface $httpClient = null;

    /**
     * PSR-17 request factory for driver construction.
     */
    private ?RequestFactoryInterface $requestFactory = null;

    /**
     * PSR-17 stream factory for driver construction.
     */
    private ?StreamFactoryInterface $streamFactory = null;

    /**
     * Registers a driver factory for a provider.
     *
     * The factory callable receives (config, httpClient, requestFactory, streamFactory)
     * and must return a DriverInterface instance.
     *
     * @param string $name Provider key (e.g., 'openai', 'anthropic').
     * @param callable(array<string, mixed>, ClientInterface, RequestFactoryInterface, StreamFactoryInterface): DriverInterface $factory Factory callable.
     */
    public function register(string $name, callable $factory): void
    {
        $this->factories[$name] = $factory;
        // Invalidate cached instance if re-registering
        unset($this->instances[$name]);
    }

    /**
     * Sets the configuration for a provider.
     *
     * @param string $name Provider key.
     * @param array<string, mixed> $config Provider configuration (api_key, base_url, etc.).
     */
    public function configure(string $name, array $config): void
    {
        $this->configs[$name] = $config;
        // Invalidate cached instance when config changes
        unset($this->instances[$name]);
    }

    /**
     * Sets the PSR-18/PSR-17 dependencies used by all driver factories.
     *
     * @param ClientInterface $httpClient PSR-18 HTTP client.
     * @param RequestFactoryInterface $requestFactory PSR-17 request factory.
     * @param StreamFactoryInterface $streamFactory PSR-17 stream factory.
     */
    public function setHttpDependencies(
        ClientInterface $httpClient,
        RequestFactoryInterface $requestFactory,
        StreamFactoryInterface $streamFactory,
    ): void {
        $this->httpClient = $httpClient;
        $this->requestFactory = $requestFactory;
        $this->streamFactory = $streamFactory;
        // Invalidate all cached instances when HTTP deps change
        $this->instances = [];
    }

    /**
     * Resolves a driver instance for the given provider.
     *
     * Caches the instance after first resolution. Subsequent calls
     * return the same instance unless config or factory is changed.
     *
     * @param string $name Provider key (e.g., 'openai').
     * @return DriverInterface The resolved driver instance.
     *
     * @throws \InvalidArgumentException If the provider is not registered.
     * @throws \RuntimeException If HTTP dependencies are not configured.
     */
    public function resolve(string $name): DriverInterface
    {
        // Return cached instance if available
        if (isset($this->instances[$name])) {
            return $this->instances[$name];
        }

        if (!isset($this->factories[$name])) {
            throw new \InvalidArgumentException(
                "No driver factory registered for provider '{$name}'. "
                . 'Available providers: ' . implode(', ', array_keys($this->factories)),
            );
        }

        if ($this->httpClient === null || $this->requestFactory === null || $this->streamFactory === null) {
            throw new \RuntimeException(
                'HTTP dependencies not configured. Call setHttpDependencies() or NexusAI::setHttpClient() first.',
            );
        }

        $config = $this->configs[$name] ?? [];
        $driver = ($this->factories[$name])($config, $this->httpClient, $this->requestFactory, $this->streamFactory);

        // Cache the resolved instance
        $this->instances[$name] = $driver;

        return $driver;
    }

    /**
     * Checks whether a factory is registered for the given provider.
     *
     * @param string $name Provider key.
     * @return bool True if the provider has a registered factory.
     */
    public function has(string $name): bool
    {
        return isset($this->factories[$name]);
    }

    /**
     * Returns all registered provider names.
     *
     * @return array<string> Provider keys.
     */
    public function getRegisteredProviders(): array
    {
        return array_keys($this->factories);
    }
}
