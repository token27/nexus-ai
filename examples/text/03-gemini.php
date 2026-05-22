<?php

declare(strict_types=1);

// Load shared helpers:
// - .env auto-loading
// - Nexus bootstrapping
// - standardized summary printers
require __DIR__ . '/_common.php';

use Token27\NexusAI\NexusAI;

echo "=== Text Pricing Example: Gemini ===" . PHP_EOL;

try {
    // 1) Read required API key from environment.
    $apiKey = requireEnv('GEMINI_API_KEY');
    // Optional override to switch model without touching code.
    $model = envOrNull('GEMINI_TEXT_MODEL') ?? 'gemini-3.1-flash';

    // 2) Configure Nexus with Gemini credentials and pricing middleware.
    bootNexus([
        'gemini' => [
            'api_key' => $apiKey,
        ],
    ]);

    // 3) Execute text generation call.
    $response = NexusAI::using('gemini', $model)
        ->withSystemPrompt('You are concise and practical.')
        ->withPrompt('Write three practical tips to keep PHP services maintainable.')
        ->withTemperature(0.3)
        ->asText();

    // 4) Print text preview + usage + pricing details.
    printTextResponseSummary($response);
} catch (Throwable $e) {
    // Keep output concise to stay example-friendly.
    echo 'Error: ' . $e->getMessage() . PHP_EOL;
}
