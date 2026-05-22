<?php

declare(strict_types=1);

require __DIR__ . '/../_common.php';

use Token27\NexusAI\NexusAI;
use Token27\NexusAI\Contract\DriverInterface;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;

/**
 * Example 08: Custom Driver Injection
 * 
 * Demonstrates advanced customization: 
 * 1. How to inject custom HTTP clients for strict firewall environments.
 * 2. How to register a custom anonymous driver factory.
 */

// --- Scenario 1: Injecting Custom HTTP Clients ---
// Nexus AI relies on PSR-18 HTTP Clients to make network requests.
// You have two main ways to inject a custom client:

// Option A: Explicitly instantiate a well-known client like Guzzle (Requires guzzlehttp/guzzle)
// $httpClient = new \GuzzleHttp\Client(['timeout' => 30]);
// NexusAI::setHttpClient($httpClient);

// Option B: Auto-discover the HTTP client using HTTPlug (Requires php-http/discovery)
// $httpClient = \Http\Discovery\Psr18ClientDiscovery::find();
// NexusAI::setHttpClient($httpClient);

// We bootstrap using shared helper so env vars and pricing are available.
bootNexus([
    'openai' => ['api_key' => requireEnv('OPENAI_API_KEY')],
    'local-llm' => ['api_key' => 'none', 'base_url' => 'http://localhost:11434/v1'],
]);

// --- Scenario 2: Creating a Custom Driver ---
// You can build your own driver for an unsupported API by extending AbstractDriver 
// or implementing DriverInterface, and then registering a factory closure.

NexusAI::registerDriver('local-llm', function (array $config, ClientInterface $httpClient, RequestFactoryInterface $requestFactory, StreamFactoryInterface $streamFactory): DriverInterface {
    // Return your own DriverInterface instance!
    // e.g., return new MyLocalAIDriver($httpClient, $requestFactory, ...);

    // For demonstration, we'll return a generic Exception to indicate the factory works
    throw new \RuntimeException('Custom Driver factory invoked successfully!');
});

echo "Executing custom registered driver...\n\n";

try {
    $response = NexusAI::using('local-llm', 'llama-3')
        ->withSystemPrompt('You are a technical mentor.')
        ->withPrompt('Explain Dependency Injection.')
        ->asText();

    echo $response->text . "\n";
} catch (\RuntimeException $e) {
    echo "As expected: " . $e->getMessage() . "\n";
} catch (\Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}



