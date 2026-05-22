<?php

declare(strict_types=1);

// Load shared helpers:
// - .env auto-loading
// - Nexus bootstrapping
// - standardized summary printers
require __DIR__ . '/_common.php';

use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Token27\NexusAI\Contract\DriverInterface;
use Token27\NexusAI\Driver\OpenAI\OpenAIDriver;
use Token27\NexusAI\NexusAI;
use Token27\NexusAI\Pricing\Engine\PricingEngine;
use Token27\NexusAI\Pricing\Registry\PricingRegistry;
use Token27\NexusAI\Pricing\ValueObject\ModelPrice;

echo "=== Text Pricing Example: Custom Injected Provider (Local/OpenAI-Compatible) ===" . PHP_EOL;

try {
    // 1) Define a custom provider key and local server settings.
    // This allows you to plug your own OpenAI-compatible endpoint.
    $provider = 'local-custom';
    $model = envOrNull('LOCAL_LLM_MODEL') ?? 'llama-3.1-8b-instruct';
    $baseUrl = envOrNull('LOCAL_LLM_BASE_URL') ?? 'http://localhost:1234/v1';
    $apiKey = envOrNull('LOCAL_LLM_API_KEY') ?? 'local-no-key';

    // 2) Register custom pricing for this local model ID.
    // Without this, pricing can show "unknown model".
    $engine = PricingEngine::withRegistry(PricingRegistry::createDefault());
    $engine->registerPrice(new ModelPrice(
        model: $model,
        inputPerMillion: 0.20,
        outputPerMillion: 0.40,
        notes: 'Example custom pricing for an injected local provider',
    ));

    // 3) Boot Nexus with the custom provider config and custom engine.
    bootNexus([
        $provider => [
            'api_key' => $apiKey,
            'base_url' => $baseUrl,
        ],
    ], $engine);

    // 4) Register provider -> driver factory mapping.
    // We reuse OpenAIDriver because our endpoint speaks OpenAI-compatible JSON.
    NexusAI::registerDriver(
        $provider,
        function (
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
                baseUrl: $config['base_url'] ?? 'http://localhost:1234/v1',
                options: $config['options'] ?? [],
            );
        },
    );

    // 5) Execute request normally through the custom provider.
    $response = NexusAI::using($provider, $model)
        ->withSystemPrompt('You are concise and practical.')
        ->withPrompt('Write three practical tips to keep PHP services maintainable.')
        ->withTemperature(0.3)
        ->asText();

    // 6) Print text preview + usage + pricing details.
    printTextResponseSummary($response);
} catch (Throwable $e) {
    // Typical failure: local endpoint not running or wrong base URL.
    echo 'Error: ' . $e->getMessage() . PHP_EOL;
    echo 'Tip: point LOCAL_LLM_BASE_URL to your local OpenAI-compatible server.' . PHP_EOL;
}
