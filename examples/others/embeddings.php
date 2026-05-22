<?php

declare(strict_types=1);

require __DIR__ . '/../_common.php';

use Token27\NexusAI\NexusAI;

/**
 * Example 09: Embeddings Generation
 * 
 * Demonstrates the Library's support for generating Vector Embeddings
 * from text inputs, which is critical for RAG (Retrieval-Augmented Generation)
 * and semantic search applications.
 */

// 1. Configure provider + HTTP stack.
bootNexus([
    'openai' => [
        'api_key' => requireEnv('OPENAI_API_KEY'),
    ],
]);

echo "Starting Embeddings Generation...\n\n";

try {
    // Generate an embedding for a text string.
    $embeddingResponse = NexusAI::using('openai', 'text-embedding-3-small')
        ->withPrompt('A futuristic city covered in snow, beautiful lighting, 8k resolution')
        // We can pass dimensions for supported models via withOption
        ->withOption('dimensions', 1536)
        ->asEmbeddings();

    // Contains an array of floating point numbers mapping the spatial embedding vector.
    // The exact property is ->values (from Token27\NexusAI\ValueObject\Embedding)
    $vector = $embeddingResponse->embeddings[0]->values;

    echo "Successfully generated embedding vector.\n";
    echo "Vector Dimensions: " . count($vector) . "\n";

    // Print the first 5 dimensions just as a preview
    echo "Preview of first 5 array values: \n";
    for ($i = 0; $i < 5; $i++) {
        echo "- {$vector[$i]}\n";
    }

} catch (\Exception $e) {
    echo "An error occurred: " . $e->getMessage() . "\n";
}



