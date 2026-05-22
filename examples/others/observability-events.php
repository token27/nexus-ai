<?php

declare(strict_types=1);

require __DIR__ . '/../_common.php';

use Token27\NexusAI\NexusAI;
use Token27\NexusAI\Pipeline\Middleware\LoggingMiddleware;
use Psr\Log\AbstractLogger;

/**
 * Example 07: Observability and Logging
 * 
 * Demonstrates how to hook into the library's Pipeline utilizing LoggingMiddleware
 * to capture request timing, cost computation, token usage, and payload metadata
 * automatically via any standard PSR-3 Logger container.
 */

// 1. Create a dummy PSR-3 Logger for demonstration.
// In actual projects, you would inject Monolog, Laravel's Log, or Symfony's Logger.
class ConsoleLogger extends AbstractLogger
{
    public function log($level, \Stringable|string $message, array $context = []): void
    {
        $jsonContext = json_encode($context, JSON_PRETTY_PRINT);
        echo sprintf("\n[%s] %s\n%s\n", strtoupper((string) $level), $message, $jsonContext);
    }
}

$logger = new ConsoleLogger();

// 2. Add the Logging Middleware globally.
// This will automatically trap and evaluate every request passing through the facade.
NexusAI::withMiddleware(new LoggingMiddleware(
    logger: $logger,
    level: 'info',
    logPayload: true // Careful: Setting this to true logs raw prompts which may contain sensitive PII
));

// 3. Configure provider + HTTP stack through shared bootstrap.
bootNexus([
    'anthropic' => ['api_key' => requireEnv('ANTHROPIC_API_KEY')],
]);

echo "Generating response to trigger PSR-3 log events...\n\n";

try {
    // 3. Make the API Call
    // You will see two logs: "NexusAI request starting" and "NexusAI request failed/completed"
    $response = NexusAI::using('anthropic', 'claude-sonnet-4-6')
        ->withSystemPrompt('You are a logging assistant.')
        ->withPrompt('Why is Observability so critical in distributed systems?')
        ->asText();

    echo "========= RESPONSE =========\n";
    echo trim($response->text) . "\n";
    echo "============================\n";

} catch (\Exception $e) {
    // In scenarios where it fails, the logger will output the specific driver error 
    // before the exception arrives here.
    echo "\nAn expected API exception happened (because the key is fake): " . $e->getMessage() . "\n";
}



