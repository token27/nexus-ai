<?php

declare(strict_types=1);

// Load shared helpers:
// - .env auto-loading
// - Nexus bootstrapping
// - standardized summary printers
require __DIR__ . '/_common.php';

use Token27\NexusAI\NexusAI;

echo "=== Text Pricing Example: Anthropic ===" . PHP_EOL;

try {
    // 1) Read required API key from environment.
    $apiKey = requireEnv('ANTHROPIC_API_KEY');
    // Optional override for fast model switching in local tests.
    $model = envOrNull('ANTHROPIC_TEXT_MODEL') ?? 'claude-sonnet-4-6';

    // 2) Configure Nexus with Anthropic credentials and pricing middleware.
    bootNexus([
        'anthropic' => [
            'api_key' => $apiKey,
        ],
    ]);

    // 3) Run a standard text request.
    $response = NexusAI::using('anthropic', $model)
        ->withSystemPrompt('You are concise and practical.')
        ->withPrompt('Write three practical tips to keep PHP services maintainable.')
        ->withTemperature(0.3)
        ->asText();

    // 4) Print text preview + usage + pricing details.
    printTextResponseSummary($response);
} catch (Throwable $e) {
    // Keep output simple and readable for quick troubleshooting.
    echo 'Error: ' . $e->getMessage() . PHP_EOL;
}
