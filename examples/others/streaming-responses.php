<?php

declare(strict_types=1);

require __DIR__ . '/../_common.php';

use Token27\NexusAI\NexusAI;

/**
 * Example 02: Streaming Responses
 * 
 * This example demonstrates how to stream AI responses in real-time.
 * Streaming is crucial for long outputs to improve perceived performance
 * and user experience.
 */

// 1. Setup provider + HTTP stack.
bootNexus([
    'openai' => [
        'api_key' => requireEnv('OPENAI_API_KEY'),
    ],
]);

echo "Streaming response from OpenAI...\n\n";

try {
    // 2. Request a streamed response using asStream()
    // By passing 'true' to stream() in the OpenAIDriver configuration or internally
    // the driver returns a generator.
    $streamGenerator = NexusAI::using('openai', 'gpt-4o-mini')
        ->withPrompt('Write a small poem about artificial intelligence in PHP.')
        ->asStream();

    // 3. Consume the stream
    foreach ($streamGenerator as $chunk) {
        if ($chunk instanceof \Token27\NexusAI\Contract\StreamChunkInterface) {
            echo $chunk->getText();
        }

        // Use ob_flush() and flush() in web environments to push to browser directly
        // ob_flush(); 
        // flush();
    }

    echo "\n\n(Stream finished successfully)\n";

} catch (\Exception $e) {
    echo "An error occurred: " . $e->getMessage() . "\n";
}



