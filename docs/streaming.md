# Streaming

## Basic Streaming

```php
$stream = NexusAI::using('openai', 'gpt-4o')
    ->withPrompt('Write a short story about a PHP developer.')
    ->asStream();

// Iterate text fragments
foreach ($stream->text() as $fragment) {
    echo $fragment;
    ob_flush();
    flush();
}
```

## Collect to Full Response

```php
$stream = NexusAI::using('anthropic', 'claude-sonnet-4-20250514')
    ->withPrompt('Explain async PHP in 3 paragraphs.')
    ->asStream();

$response = $stream->collect(); // TextResponse

echo $response->text;
echo $response->usage->totalTokens();
echo $response->finishReason->value;
```

## Chunk Types

The stream yields typed `StreamChunkInterface` objects:

| Chunk Class | When | Key Properties |
|-------------|------|----------------|
| `TextChunk` | Text delta arrives | `text`, `index`, `finishReason` |
| `ToolCallChunk` | Tool call delta arrives | `toolCallId`, `toolName`, `argumentsDelta` |
| `UsageChunk` | Final chunk with usage | `usage` (promptTokens, completionTokens) |

## Raw Chunk Iteration

```php
foreach ($stream as $chunk) {
    if ($chunk instanceof TextChunk) {
        echo $chunk->text;
    } elseif ($chunk instanceof UsageChunk) {
        echo "\nTokens: " . $chunk->usage->totalTokens();
    }
}
```

## Server-Sent Events (SSE) with HTTP

```php
header('Content-Type: text/event-stream');
header('Cache-Control: no-cache');

$stream = NexusAI::using('openai', 'gpt-4o')
    ->withPrompt($_GET['prompt'])
    ->asStream();

foreach ($stream->text() as $fragment) {
    echo "data: " . json_encode(['text' => $fragment]) . "\n\n";
    ob_flush();
    flush();
}

echo "data: [DONE]\n\n";
```

---

> **← Back:** [Structured Output](structured-output.md) · **Next:** [Embeddings →](embeddings.md)
