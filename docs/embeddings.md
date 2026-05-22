# Embeddings

Text embeddings convert strings into numerical vectors, enabling semantic search, similarity comparisons, clustering, and RAG pipelines.

## Basic Usage

```php
use Token27\NexusAI\NexusAI;

$response = NexusAI::using('openai', 'text-embedding-3-small')
    ->withPrompt('The quick brown fox jumps over the lazy dog')
    ->asEmbeddings();

$vector = $response->embeddings[0]->vector; // float[]
echo count($vector); // 1536 (dimensions)
```

## Batch Embeddings

Embed multiple texts in a single API call:

```php
use Token27\NexusAI\Request\EmbeddingRequest;

$request = new EmbeddingRequest(
    provider: 'openai',
    model: 'text-embedding-3-small',
    input: [
        'PHP is a server-side scripting language',
        'Python is great for data science',
        'JavaScript runs in the browser',
    ],
    dimensions: 512, // Optional — reduce dimensions for some models
);

$driver = /* resolve driver */;
$response = $driver->embeddings($request);

foreach ($response->embeddings as $i => $embedding) {
    echo "Text {$i}: " . count($embedding->vector) . " dimensions\n";
}
```

## Response Object

```php
$response->embeddings; // array<Embedding>
$response->usage;      // Usage (promptTokens, completionTokens)

// Embedding value object
$embedding = $response->embeddings[0];
$embedding->vector; // float[] — the embedding vector
$embedding->index;  // int — position in batch
```

## Semantic Similarity (Cosine)

```php
function cosineSimilarity(array $a, array $b): float
{
    $dot = array_sum(array_map(fn($x, $y) => $x * $y, $a, $b));
    $magA = sqrt(array_sum(array_map(fn($x) => $x * $x, $a)));
    $magB = sqrt(array_sum(array_map(fn($x) => $x * $x, $b)));
    return $dot / ($magA * $magB);
}

// Embed query and documents
$queryResponse = NexusAI::using('openai', 'text-embedding-3-small')
    ->withPrompt('What is dependency injection?')
    ->asEmbeddings();

$docResponse = NexusAI::using('openai', 'text-embedding-3-small')
    ->withPrompt('Dependency injection is a design pattern...')
    ->asEmbeddings();

$similarity = cosineSimilarity(
    $queryResponse->embeddings[0]->vector,
    $docResponse->embeddings[0]->vector,
);

echo "Similarity: {$similarity}"; // 0.0 to 1.0
```

## RAG (Retrieval-Augmented Generation) Pattern

```php
// 1. Index documents
$documents = ['PHP manual section...', 'Laravel docs...', 'Symfony guide...'];
$embeddings = [];

foreach ($documents as $i => $doc) {
    $response = NexusAI::using('openai', 'text-embedding-3-small')
        ->withPrompt($doc)
        ->asEmbeddings();
    $embeddings[$i] = $response->embeddings[0]->vector;
}

// 2. Query
$query = 'How do I use dependency injection in PHP?';
$queryEmbedding = NexusAI::using('openai', 'text-embedding-3-small')
    ->withPrompt($query)
    ->asEmbeddings()
    ->embeddings[0]->vector;

// 3. Find most similar document
$scores = [];
foreach ($embeddings as $i => $vector) {
    $scores[$i] = cosineSimilarity($queryEmbedding, $vector);
}
arsort($scores);
$bestDoc = $documents[array_key_first($scores)];

// 4. Generate answer with context
$answer = NexusAI::using('openai', 'gpt-4o')
    ->withSystemPrompt('Answer based on the provided context only.')
    ->withMessages([
        new UserMessage("Context:\n{$bestDoc}\n\nQuestion: {$query}"),
    ])
    ->asText();
```

## Available Models

| Model | Provider | Dimensions | Best For |
|-------|----------|-----------|----------|
| `text-embedding-3-small` | OpenAI | 1536 (reducible) | Cost-efficient, general use |
| `text-embedding-3-large` | OpenAI | 3072 (reducible) | Highest accuracy |
| `text-embedding-ada-002` | OpenAI | 1536 | Legacy |
| `embedding-001` | Gemini | 768 | Gemini ecosystem |

---

> **← Back:** [Streaming](streaming.md) · **Next:** [Tool Calling →](tool-calling.md)
