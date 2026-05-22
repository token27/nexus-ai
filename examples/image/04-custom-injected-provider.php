<?php

declare(strict_types=1);

// Load shared helpers:
// - .env auto-loading
// - Nexus bootstrapping
// - standardized summary printers
require __DIR__ . '/../_common.php';

use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Token27\NexusAI\Contract\DriverInterface;
use Token27\NexusAI\Driver\OpenAI\OpenAIDriver;
use Token27\NexusAI\NexusAI;
use Token27\NexusAI\Pricing\Engine\PricingEngine;
use Token27\NexusAI\Pricing\Registry\PricingRegistry;
use Token27\NexusAI\Pricing\ValueObject\ModelPrice;

echo "=== Image Pricing Example: Custom Injected Provider ===" . PHP_EOL;

try {
    // 1) Define provider key + local endpoint settings.
    // This example demonstrates how to plug an OpenAI-compatible image server.
    $provider = 'local-image-provider';
    $model = envOrNull('LOCAL_IMAGE_MODEL') ?? 'local-image-model-v1';
    $baseUrl = envOrNull('LOCAL_IMAGE_BASE_URL') ?? 'http://localhost:1234/v1';
    $apiKey = envOrNull('LOCAL_IMAGE_API_KEY') ?? 'local-no-key';

    // 2) Register custom model pricing so cost calculations are meaningful.
    $engine = PricingEngine::withRegistry(PricingRegistry::createDefault());
    $engine->registerPrice(new ModelPrice(
        model: $model,
        inputPerMillion: 0.20,
        outputPerMillion: 0.40,
        imageOutputPerMillion: 40.0,
        notes: 'Example custom pricing for injected local image provider',
    ));

    // 3) Boot Nexus with custom provider config and custom pricing engine.
    bootNexus([
        $provider => [
            'api_key' => $apiKey,
            'base_url' => $baseUrl,
        ],
    ], $engine);

    // 4) Register provider -> driver mapping.
    // We reuse OpenAIDriver because endpoint is OpenAI-compatible.
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

    // 5) Execute image generation request.
    $response = NexusAI::using($provider, $model)
        ->withPrompt('A minimalist icon set of weather symbols on transparent background.')
        ->withOutputFormat('png')
        ->withBackground('transparent')
        ->asImage();

    // 6) Print image summary + usage + pricing details.
    printImageResponseSummary($response);

    // 7) Save first image when payload is base64.
    $outputPath = __DIR__ . '/output-custom.png';
    if (saveFirstImageIfBase64($response, $outputPath)) {
        echo 'Saved first image to: ' . $outputPath . PHP_EOL;
    }
} catch (Throwable $e) {
    // Typical failure: endpoint not running or wrong base URL.
    echo 'Error: ' . $e->getMessage() . PHP_EOL;
    echo 'Tip: point LOCAL_IMAGE_BASE_URL to your local OpenAI-compatible image server.' . PHP_EOL;
}
