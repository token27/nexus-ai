<?php

declare(strict_types=1);

require __DIR__ . '/../_common.php';

use Token27\NexusAI\NexusAI;
use Token27\NexusAI\Pricing\Engine\PricingEngine;
use Token27\NexusAI\Pricing\ValueObject\ModelPrice;
use Token27\NexusAI\Exception\CostLimitException;
use Token27\Tokenizer\Registry\TokenizerRegistry;

/**
 * Example 06: Pricing and Cost Tracking
 * 
 * Demonstrates how to use the underlying nexus-ai-pricing library 
 * directly mathematically, and then how it hooks into the Nexus AI pipeline.
 */

// ======================================================================
// 1. Post-Request Billing Calculation (Static facade, zero dependencies)
// ======================================================================
echo "1. Standalone calculation (Static):\n";
$result = PricingEngine::for('claude-sonnet-4-6')->calculate(
    inputTokens: 1200,
    outputTokens: 350,
    cacheWriteTokens: 800, // E.g., Anthropic Additive Cache Writes
    cacheReadTokens: 5000  // Cached hits
);
echo "Cost for cached Anthropic call: $" . $result->totalCostUsd() . "\n";
echo "Saved by using cache: $" . $result->cacheSavingsUsd() . "\n\n";

// ======================================================================
// 2. Pre-Request Proactive Estimations (Builder using Tokenizer)
// ======================================================================
echo "2. Proactive Estimation (pre-request):\n";
// This uses token27/nexus-ai-tokenizer to calculate offline immediately
$engineWithTokenizer = PricingEngine::withTokenizer(TokenizerRegistry::createDefault());
$estimated = $engineWithTokenizer->make('gpt-4o')
    ->estimate('Generate a dense historical assessment regarding the Roman Empire.');

echo "Estimated Tokens for 'gpt-4o': " . $estimated->inputTokens()->count() . "\n";
echo "Estimated Cost: $" . $estimated->totalCostUsd() . "\n\n";

// ======================================================================
// 3. Integration with Nexus AI Global Pipeline
// ======================================================================
echo "3. Integrated Automatic Cost Tracking in Nexus AI:\n";

// Create custom engine
$customEngine = new PricingEngine();
// Register a custom model
$customEngine->registerPrice(new ModelPrice(
    model: 'gpt-4o-mini',
    inputPerMillion: 0.150,
    outputPerMillion: 0.600
));

// 1) Configure provider + HTTP stack + custom pricing engine + budget limit.
// The library will automatically sum cost for every request.
bootNexus(
    providers: [
        'openai' => [
            'api_key' => requireEnv('OPENAI_API_KEY'),
        ],
    ],
    engine: $customEngine,
    budgetLimit: 0.05,
);

try {
    // Send a real request (will fail gracefully since API key is fake in this example)
    $response = NexusAI::using('openai', 'gpt-4o-mini')
        ->withPrompt('Write a one paragraph summary of quantum computing.')
        ->asText();

    echo "Request successful. The library tracked the budget silently in the background.\n";

} catch (CostLimitException $e) {
    echo ">>> BUDGET LIMIT REACHED! <<<\n";
    echo $e->getMessage() . "\n";
    echo "Estimated cost that triggered block: $" . $e->estimatedCost . "\n";
} catch (\Exception $e) {
    echo "Network/Driver Error: " . $e->getMessage() . "\n";
}



