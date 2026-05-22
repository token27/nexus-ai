<?php

declare(strict_types=1);

namespace Token27\NexusAI;

use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Token27\NexusAI\Contract\DriverInterface;
use Token27\NexusAI\Contract\MiddlewareInterface;
use Token27\NexusAI\Driver\Anthropic\AnthropicDriver;
use Token27\NexusAI\Driver\DeepSeek\DeepSeekDriver;
use Token27\NexusAI\Driver\DriverRegistry;
use Token27\NexusAI\Driver\Gemini\GeminiDriver;
use Token27\NexusAI\Driver\Ollama\OllamaDriver;
use Token27\NexusAI\Driver\OpenAI\OpenAIDriver;
use Token27\NexusAI\Enum\Provider;
use Token27\NexusAI\Pipeline\Middleware\CostTrackingMiddleware;
use Token27\NexusAI\Pipeline\Pipeline;
use Token27\NexusAI\Pricing\Contract\PricingEngineInterface;

/**
 * Static facade and entry point for the NexusAI library.
 *
 * Provides a clean, expressive API for interacting with AI providers:
 *
 *   NexusAI::using('openai', 'gpt-4o')
 *       ->withPrompt('Hello')
 *       ->asText();
 *
 * Manages the singleton DriverRegistry, global Pipeline, and PSR-18/PSR-17
 * dependencies. All state is static for convenient access without DI.
 *
 * For applications using DI containers, use PendingRequest and DriverRegistry
 * directly instead of this facade.
 *
 * @see \Token27\NexusAI\PendingRequest
 * @see \Token27\NexusAI\Driver\DriverRegistry
 */
final class NexusAI
{
    /** @var DriverRegistry|null Singleton driver registry. */
    private static ?DriverRegistry $registry = null;

    /** @var Pipeline|null Default pipeline with global middlewares. */
    private static ?Pipeline $pipeline = null;

    /** @var ClientInterface|null PSR-18 HTTP client. */
    private static ?ClientInterface $httpClient = null;

    /** @var RequestFactoryInterface|null PSR-17 request factory. */
    private static ?RequestFactoryInterface $requestFactory = null;

    /** @var StreamFactoryInterface|null PSR-17 stream factory. */
    private static ?StreamFactoryInterface $streamFactory = null;

    /**
     * Prevent instantiation — this is a static facade.
     */
    private function __construct()
    {
    }

    /**
     * Creates a PendingRequest for the given provider and model.
     *
     * This is the main entry point for building and executing AI requests.
     *
     * @param string $provider Provider key (e.g., 'openai', 'anthropic').
     * @param string $model Model identifier (e.g., 'gpt-4o', 'claude-sonnet-4-20250514').
     * @return PendingRequest Fluent builder for configuring and executing the request.
     */
    public static function using(string $provider, string $model): PendingRequest
    {
        return new PendingRequest(
            registry: self::getRegistry(),
            pipeline: self::getPipeline(),
            provider: $provider,
            model: $model,
        );
    }

    /**
     * Configures providers with their API keys and settings.
     *
     * Example:
     *   NexusAI::configure([
     *       'openai' => ['api_key' => 'sk-...', 'base_url' => 'https://api.openai.com/v1'],
     *       'anthropic' => ['api_key' => 'sk-ant-...'],
     *   ]);
     *
     * @param array<string, array<string, mixed>> $config Provider configurations keyed by provider name.
     */
    public static function configure(array $config): void
    {
        $registry = self::getRegistry();

        foreach ($config as $name => $providerConfig) {
            $registry->configure($name, $providerConfig);
        }
    }

    /**
     * Sets the PSR-18 HTTP client used by all drivers.
     *
     * @param ClientInterface $client PSR-18 compliant HTTP client.
     */
    public static function setHttpClient(ClientInterface $client): void
    {
        self::$httpClient = $client;
        self::syncHttpDependencies();
    }

    /**
     * Sets the PSR-17 factories used for creating HTTP requests and streams.
     *
     * @param RequestFactoryInterface $requestFactory PSR-17 request factory.
     * @param StreamFactoryInterface $streamFactory PSR-17 stream factory.
     */
    public static function setFactories(
        RequestFactoryInterface $requestFactory,
        StreamFactoryInterface $streamFactory,
    ): void {
        self::$requestFactory = $requestFactory;
        self::$streamFactory = $streamFactory;
        self::syncHttpDependencies();
    }

    /**
     * Adds a global middleware that applies to ALL requests.
     *
     * @param MiddlewareInterface $middleware The middleware to add.
     */
    public static function withMiddleware(MiddlewareInterface $middleware): void
    {
        self::$pipeline = self::getPipeline()->pipe($middleware);
    }

    /**
     * Enables global cost tracking with the given pricing engine.
     *
     * Adds a CostTrackingMiddleware to the global pipeline. After each request,
     * the PricingResult is available via $context->getPricingResult().
     *
     * @param PricingEngineInterface $engine Pricing engine to use for cost calculations.
     * @param float|null $budgetLimit Maximum budget in USD. Null = no limit.
     */
    public static function withPricing(PricingEngineInterface $engine, ?float $budgetLimit = null): void
    {
        self::withMiddleware(new CostTrackingMiddleware($engine, $budgetLimit));
    }

    /**
     * Registers a custom driver factory for a provider.
     *
     * @param string $name Provider key.
     * @param callable(array<string, mixed>, ClientInterface, RequestFactoryInterface, StreamFactoryInterface): DriverInterface $factory Factory callable.
     */
    public static function registerDriver(string $name, callable $factory): void
    {
        self::getRegistry()->register($name, $factory);
    }

    /**
     * Resets all static state. Useful for testing.
     */
    public static function reset(): void
    {
        self::$registry = null;
        self::$pipeline = null;
        self::$httpClient = null;
        self::$requestFactory = null;
        self::$streamFactory = null;
    }

    /**
     * Returns the singleton DriverRegistry, creating it with default factories if needed.
     *
     * @return DriverRegistry The registry instance.
     */
    private static function getRegistry(): DriverRegistry
    {
        if (self::$registry === null) {
            $registry = new DriverRegistry();
            self::registerDefaultDrivers($registry);
            self::$registry = $registry;

            return $registry;
        }

        return self::$registry;
    }

    /**
     * Returns the global Pipeline, creating an empty one if needed.
     *
     * @return Pipeline The pipeline instance.
     */
    private static function getPipeline(): Pipeline
    {
        if (self::$pipeline === null) {
            self::$pipeline = new Pipeline();
        }

        return self::$pipeline;
    }

    /**
     * Registers the built-in driver factories for all supported providers.
     *
     * Architecture:
     * - OpenAI: Native driver with full protocol support
     * - Anthropic: Own driver (different auth, message format, tool calls, streaming)
     * - Gemini: Own driver (query param auth, user/model roles, parts-based content)
     * - Ollama: Own driver (OpenAI-compatible but no auth, Ollama-specific options)
     * - DeepSeek: Own driver (OpenAI-compatible + reasoning_content extraction)
     * - Groq, Mistral, XAI, Perplexity: OpenAIDriver with different base URLs
     *
     * @param DriverRegistry $registry The registry to populate.
     */
    private static function registerDefaultDrivers(DriverRegistry $registry): void
    {
        // OpenAI driver factory
        $registry->register('openai', function (
            array $config,
            ClientInterface $httpClient,
            RequestFactoryInterface $requestFactory,
            StreamFactoryInterface $streamFactory,
        ): DriverInterface {
            return new OpenAIDriver(
                httpClient: $httpClient,
                requestFactory: $requestFactory,
                streamFactory: $streamFactory,
                apiKey: $config['api_key'] ?? '',
                baseUrl: $config['base_url'] ?? Provider::OpenAI->baseUrl(),
                options: $config['options'] ?? [],
            );
        });

        // Anthropic driver factory — own protocol (x-api-key, system field, tool_use/tool_result)
        $registry->register('anthropic', function (
            array $config,
            ClientInterface $httpClient,
            RequestFactoryInterface $requestFactory,
            StreamFactoryInterface $streamFactory,
        ): DriverInterface {
            return new AnthropicDriver(
                httpClient: $httpClient,
                requestFactory: $requestFactory,
                streamFactory: $streamFactory,
                apiKey: $config['api_key'] ?? '',
                baseUrl: $config['base_url'] ?? Provider::Anthropic->baseUrl(),
                options: $config['options'] ?? [],
            );
        });

        // Gemini driver factory — own protocol (query param auth, user/model roles)
        $registry->register('gemini', function (
            array $config,
            ClientInterface $httpClient,
            RequestFactoryInterface $requestFactory,
            StreamFactoryInterface $streamFactory,
        ): DriverInterface {
            return new GeminiDriver(
                httpClient: $httpClient,
                requestFactory: $requestFactory,
                streamFactory: $streamFactory,
                apiKey: $config['api_key'] ?? '',
                baseUrl: $config['base_url'] ?? Provider::Gemini->baseUrl(),
                options: $config['options'] ?? [],
            );
        });

        // Ollama driver factory — OpenAI-compatible, no auth, local server
        $registry->register('ollama', function (
            array $config,
            ClientInterface $httpClient,
            RequestFactoryInterface $requestFactory,
            StreamFactoryInterface $streamFactory,
        ): DriverInterface {
            return new OllamaDriver(
                httpClient: $httpClient,
                requestFactory: $requestFactory,
                streamFactory: $streamFactory,
                apiKey: $config['api_key'] ?? '',
                baseUrl: $config['base_url'] ?? Provider::Ollama->baseUrl(),
                options: $config['options'] ?? [],
            );
        });

        // DeepSeek driver factory — OpenAI-compatible + reasoning_content
        $registry->register('deepseek', function (
            array $config,
            ClientInterface $httpClient,
            RequestFactoryInterface $requestFactory,
            StreamFactoryInterface $streamFactory,
        ): DriverInterface {
            return new DeepSeekDriver(
                httpClient: $httpClient,
                requestFactory: $requestFactory,
                streamFactory: $streamFactory,
                apiKey: $config['api_key'] ?? '',
                baseUrl: $config['base_url'] ?? Provider::DeepSeek->baseUrl(),
                options: $config['options'] ?? [],
            );
        });

        // OpenAI-compatible providers (same OpenAIDriver, different base URL)
        foreach (['groq', 'mistral', 'xai', 'perplexity'] as $compatible) {
            $registry->register($compatible, function (
                array $config,
                ClientInterface $httpClient,
                RequestFactoryInterface $requestFactory,
                StreamFactoryInterface $streamFactory,
            ) use ($compatible): DriverInterface {
                $providerEnum = Provider::from($compatible);
                $defaultBaseUrl = $providerEnum->baseUrl();

                return new OpenAIDriver(
                    httpClient: $httpClient,
                    requestFactory: $requestFactory,
                    streamFactory: $streamFactory,
                    apiKey: $config['api_key'] ?? '',
                    baseUrl: $config['base_url'] ?? $defaultBaseUrl,
                    options: $config['options'] ?? [],
                );
            });
        }
    }

    /**
     * Syncs PSR-18/PSR-17 dependencies to the registry when both are available.
     */
    private static function syncHttpDependencies(): void
    {
        if (self::$httpClient !== null && self::$requestFactory !== null && self::$streamFactory !== null) {
            self::getRegistry()->setHttpDependencies(
                self::$httpClient,
                self::$requestFactory,
                self::$streamFactory,
            );
        }
    }
}
