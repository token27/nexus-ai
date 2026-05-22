<?php

declare(strict_types=1);

// Load shared helpers:
// - .env auto-loading
// - Nexus bootstrapping
// - standardized summary printers
require __DIR__ . '/_common.php';

use Token27\NexusAI\NexusAI;

echo "=== Text Pricing Example: DeepSeek ===" . PHP_EOL;

try {
    // 1) Read required API key from environment.
    $apiKey = requireEnv('DEEPSEEK_API_KEY');
    // Optional override for easy model experiments.
    $model = envOrNull('DEEPSEEK_TEXT_MODEL') ?? 'deepseek-chat';

    // 2) Configure Nexus with DeepSeek credentials and pricing middleware.
    bootNexus([
        'deepseek' => [
            'api_key' => $apiKey,
        ],
    ]);

    // 3) Execute a normal text generation call.
    $response = NexusAI::using('deepseek', $model)
        ->withSystemPrompt('You are concise and practical.')
        ->withPrompt('Write three practical tips to keep PHP services maintainable.')
        ->withTemperature(0.3)
        ->asText();

    // 4) Print text preview + usage + pricing details.
    printTextResponseSummary($response);
} catch (Throwable $e) {
    // Keep examples readable with a single-line error.
    echo 'Error: ' . $e->getMessage() . PHP_EOL;
}
