<?php

declare(strict_types=1);

// Load shared helpers:
// - .env auto-loading
// - Nexus bootstrapping
// - standardized summary printers
require __DIR__ . '/_common.php';

use Token27\NexusAI\NexusAI;

echo "=== Text Pricing Example: xAI ===" . PHP_EOL;

try {
    // 1) Read required API key from environment.
    $apiKey = requireEnv('XAI_API_KEY');
    // Optional override for model switching.
    $model = envOrNull('XAI_TEXT_MODEL') ?? 'grok-4-fast';

    // 2) Configure Nexus with xAI credentials and pricing middleware.
    bootNexus([
        'xai' => [
            'api_key' => $apiKey,
        ],
    ]);

    // 3) Execute text generation using xAI provider key.
    $response = NexusAI::using('xai', $model)
        ->withSystemPrompt('You are concise and practical.')
        ->withPrompt('Write three practical tips to keep PHP services maintainable.')
        ->withTemperature(0.3)
        ->asText();

    // 4) Print text preview + usage + pricing details.
    printTextResponseSummary($response);
} catch (Throwable $e) {
    // Keep output concise and easy to scan.
    echo 'Error: ' . $e->getMessage() . PHP_EOL;
}
