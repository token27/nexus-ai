<?php

declare(strict_types=1);

// Load shared helpers:
// - .env auto-loading
// - Nexus bootstrapping
// - standardized summary printers
require __DIR__ . '/_common.php';

use Token27\NexusAI\NexusAI;
use Token27\NexusAI\Pricing\Engine\PricingEngine;
use Token27\NexusAI\Pricing\Registry\PricingRegistry;
use Token27\NexusAI\Pricing\ValueObject\ModelPrice;

echo "=== Text Pricing Example: Ollama ===" . PHP_EOL;

try {
    // 1) Local model/base URL usually come from local setup.
    $model = envOrNull('OLLAMA_TEXT_MODEL') ?? 'llama3.1:8b';
    $baseUrl = envOrNull('OLLAMA_BASE_URL') ?? 'http://localhost:11434/v1';

    // 2) Local models are often unknown to default catalogs,
    // so we register an explicit custom price for this exact model ID.
    $engine = PricingEngine::withRegistry(PricingRegistry::createDefault());
    $engine->registerPrice(new ModelPrice(
        model: $model,
        inputPerMillion: 0.25,
        outputPerMillion: 0.50,
        notes: 'Example custom pricing for local Ollama model',
    ));

    // 3) Configure the local Ollama provider and inject our custom engine.
    bootNexus([
        'ollama' => [
            'api_key' => '',
            'base_url' => $baseUrl,
        ],
    ], $engine);

    // 4) Execute text generation through Ollama.
    $response = NexusAI::using('ollama', $model)
        ->withSystemPrompt('You are concise and practical.')
        ->withPrompt('Write three practical tips to keep PHP services maintainable.')
        ->withTemperature(0.3)
        ->asText();

    // 5) Print text preview + usage + pricing details.
    printTextResponseSummary($response);
} catch (Throwable $e) {
    // Common local failure: Ollama daemon not running or model not pulled.
    echo 'Error: ' . $e->getMessage() . PHP_EOL;
    echo 'Tip: make sure Ollama is running locally and the model is pulled.' . PHP_EOL;
}
