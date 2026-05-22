<?php

declare(strict_types=1);

// Load shared helpers:
// - .env auto-loading
// - Nexus bootstrapping
// - standardized summary printers
require __DIR__ . '/_common.php';

use Token27\NexusAI\NexusAI;

echo "=== Text Pricing Example: OpenAI ===" . PHP_EOL;

try {
    // 1) Read required API key from environment.
    $apiKey = requireEnv('OPENAI_API_KEY');
    // Optional override to try another OpenAI text model quickly.
    $model = envOrNull('OPENAI_TEXT_MODEL') ?? 'gpt-4o-mini';

    // 2) Configure Nexus with provider credentials and pricing middleware.
    bootNexus([
        'openai' => [
            'api_key' => $apiKey,
        ],
    ]);

    // 3) Execute a normal text generation call with fluent API.
    $response = NexusAI::using('openai', $model)
        ->withSystemPrompt('You are concise and practical.')
        ->withPrompt('Write three practical tips to keep PHP services maintainable.')
        ->withTemperature(0.3)
        ->asText();

    // 4) Print text preview + usage + pricing details.
    printTextResponseSummary($response);
} catch (Throwable $e) {
    // Keep examples friendly: print clean message instead of stack trace.
    echo 'Error: ' . $e->getMessage() . PHP_EOL;
}
