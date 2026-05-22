<?php

declare(strict_types=1);

require __DIR__ . '/../_common.php';

use Token27\NexusAI\NexusAI;
use Token27\NexusAI\Exception\RateLimitException;
use Token27\NexusAI\Exception\DriverException;
use Token27\NexusAI\Exception\StructuredOutputException;

/**
 * Example 10: Error Handling
 * 
 * Robust LLM applications require intelligent error handling.
 * Nexus AI breaks failures into distinct exception classes so you can 
 * react appropriately (e.g., retrying, showing user alerts, logging).
 */

// 1. Configure provider + HTTP stack.
// If OPENAI_API_KEY exists, request may succeed and skip some error branches.
// Without OPENAI_API_KEY, we keep an invalid key to force deterministic failures.
bootNexus([
    'openai' => [
        'api_key' => envOrNull('OPENAI_API_KEY') ?? 'invalid-key-for-demonstration',
    ],
]);

echo "Demonstrating specific exceptions...\n\n";

try {
    NexusAI::using('openai', 'gpt-4o')
        ->withPrompt('Tell me a joke.')
        ->asText();

} catch (RateLimitException $e) {
    // Caught when the provider returns HTTP 429 (Too Many Requests).
    // The RetryMiddleware normally handles this automatically, but if max 
    // retries are exhausted or it's not configured, you hit this.
    echo "Warning: Rate Limit Exceeded.\n";
    echo "Provider: " . $e->provider . "\n";

    // Some providers return X-Ratelimit-Reset metadata
    if ($e->retryAfter) {
        echo "Please try again in {$e->retryAfter} seconds.\n";
    }

} catch (StructuredOutputException $e) {
    // Thrown uniquely from asStructured() if parsing/deserialization completely fails
    echo "Warning: LLM output did not match correct json Schema.\n";
    echo "Raw response from LLM: \n" . $e->responseText . "\n";

} catch (\Token27\NexusAI\Exception\CostLimitException $e) {
    // Caught if CostTrackingMiddleware budgets are exceeded
    echo "Warning: Wallet Empty!\n";
    echo "Estimated Cost: $" . $e->estimatedCost . "\n";

} catch (DriverException $e) {
    // A concrete failure from the provider (e.g. 401 Unauthorized, 400 Bad Request, 500)
    echo "Provider API Error!\n";
    echo "Message: " . $e->getMessage() . "\n";
    echo "Provider: " . $e->provider . "\n";

    if ($e->statusCode !== null) {
        echo "HTTP Status Code: " . $e->statusCode . "\n";
        if ($e->statusCode === 401) {
            echo "-> Looks like your API key is invalid!\n";
        }
    }
    if ($e->responseBody !== null) {
        // You can inspect the raw array data from the provider
        echo "Raw Response Body context captured.\n";
    }

} catch (\Token27\NexusAI\Exception\NexusException $e) {
    // The base exception for everything else (e.g., invalid arguments)
    echo "General Nexus Library Error: " . $e->getMessage() . "\n";
} catch (\Exception $e) {
    // Any other PHP exception
    echo "Unknown Error: " . $e->getMessage() . "\n";
}



