<?php

declare(strict_types=1);

require __DIR__ . '/../_common.php';

use Token27\NexusAI\NexusAI;
use Token27\NexusAI\Pipeline\Middleware\ValidationMiddleware;
use Token27\NexusAI\Pipeline\Middleware\RetryMiddleware;
use Token27\NexusAI\Pipeline\Middleware\CircuitBreakerMiddleware;

/**
 * Example 05: Middleware Pipeline
 * 
 * The Nexus AI Pipeline allows injecting robust logic around AI requests
 * such as resiliency, retries, and rate limit protections.
 * This example adds Circuit Breaker and Retry logic globally.
 */

// 1. Configure provider + HTTP stack.
// If OPENAI_API_KEY is missing, keep invalid fallback to demonstrate failures.
bootNexus([
    'openai' => [
        'api_key' => envOrNull('OPENAI_API_KEY') ?? 'invalid-key-to-force-failures',
    ],
]);

// 1. Add Validation internally (always recommended to ensure basic request validity)
NexusAI::withMiddleware(new ValidationMiddleware());

// 2. Add Retries (with exponential backoff and jitter for Rate Limit anomalies)
NexusAI::withMiddleware(new RetryMiddleware(
    maxRetries: 3,
    baseDelayMs: 1000,
    multiplier: 1.5,
    jitter: true
));

// 3. Add CircuitBreaker
// Prevents continuous requests to a provider if it is failing repeatedly.
// If 5 requests fail in a row, it opens the circuit, failing immediately for 60 seconds
// to protect your wallet and provider limits.
NexusAI::withMiddleware(new CircuitBreakerMiddleware(
    failureThreshold: 5,
    cooldownSeconds: 60
));

echo "Sending requests to a badly configured OpenAI instance...\n";
echo "The Circuit Breaker and Retry middlewares will handle this.\n\n";

try {
    for ($i = 1; $i <= 6; $i++) {
        echo "Attempt #{$i}...\n";
        try {
            $response = NexusAI::using('openai', 'gpt-4o-mini')
                ->withPrompt('Say hello!')
                ->asText();
            echo "Success: " . $response->text . "\n";
        } catch (\Token27\NexusAI\Exception\DriverException $e) {
            echo "Failed: [{$e->provider}] " . $e->getMessage() . "\n";
        }
    }
} catch (\Exception $e) {
    echo "General failure: " . $e->getMessage() . "\n";
}



