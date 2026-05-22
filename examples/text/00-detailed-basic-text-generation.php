<?php

declare(strict_types=1);

require __DIR__ . '/_common.php';

use Token27\NexusAI\NexusAI;

/**
 * Example: Detailed Basic Text Generation
 *
 * This is a "walkthrough" version of the simple OpenAI text example.
 * It explains each step in detail so a new developer can learn the flow
 * just by reading the file from top to bottom.
 */

echo "=== Detailed Basic Text Generation ===" . PHP_EOL . PHP_EOL;

try {
    // 1) Read provider credentials and model from environment variables.
    // - OPENAI_API_KEY is required.
    // - OPENAI_TEXT_MODEL is optional (defaults to gpt-4o-mini).
    $apiKey = requireEnv('OPENAI_API_KEY');
    $model = envOrNull('OPENAI_TEXT_MODEL') ?? 'gpt-4o-mini';

    // 2) Boot Nexus static facade:
    // - Sets HTTP client + PSR factories.
    // - Configures provider credentials.
    // - Enables pricing middleware (automatic pricingResult in response).
    bootNexus([
        'openai' => [
            'api_key' => $apiKey,
        ],
    ]);

    echo "Sending request to OpenAI ({$model})..." . PHP_EOL . PHP_EOL;

    // 3) Build and execute a fluent text request.
    // - withSystemPrompt: high-level behavior instructions.
    // - withPrompt: user message.
    // - withTemperature: creativity/randomness.
    // - withMaxTokens: response length cap.
    // - asText: sends request and returns TextResponse.
    $response = NexusAI::using('openai', $model)
        ->withSystemPrompt('You are a helpful, concise AI assistant.')
        ->withPrompt('Provide three key benefits of using design patterns in PHP.')
        ->withTemperature(0.7)
        ->withMaxTokens(500)
        ->asText();

    // 4) Print generated content.
    echo "========= RESPONSE =========" . PHP_EOL;
    echo $response->text . PHP_EOL;
    echo "============================" . PHP_EOL . PHP_EOL;

    // 5) Print usage details (tokens consumed).
    echo "Usage details:" . PHP_EOL;
    echo "- Text Input Tokens: " . $response->usage->textInputTokens . PHP_EOL;
    echo "- Text Output Tokens: " . $response->usage->textOutputTokens . PHP_EOL;
    echo "- Total Tokens: " . $response->usage->totalTokens() . PHP_EOL;
    echo "- Finish Reason: " . $response->finishReason->value . PHP_EOL;

    // 6) Print cost details if pricing middleware is active.
    if ($response->pricingResult !== null) {
        echo "- Total USD: " . $response->pricingResult->totalCostUsd() . PHP_EOL;
        echo "- Pricing format: " . $response->pricingResult->format() . PHP_EOL;
    }
} catch (Throwable $e) {
    echo "Error: " . $e->getMessage() . PHP_EOL;
}
